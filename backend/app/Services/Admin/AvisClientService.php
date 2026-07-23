<?php

namespace App\Services\Admin;

use App\Models\AvisClient;
use App\Models\Client;
use App\Models\Produit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

use Carbon\Carbon;


class AvisClientService
{
    /**
     * Obtenir les avis avec filtres et pagination
     */
    public function getAvisWithFilters(array $filters = [], int $perPage = 15): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = AvisClient::with(['client', 'produit', 'commande'])
            ->orderBy('created_at', 'desc');

        // Filtrer par statut
        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        // Filtrer par note
        if (!empty($filters['note_min'])) {
            $query->where('note_globale', '>=', $filters['note_min']);
        }
        if (!empty($filters['note_max'])) {
            $query->where('note_globale', '<=', $filters['note_max']);
        }

        // Filtrer par produit
        if (!empty($filters['produit_id'])) {
            $query->where('produit_id', $filters['produit_id']);
        }

        // Filtrer par client
        if (!empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        // Filtrer par date
        if (!empty($filters['date_debut'])) {
            $query->whereDate('created_at', '>=', $filters['date_debut']);
        }
        if (!empty($filters['date_fin'])) {
            $query->whereDate('created_at', '<=', $filters['date_fin']);
        }

        // Recherche textuelle
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('commentaire', 'ILIKE', "%{$search}%")
                  ->orWhere('titre', 'ILIKE', "%{$search}%")
                  ->orWhere('nom_affiche', 'ILIKE', "%{$search}%")
                  ->orWhereHas('client', function ($q) use ($search) {
                      $q->where('nom', 'ILIKE', "%{$search}%")
                        ->orWhere('prenom', 'ILIKE', "%{$search}%");
                  })
                  ->orWhereHas('produit', function ($q) use ($search) {
                      $q->where('nom', 'ILIKE', "%{$search}%");
                  });
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Modérer un avis (approuver ou rejeter)
     */
    public function modererAvis(AvisClient $avis, string $action, string $raison = null): AvisClient
    {
        DB::beginTransaction();

        try {
            $statutMap = [
                'approuver' => 'approuve',
                'rejeter' => 'rejete',
                'masquer' => 'masque'
            ];

            if (!isset($statutMap[$action])) {
                throw new \InvalidArgumentException('Action de modération invalide');
            }

            $nouveauStatut = $statutMap[$action];

            $avis->update([
                'statut' => $nouveauStatut,
                'est_visible' => $nouveauStatut === 'approuve',
                'raison_rejet' => $action === 'rejeter' ? $raison : null,
                'date_moderation' => now(),
                'modere_par' => auth()->user()->name ?? 'Admin'
            ]);

            // Mettre à jour les statistiques du produit si approuvé
            if ($nouveauStatut === 'approuve') {
                $this->updateProductStats($avis->produit_id);
            }
            $this->clearPublicReviewCaches();

            DB::commit();

            Log::info('Avis modéré', [
                'avis_id' => $avis->id,
                'action' => $action,
                'nouveau_statut' => $nouveauStatut,
                'admin_id' => auth()->id()
            ]);

            return $avis;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur modération avis', [
                'avis_id' => $avis->id,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Vider les caches publics liés aux avis (home/testimonials + réponses API).
     * Appelée après modération pour que l'avis approuvé apparaisse côté client.
     */
    private function clearPublicReviewCaches(): void
    {
        Cache::forget('client_home_data');
        Cache::forget('home_page_data_' . now()->format('Y-m-d-H'));

        try {
            Cache::tags(['api_responses'])->flush();
        } catch (\Throwable $e) {
            Log::debug('Cache tags non disponible (avis publics)', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Répondre à un avis
     */
    public function repondreAvis(AvisClient $avis, string $reponse): AvisClient
    {
        $avis->update([
            'reponse_boutique' => $reponse,
            'date_reponse' => now(),
            'repondu_par' => auth()->user()->name ?? 'Admin'
        ]);

        Log::info('Réponse ajoutée à un avis', [
            'avis_id' => $avis->id,
            'admin_id' => auth()->id()
        ]);

        return $avis;
    }

    /**
     * Mettre en avant un avis
     */
    public function toggleMiseEnAvant(AvisClient $avis): AvisClient
    {
        $nouveauStatut = !$avis->est_mis_en_avant;
        
        $avis->update([
            'est_mis_en_avant' => $nouveauStatut,
            'ordre_affichage' => $nouveauStatut ? 1 : 0
        ]);

        Log::info('Avis mise en avant modifiée', [
            'avis_id' => $avis->id,
            'mis_en_avant' => $nouveauStatut,
            'admin_id' => auth()->id()
        ]);

        return $avis;
    }

    /**
     * Supprimer un avis
     */
    public function supprimerAvis(AvisClient $avis): bool
    {
        try {
            // Supprimer les photos s'il y en a
            if ($avis->photos_avis) {
                $photos = $avis->photos_avis; // déjà un array (cast modèle)
                if (is_array($photos)) {
                    foreach ($photos as $photo) {
                        Storage::disk('public')->delete($photo);
                    }
                }
            }

            $produitId = $avis->produit_id;
            $avis->delete();

            // Mettre à jour les statistiques du produit (si l'avis y est rattaché)
            if ($produitId) {
                $this->updateProductStats($produitId);
            }

            Log::info('Avis supprimé', [
                'avis_id' => $avis->id,
                'admin_id' => auth()->id()
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Erreur suppression avis', [
                'avis_id' => $avis->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtenir les statistiques des avis
     */
 public function getStatistiques(): array
{
    return [
        'total_avis' => AvisClient::count(),
        'avis_en_attente' => AvisClient::where('statut', 'en_attente')->count(),
        'avis_approuves' => AvisClient::where('statut', 'approuve')->count(),
        'avis_rejetes' => AvisClient::where('statut', 'rejete')->count(),
        'note_moyenne_globale' => round(AvisClient::where('statut', 'approuve')->avg('note_globale') ?? 0, 1),
        'avis_avec_photos' => AvisClient::whereNotNull('photos_avis')
            ->whereRaw("photos_avis::text != '[]'")
            ->whereRaw("photos_avis::text != 'null'")
            ->count(),
        'avis_recommandent_produit' => AvisClient::where('statut', 'approuve')
            ->where('recommande_produit', true)->count(),
        'avis_recommandent_boutique' => AvisClient::where('statut', 'approuve')
            ->where('recommande_boutique', true)->count(),
        'avis_par_note' => AvisClient::where('statut', 'approuve')
            ->select('note_globale', DB::raw('count(*) as total'))
            ->groupBy('note_globale')
            ->orderBy('note_globale', 'desc')
            ->get()
            ->keyBy('note_globale'),
        'avis_recents' => AvisClient::with(['client', 'produit'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get(),
        'produits_les_mieux_notes' => $this->getProduitsLesMieuxNotes(),
        'clients_plus_actifs' => $this->getClientsPlusActifs()
    ];
}

    /**
     * Obtenir les avis en attente de modération
     */
    public function getAvisEnAttente(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return AvisClient::with(['client', 'produit'])
            ->where('statut', 'en_attente')
            ->orderBy('created_at', 'asc')
            ->take($limit)
            ->get();
    }

    /**
     * Marquer un avis comme vérifié
     */
    public function marquerVerifie(AvisClient $avis): AvisClient
    {
        $avis->update(['avis_verifie' => true]);

        Log::info('Avis marqué comme vérifié', [
            'avis_id' => $avis->id,
            'admin_id' => auth()->id()
        ]);

        return $avis;
    }

    // ========== MÉTHODES PRIVÉES ==========

    /**
     * Mettre à jour les statistiques du produit
     */
    private function updateProductStats(int $produitId): void
    {
        $avisApprouves = AvisClient::where('produit_id', $produitId)
            ->where('statut', 'approuve')
            ->get();

        $nombreAvis = $avisApprouves->count();
        $noteMoyenne = $nombreAvis > 0 ? $avisApprouves->avg('note_globale') : 0;

        Produit::where('id', $produitId)->update([
            'nombre_avis' => $nombreAvis,
            'note_moyenne' => round($noteMoyenne, 1)
        ]);
    }

    /**
     * Obtenir les produits les mieux notés
     */
    private function getProduitsLesMieuxNotes(int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return Produit::where('nombre_avis', '>', 0)
            ->orderBy('note_moyenne', 'desc')
            ->orderBy('nombre_avis', 'desc')
            ->take($limit)
            ->get(['id', 'nom', 'note_moyenne', 'nombre_avis']);
    }

    /**
     * Obtenir les clients les plus actifs en avis
     */
    /**
 * Obtenir les clients les plus actifs en avis
 */
   /**
 * Obtenir les clients les plus actifs en avis
 */
private function getClientsPlusActifs(int $limit = 5): \Illuminate\Database\Eloquent\Collection
{
    return Client::whereHas('avis_clients', function ($query) {
            $query->where('statut', 'approuve');
        })
        ->withCount(['avis_clients as avis_clients_count' => function ($query) {
            $query->where('statut', 'approuve');
        }])
        ->orderBy('avis_clients_count', 'desc')
        ->take($limit)
        ->get(['id', 'nom', 'prenom']);
}
}
