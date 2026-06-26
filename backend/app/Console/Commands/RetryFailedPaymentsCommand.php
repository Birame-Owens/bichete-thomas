<?php

namespace App\Console\Commands;

use App\Models\Commande;
use App\Models\Paiement;
use App\Jobs\SendPaymentRetryEmailJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryFailedPaymentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:retry-failed 
                            {--days=7 : Nombre de jours maximum depuis l\'échec}
                            {--dry-run : Simuler sans envoyer les emails}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Relancer les paiements échoués par email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info("🔍 Recherche des commandes avec paiements échoués (derniers {$days} jours)...");

        // Récupérer toutes les commandes avec statut 'echoue' ou 'en_attente'
        // qui ont au moins un paiement échoué dans les X derniers jours
        $commandes = Commande::whereIn('statut', ['echoue', 'en_attente'])
            ->whereHas('paiements', function($q) use ($days) {
                $q->where('statut', 'echoue')
                  ->where('created_at', '>=', now()->subDays($days));
            })
            ->with(['client', 'paiements'])
            ->get();

        if ($commandes->isEmpty()) {
            $this->info('✅ Aucune commande avec paiement échoué trouvée.');
            return Command::SUCCESS;
        }

        $this->info("📊 {$commandes->count()} commande(s) trouvée(s)");

        $table = [];
        $emailsSent = 0;

        foreach ($commandes as $commande) {
            $lastFailedPayment = $commande->paiements()
                ->where('statut', 'echoue')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$lastFailedPayment) continue;

            $table[] = [
                'Numéro' => $commande->numero_commande,
                'Client' => $commande->client->prenom . ' ' . $commande->client->nom,
                'Email' => $commande->client->email,
                'Montant' => number_format($commande->montant_total, 0, ',', ' ') . ' FCFA',
                'Échec le' => $lastFailedPayment->created_at->format('d/m/Y H:i')
            ];

            if (!$dryRun) {
                // Générer un nouveau lien de paiement
                $newPaymentUrl = url('/checkout?order=' . $commande->numero_commande);

                // Dispatcher le job d'email
                SendPaymentRetryEmailJob::dispatch($commande, $newPaymentUrl)
                    ->onQueue('emails');

                Log::info('📧 Email relance dispatché', [
                    'commande_id' => $commande->id,
                    'numero' => $commande->numero_commande
                ]);

                $emailsSent++;
            }
        }

        $this->table(
            ['Numéro', 'Client', 'Email', 'Montant', 'Échec le'],
            $table
        );

        if ($dryRun) {
            $this->warn("🔸 MODE DRY-RUN : Aucun email envoyé");
            $this->info("   Pour envoyer les emails, exécutez sans --dry-run");
        } else {
            $this->info("✅ {$emailsSent} email(s) de relance dispatché(s) dans la queue");
            $this->comment("   N'oubliez pas de lancer le queue worker : php artisan queue:work");
        }

        return Command::SUCCESS;
    }
}
