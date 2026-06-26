<?php

namespace App\Services\Admin;

use App\Models\Paiement;
use App\Models\Commande;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaiementService
{
    /**
     * Créer un nouveau paiement manuel (admin uniquement)
     */
    public function createPaiement(array $data): Paiement
    {
        DB::beginTransaction();

        try {
            // Générer une référence unique
            $data['reference_paiement'] = $this->generateReference();
            $data['date_initiation'] = now();
            
            // Pour les paiements manuels admin, statut par défaut
            $data['statut'] = $this->getInitialStatus($data['methode_paiement']);

            $paiement = Paiement::create($data);

            // Validation automatique pour certains types
            if (in_array($data['methode_paiement'], ['especes', 'cheque', 'virement'])) {
                // Les paiements manuels peuvent être directement validés si confirmés
                if (isset($data['confirmer_immediatement']) && $data['confirmer_immediatement']) {
                    $this->confirmerPaiement($paiement, [
                        'message' => 'Paiement manuel confirmé par administrateur'
                    ]);
                }
            }

            DB::commit();

            Log::info('Nouveau paiement manuel créé', [
                'paiement_id' => $paiement->id,
                'reference' => $paiement->reference_paiement,
                'methode' => $paiement->methode_paiement,
                'montant' => $paiement->montant,
                'user_id' => auth()->id()
            ]);

            return $paiement->load(['commande', 'client']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur création paiement manuel', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Confirmer un paiement
     */
    public function confirmerPaiement(Paiement $paiement, array $data = []): bool
    {
        DB::beginTransaction();

        try {
            $paiement->update([
                'statut' => 'valide',
                'date_validation' => now(),
                'code_autorisation' => $data['code_autorisation'] ?? null,
                'message_retour' => $data['message'] ?? 'Paiement confirmé par administrateur',
                'donnees_api' => $data['donnees_api'] ?? $paiement->donnees_api
            ]);

            // Mettre à jour le statut de la commande
            $this->updateCommandeStatut($paiement);

            DB::commit();

            Log::info('Paiement confirmé', [
                'paiement_id' => $paiement->id,
                'reference' => $paiement->reference_paiement,
                'montant' => $paiement->montant,
                'admin_id' => auth()->id()
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur confirmation paiement', [
                'paiement_id' => $paiement->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Rejeter un paiement
     */
    public function rejeterPaiement(Paiement $paiement, string $raison): bool
    {
        try {
            $paiement->update([
                'statut' => 'echec',
                'message_retour' => $raison,
                'date_rejet' => now()
            ]);

            Log::info('Paiement rejeté', [
                'paiement_id' => $paiement->id,
                'raison' => $raison,
                'admin_id' => auth()->id()
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Erreur rejet paiement', [
                'paiement_id' => $paiement->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Rembourser un paiement
     */
    public function rembourserPaiement(Paiement $paiement, float $montant, string $motif): bool
    {
        DB::beginTransaction();

        try {
            if ($montant > ($paiement->montant - $paiement->montant_rembourse)) {
                throw new \InvalidArgumentException('Montant de remboursement supérieur au montant disponible');
            }

            $newMontantRembourse = $paiement->montant_rembourse + $montant;
            
            $paiement->update([
                'montant_rembourse' => $newMontantRembourse,
                'date_remboursement' => now(),
                'motif_remboursement' => $motif,
                'statut' => $newMontantRembourse >= $paiement->montant ? 'rembourse' : 'partiel_rembourse'
            ]);

            DB::commit();

            Log::info('Paiement remboursé', [
                'paiement_id' => $paiement->id,
                'montant_rembourse' => $montant,
                'motif' => $motif,
                'admin_id' => auth()->id()
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur remboursement', [
                'paiement_id' => $paiement->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Vérifier le statut d'un paiement externe (Wave/Orange Money)
     * Utilisé pour consulter les paiements initiés par les clients
     */
    public function verifierStatutPaiement(Paiement $paiement): string
    {
        switch ($paiement->methode_paiement) {
            case 'wave':
                return $this->verifierStatutWave($paiement);
            case 'orange_money':
                return $this->verifierStatutOrangeMoney($paiement);
            default:
                return $paiement->statut;
        }
    }

    /**
     * Obtenir les statistiques des paiements
     */
    public function getStatistics(): array
    {
        return [
            'total_paiements' => Paiement::count(),
            'paiements_valides' => Paiement::where('statut', 'valide')->count(),
            'paiements_en_attente' => Paiement::where('statut', 'en_attente')->count(),
            'paiements_echecs' => Paiement::where('statut', 'echec')->count(),
            'montant_total_valide' => Paiement::where('statut', 'valide')->sum('montant'),
            'montant_total_rembourse' => Paiement::sum('montant_rembourse'),
            'paiements_par_methode' => Paiement::select('methode_paiement', DB::raw('count(*) as total'), DB::raw('sum(montant) as montant_total'))
                ->where('statut', 'valide')
                ->groupBy('methode_paiement')
                ->get()
                ->keyBy('methode_paiement'),
            'paiements_aujourdhui' => Paiement::whereDate('created_at', today())->count(),
            'montant_aujourdhui' => Paiement::where('statut', 'valide')
                ->whereDate('created_at', today())
                ->sum('montant'),
            'paiements_ce_mois' => Paiement::whereMonth('created_at', now()->month)->count(),
            'montant_ce_mois' => Paiement::where('statut', 'valide')
                ->whereMonth('created_at', now()->month)
                ->sum('montant'),
            'paiements_manuels' => Paiement::whereIn('methode_paiement', ['especes', 'cheque', 'virement'])->count(),
            'paiements_electroniques' => Paiement::whereIn('methode_paiement', ['wave', 'orange_money', 'carte_bancaire'])->count()
        ];
    }

    // ========== MÉTHODES PRIVÉES ==========

    /**
     * Déterminer le statut initial selon la méthode
     */
    private function getInitialStatus(string $methode): string
    {
        return match($methode) {
            'especes', 'cheque', 'virement' => 'en_attente', // Nécessitent validation manuelle
            'wave', 'orange_money', 'carte_bancaire' => 'en_cours', // Paiements électroniques
            default => 'en_attente'
        };
    }

    /**
     * Générer une référence de paiement unique
     */
    private function generateReference(): string
    {
        return 'PAY-' . date('YmdHis') . '-' . strtoupper(Str::random(6));
    }

    /**
     * Mettre à jour le statut de la commande
     */
    private function updateCommandeStatut(Paiement $paiement): void
    {
        $commande = $paiement->commande;
        $totalPaye = $commande->paiements()->where('statut', 'valide')->sum('montant');

        if ($totalPaye >= $commande->montant_total) {
            $commande->update(['statut_paiement' => 'paye']);
        } elseif ($totalPaye > 0) {
            $commande->update(['statut_paiement' => 'partiel']);
        } else {
            $commande->update(['statut_paiement' => 'impaye']);
        }
    }

    /**
     * Vérifier statut Wave (consultation uniquement)
     */
    private function verifierStatutWave(Paiement $paiement): string
    {
        try {
            $response = Http::get(
                config('payment.wave.api_url') . '/v1/checkout/sessions/' . $paiement->transaction_id,
                [],
                [
                    'Authorization' => 'Bearer ' . config('payment.wave.api_key')
                ]
            );

            if ($response->successful()) {
                $data = $response->json();
                $statutWave = $data['payment_status'];

                $nouveauStatut = match($statutWave) {
                    'successful' => 'valide',
                    'pending' => 'en_cours',
                    'failed', 'cancelled' => 'echec',
                    default => $paiement->statut
                };

                if ($nouveauStatut !== $paiement->statut) {
                    $paiement->update([
                        'statut' => $nouveauStatut,
                        'donnees_api' => json_encode($data)
                    ]);

                    if ($nouveauStatut === 'valide') {
                        $paiement->update(['date_validation' => now()]);
                        $this->updateCommandeStatut($paiement);
                    }
                }

                return $nouveauStatut;
            }

            return $paiement->statut;

        } catch (\Exception $e) {
            Log::error('Erreur vérification statut Wave', [
                'paiement_id' => $paiement->id,
                'error' => $e->getMessage()
            ]);
            return $paiement->statut;
        }
    }

    /**
     * Vérifier statut Orange Money (consultation uniquement)
     */
    private function verifierStatutOrangeMoney(Paiement $paiement): string
    {
        try {
            $response = Http::get(
                config('payment.orange_money.api_url') . '/omcoreapis/1.0.2/mp/paymentstatus/' . $paiement->transaction_id,
                [],
                [
                    'Authorization' => 'Bearer ' . $this->getOrangeMoneyToken()
                ]
            );

            if ($response->successful()) {
                $data = $response->json();
                $statutOrange = $data['status'];

                $nouveauStatut = match($statutOrange) {
                    'SUCCESS' => 'valide',
                    'PENDING' => 'en_cours',
                    'FAILED', 'EXPIRED' => 'echec',
                    default => $paiement->statut
                };

                if ($nouveauStatut !== $paiement->statut) {
                    $paiement->update([
                        'statut' => $nouveauStatut,
                        'donnees_api' => json_encode($data)
                    ]);

                    if ($nouveauStatut === 'valide') {
                        $paiement->update(['date_validation' => now()]);
                        $this->updateCommandeStatut($paiement);
                    }
                }

                return $nouveauStatut;
            }

            return $paiement->statut;

        } catch (\Exception $e) {
            Log::error('Erreur vérification statut Orange Money', [
                'paiement_id' => $paiement->id,
                'error' => $e->getMessage()
            ]);
            return $paiement->statut;
        }
    }

    /**
     * Obtenir le token Orange Money
     */
    private function getOrangeMoneyToken(): string
    {
        $response = Http::post(config('payment.orange_money.auth_url'), [
            'grant_type' => 'client_credentials'
        ], [
            'Authorization' => 'Basic ' . base64_encode(
                config('payment.orange_money.client_id') . ':' . config('payment.orange_money.client_secret')
            ),
            'Content-Type' => 'application/x-www-form-urlencoded'
        ]);

        if ($response->successful()) {
            return $response->json()['access_token'];
        }

        throw new \Exception('Impossible d\'obtenir le token Orange Money');
    }
}