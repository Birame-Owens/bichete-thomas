<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Applique les decisions de reconciliation des telephones (Phase 5 etape 1).
 *
 * Lit le CSV genere par la migration 2026_05_10_000001_normalize_existing_phones_to_e164
 * que la gerante a complete avec une colonne 'decision'. Pour chaque ligne,
 * execute la fusion FK demandee dans une transaction isolee (rollback par
 * conflit, pas tout-ou-rien).
 *
 * Decisions supportees :
 *   - MERGE_INTO_KEPT : les FK de other_id basculent vers kept_id (celui qui
 *     porte deja le E.164), puis other_id est supprime. La donnee historique
 *     du tel raw d other est perdue (non recuperable par lookup), mais ses
 *     reservations/paiements sont conservees sous kept_id.
 *   - MERGE_INTO_OTHER : symetrique. On supprime kept (qui porte le E.164),
 *     puis on UPDATE other.telephone = E.164. Strategie inversee, utile quand
 *     other a plus d historique meme s il etait stocke avec un format raw.
 *   - KEEP_BOTH : no-op. La gerante accepte que other reste introuvable via
 *     lookup (son tel reste raw) - utile quand les deux clients sont vraiment
 *     differents et qu il y a juste une coincidence de format.
 *   - SKIP : no-op explicite. Pour passer la ligne sans rien faire.
 *
 * Pour les lignes 'unparseable' : la decision attendue est SKIP (la gerante
 * a deja edite manuellement le tel via /admin/clients) ou KEEP_BOTH (rien a
 * faire). MERGE_* n a pas de sens car other n a pas de E.164 a recevoir.
 *
 * --dry-run obligatoire en premiere passe : simule sans rien ecrire.
 */
class ReconcileClientPhones extends Command
{
    protected $signature = 'clients:reconcile-phones
                            {--csv= : Chemin du CSV genere par la migration (avec colonne decision remplie)}
                            {--dry-run : Simule sans rien ecrire en base}';

    protected $description = 'Applique les decisions de fusion/conservation des doublons telephone (Phase 5).';

    /** Tables qui referencent clients.id par FK (a transferer lors d un merge). */
    private const FK_TABLES = [
        'reservations' => 'client_id',
        'paiements' => 'client_id',
        'avis_coiffures' => 'client_id',
        'liste_noire_clients' => 'client_id',
    ];

    /** preferences_clients est un HasOne (UNIQUE sur client_id) : merge => on supprime celui du loser. */
    private const HAS_ONE_TABLE = 'preferences_clients';

    public function handle(): int
    {
        $path = (string) $this->option('csv');
        $dryRun = (bool) $this->option('dry-run');

        if ($path === '' || ! is_file($path)) {
            $this->error('CSV introuvable. Utilise --csv=storage/logs/phone_migration_conflicts_<ts>.csv');

            return self::FAILURE;
        }

        $rows = $this->readCsv($path);
        if ($rows === null) {
            return self::FAILURE;
        }

        $stats = ['merged_into_kept' => 0, 'merged_into_other' => 0, 'kept_both' => 0, 'skipped' => 0, 'errors' => 0];

        $this->info($dryRun
            ? '[DRY-RUN] Simulation des decisions, aucune ecriture en base.'
            : 'Application reelle des decisions.'
        );

        foreach ($rows as $i => $row) {
            $line = $i + 2; // +2 = entete + index 0-based
            $decision = strtoupper(trim($row['decision'] ?? ''));

            try {
                match ($decision) {
                    'MERGE_INTO_KEPT' => $this->mergeIntoKept($row, $dryRun, $line, $stats),
                    'MERGE_INTO_OTHER' => $this->mergeIntoOther($row, $dryRun, $line, $stats),
                    'KEEP_BOTH' => $this->keepBoth($row, $line, $stats),
                    'SKIP', '' => $this->skip($row, $line, $stats),
                    default => $this->warnUnknownDecision($row, $decision, $line, $stats),
                };
            } catch (\Throwable $e) {
                $this->error("Ligne {$line} : {$e->getMessage()}");
                $stats['errors']++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Resume : merged_into_kept=%d, merged_into_other=%d, kept_both=%d, skipped=%d, errors=%d',
            $stats['merged_into_kept'],
            $stats['merged_into_other'],
            $stats['kept_both'],
            $stats['skipped'],
            $stats['errors'],
        ));

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, array<string, string>>|null
     */
    private function readCsv(string $path): ?array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("Impossible d ouvrir : {$path}");

            return null;
        }

        $header = fgetcsv($handle);
        if ($header === false || ! in_array('decision', $header, true)) {
            $this->error('CSV invalide : la colonne "decision" est requise.');
            fclose($handle);

            return null;
        }

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($header, $line);
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, int>  &$stats
     */
    private function mergeIntoKept(array $row, bool $dryRun, int $line, array &$stats): void
    {
        $keptId = (int) $row['kept_id'];
        $otherId = (int) $row['other_id'];

        if ($keptId === 0 || $otherId === 0) {
            $this->error("Ligne {$line} : MERGE_INTO_KEPT exige kept_id et other_id (lignes 'unparseable' incompatibles).");
            $stats['errors']++;

            return;
        }

        $this->line("Ligne {$line} : MERGE_INTO_KEPT (other={$otherId} => kept={$keptId})");

        if ($dryRun) {
            return;
        }

        DB::transaction(function () use ($keptId, $otherId): void {
            $this->transferForeignKeys($otherId, $keptId);
            $this->mergeHasOne($otherId, $keptId);
            DB::table('clients')->where('id', $otherId)->delete();
        });

        $stats['merged_into_kept']++;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, int>  &$stats
     */
    private function mergeIntoOther(array $row, bool $dryRun, int $line, array &$stats): void
    {
        $keptId = (int) $row['kept_id'];
        $otherId = (int) $row['other_id'];
        $e164 = (string) ($row['other_phone_normalized'] ?: $row['kept_phone']);

        if ($keptId === 0 || $otherId === 0 || $e164 === '') {
            $this->error("Ligne {$line} : MERGE_INTO_OTHER exige kept_id + other_id + un E.164.");
            $stats['errors']++;

            return;
        }

        $this->line("Ligne {$line} : MERGE_INTO_OTHER (kept={$keptId} => other={$otherId}, telephone <= {$e164})");

        if ($dryRun) {
            return;
        }

        DB::transaction(function () use ($keptId, $otherId, $e164): void {
            // Ordre crucial : on transfere d abord les FK de kept vers other,
            // puis on supprime kept (libere le E.164), puis on UPDATE other au E.164.
            // Sinon UPDATE other planterait sur la contrainte UNIQUE.
            $this->transferForeignKeys($keptId, $otherId);
            $this->mergeHasOne($keptId, $otherId);
            DB::table('clients')->where('id', $keptId)->delete();
            DB::table('clients')->where('id', $otherId)->update(['telephone' => $e164]);
        });

        $stats['merged_into_other']++;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, int>  &$stats
     */
    private function keepBoth(array $row, int $line, array &$stats): void
    {
        $this->line("Ligne {$line} : KEEP_BOTH (no-op, other reste avec son tel raw)");
        $stats['kept_both']++;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, int>  &$stats
     */
    private function skip(array $row, int $line, array &$stats): void
    {
        $this->line("Ligne {$line} : SKIP");
        $stats['skipped']++;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, int>  &$stats
     */
    private function warnUnknownDecision(array $row, string $decision, int $line, array &$stats): void
    {
        $this->warn("Ligne {$line} : decision inconnue '{$decision}', traitee comme SKIP. Valeurs valides : MERGE_INTO_KEPT, MERGE_INTO_OTHER, KEEP_BOTH, SKIP.");
        $stats['skipped']++;
    }

    private function transferForeignKeys(int $fromId, int $toId): void
    {
        foreach (self::FK_TABLES as $table => $fk) {
            DB::table($table)->where($fk, $fromId)->update([$fk => $toId]);
        }
    }

    /**
     * preferences_clients = HasOne (UNIQUE sur client_id). Si le destinataire
     * a deja une preference, on supprime celle de la source pour ne pas violer
     * la contrainte. Sinon on transfere normalement.
     */
    private function mergeHasOne(int $fromId, int $toId): void
    {
        $hasTarget = DB::table(self::HAS_ONE_TABLE)->where('client_id', $toId)->exists();

        if ($hasTarget) {
            DB::table(self::HAS_ONE_TABLE)->where('client_id', $fromId)->delete();

            return;
        }

        DB::table(self::HAS_ONE_TABLE)->where('client_id', $fromId)->update(['client_id' => $toId]);
    }
}
