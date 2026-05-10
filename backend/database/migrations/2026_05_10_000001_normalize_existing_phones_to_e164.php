<?php

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Normalisation des telephones clients en E.164 (Phase 5 etape 1).
 *
 * But : aligner les telephones existants sur le format E.164 que tous les
 * nouveaux flux (lookup, findOrCreate, validation Phone) utilisent. Sans ca,
 * un client legacy stocke "+221 77 ..." ne serait jamais retrouve via lookup.
 *
 * Strategie - lossless et idempotente :
 *  - parse chaque tel via PhoneNumber::normalize ;
 *  - si tel deja E.164 (normalize == raw) : skip silencieux ;
 *  - si tel non parsable : ligne CSV "unparseable", tel laisse tel quel ;
 *  - si tel normalise libre en base : UPDATE ;
 *  - si UPDATE echoue par UNIQUE collision : ligne CSV "unique_collision",
 *    tel laisse tel quel - la gerante decide via clients:reconcile-phones.
 *
 * Re-run safe : sur les enregistrements deja E.164 (ceux normalises au run
 * precedent), normalize() retourne la meme chose donc on skip. Les conflits
 * non resolus restent dans leur CSV jusqu a action manuelle.
 *
 * Le CSV horodate va dans storage/logs/phone_migration_conflicts_<timestamp>.csv.
 * Si aucun conflit/unparseable, le CSV est supprime en fin de run pour ne pas
 * encombrer.
 */
return new class extends Migration
{
    public function up(): void
    {
        $timestamp = now()->format('Y-m-d_His');
        $csvPath = storage_path("logs/phone_migration_conflicts_{$timestamp}.csv");

        if (! is_dir(dirname($csvPath))) {
            mkdir(dirname($csvPath), 0755, true);
        }

        $handle = fopen($csvPath, 'w');
        fputcsv($handle, [
            'conflict_type',
            'decision',
            'kept_id',
            'kept_phone',
            'kept_name',
            'kept_reservations_count',
            'kept_last_activity',
            'other_id',
            'other_phone_raw',
            'other_phone_normalized',
            'other_name',
            'other_reservations_count',
            'other_last_activity',
        ]);

        $stats = ['normalized' => 0, 'already_e164' => 0, 'unparseable' => 0, 'collisions' => 0];

        DB::table('clients')
            ->orderBy('id')
            ->chunkById(200, function ($clients) use ($handle, &$stats): void {
                foreach ($clients as $c) {
                    $normalized = PhoneNumber::normalize($c->telephone);

                    if ($normalized === null) {
                        fputcsv($handle, [
                            'unparseable',
                            '',
                            '', '', '', '', '',
                            $c->id,
                            $c->telephone,
                            '',
                            trim("{$c->prenom} {$c->nom}"),
                            $this->countReservations($c->id),
                            $this->lastActivity($c->id),
                        ]);
                        $stats['unparseable']++;

                        continue;
                    }

                    if ($normalized === $c->telephone) {
                        $stats['already_e164']++;

                        continue;
                    }

                    try {
                        DB::table('clients')->where('id', $c->id)->update(['telephone' => $normalized]);
                        $stats['normalized']++;
                    } catch (QueryException $e) {
                        // On filtre uniquement les violations UNIQUE (toutes drivers : pgsql 23505,
                        // sqlite "UNIQUE constraint", mysql "Duplicate entry"). Sinon on remonte.
                        $msg = strtolower($e->getMessage());
                        if (! str_contains($msg, 'unique') && ! str_contains($msg, 'duplicate')) {
                            throw $e;
                        }

                        $other = DB::table('clients')->where('telephone', $normalized)->first();
                        fputcsv($handle, [
                            'unique_collision',
                            '',
                            $other->id,
                            $other->telephone,
                            trim("{$other->prenom} {$other->nom}"),
                            $this->countReservations($other->id),
                            $this->lastActivity($other->id),
                            $c->id,
                            $c->telephone,
                            $normalized,
                            trim("{$c->prenom} {$c->nom}"),
                            $this->countReservations($c->id),
                            $this->lastActivity($c->id),
                        ]);
                        $stats['collisions']++;
                    }
                }
            });

        fclose($handle);

        $hasConflicts = ($stats['unparseable'] + $stats['collisions']) > 0;
        if (! $hasConflicts) {
            // Ne pas encombrer storage/logs avec un CSV vide a chaque run.
            @unlink($csvPath);
        }

        $this->logSummary($stats, $hasConflicts ? $csvPath : null);
    }

    public function down(): void
    {
        // Pas de rollback automatique : la migration ne perd aucune donnee
        // (skip + CSV en cas de conflit). Repasser au format raw casserait le
        // lookup et la validation. Si on doit re-importer, on le fait a la main
        // a partir d un dump.
    }

    private function countReservations(int $clientId): int
    {
        return DB::table('reservations')->where('client_id', $clientId)->count();
    }

    private function lastActivity(int $clientId): ?string
    {
        $latest = DB::table('reservations')
            ->where('client_id', $clientId)
            ->latest('created_at')
            ->value('created_at');

        return $latest ? (string) $latest : null;
    }

    private function logSummary(array $stats, ?string $csvPath): void
    {
        $summary = sprintf(
            '[phone migration] normalized=%d, already_e164=%d, unparseable=%d, collisions=%d',
            $stats['normalized'],
            $stats['already_e164'],
            $stats['unparseable'],
            $stats['collisions'],
        );

        if (app()->runningInConsole()) {
            echo $summary."\n";
            if ($csvPath !== null) {
                echo "[phone migration] CSV: {$csvPath}\n";
                echo "[phone migration] Run: php artisan clients:reconcile-phones --csv=\"{$csvPath}\" --dry-run\n";
            }
        }
    }
};
