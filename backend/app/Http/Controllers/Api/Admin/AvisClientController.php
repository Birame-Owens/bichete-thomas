<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AvisClient;
use App\Models\Client;
use App\Models\Produit;
use App\Services\Admin\AvisClientService;
use App\Http\Requests\Admin\AvisClientRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AvisClientController extends Controller
{
    protected AvisClientService $avisService;

    public function __construct(AvisClientService $avisService)
    {
        $this->avisService = $avisService;
    }

    /**
     * Liste tous les avis avec filtres
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $filters = [
                'statut' => $request->get('statut'),
                'note_min' => $request->get('note_min'),
                'note_max' => $request->get('note_max'),
                'produit_id' => $request->get('produit_id'),
                'client_id' => $request->get('client_id'),
                'date_debut' => $request->get('date_debut'),
                'date_fin' => $request->get('date_fin'),
                'search' => $request->get('search')
            ];

            $avis = $this->avisService->getAvisWithFilters($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'avis' => $avis->map(function ($avis) {
                        return $this->formatAvisResponse($avis);
                    }),
                    'pagination' => [
                        'current_page' => $avis->currentPage(),
                        'per_page' => $avis->perPage(),
                        'total' => $avis->total(),
                        'last_page' => $avis->lastPage(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des avis', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des avis'
            ], 500);
        }
    }

    /**
     * Afficher un avis spécifique
     */
    public function show(AvisClient $avis): JsonResponse
    {
        try {
            $avis->load(['client', 'produit', 'commande']);

            return response()->json([
                'success' => true,
                'data' => [
                    'avis' => $this->formatAvisResponse($avis, true)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération de l\'avis', [
                'avis_id' => $avis->id,
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'avis'
            ], 500);
        }
    }

    /**
     * Modérer un avis (approuver/rejeter/masquer)
     */
    public function moderer(Request $request, AvisClient $avis): JsonResponse
    {
        try {
            $request->validate([
                'action' => 'required|string|in:approuver,rejeter,masquer',
                'raison' => 'nullable|string|max:500|required_if:action,rejeter'
            ]);

            $avisModere = $this->avisService->modererAvis(
                $avis, 
                $request->action, 
                $request->raison
            );

            $message = [
                'approuver' => 'Avis approuvé avec succès',
                'rejeter' => 'Avis rejeté avec succès',
                'masquer' => 'Avis masqué avec succès'
            ][$request->action];
            $this->clearPublicReviewCaches();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'avis' => $this->formatAvisResponse($avisModere->fresh())
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la modération de l\'avis', [
                'avis_id' => $avis->id,
                'action' => $request->action ?? null,
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modération de l\'avis'
            ], 500);
        }
    }

    /**
     * Répondre à un avis
     */
    public function repondre(Request $request, AvisClient $avis): JsonResponse
    {
        try {
            $request->validate([
                'reponse' => 'required|string|min:10|max:1000'
            ]);

            $avisAvecReponse = $this->avisService->repondreAvis($avis, $request->reponse);

            return response()->json([
                'success' => true,
                'message' => 'Réponse ajoutée avec succès',
                'data' => [
                    'avis' => $this->formatAvisResponse($avisAvecReponse->fresh())
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la réponse à l\'avis', [
                'avis_id' => $avis->id,
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout de la réponse'
            ], 500);
        }
    }

    /**
     * Mettre en avant / retirer de la mise en avant
     */
    public function toggleMiseEnAvant(AvisClient $avis): JsonResponse
    {
        try {
            $avisModifie = $this->avisService->toggleMiseEnAvant($avis);

            $message = $avisModifie->est_mis_en_avant 
                ? 'Avis mis en avant' 
                : 'Avis retiré de la mise en avant';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'avis' => $this->formatAvisResponse($avisModifie->fresh())
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la modification de la mise en avant', [
                'avis_id' => $avis->id,
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification de la mise en avant'
            ], 500);
        }
    }

    /**
     * Marquer comme vérifié / non vérifié
     */
    public function toggleVerifie(AvisClient $avis): JsonResponse
    {
        try {
            $avis->update(['avis_verifie' => !$avis->avis_verifie]);

            $message = $avis->avis_verifie 
                ? 'Avis marqué comme vérifié' 
                : 'Avis marqué comme non vérifié';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'avis' => $this->formatAvisResponse($avis->fresh())
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la modification de la vérification', [
                'avis_id' => $avis->id,
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification de la vérification'
            ], 500);
        }
    }

    /**
     * Supprimer un avis
     */
    public function destroy(AvisClient $avis): JsonResponse
    {
        try {
            $this->avisService->supprimerAvis($avis);

            return response()->json([
                'success' => true,
                'message' => 'Avis supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression de l\'avis', [
                'avis_id' => $avis->id,
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'avis'
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques des avis
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->avisService->getStatistiques();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des statistiques des avis', [
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques'
            ], 500);
        }
    }

    /**
     * Obtenir les avis en attente de modération
     */
    public function enAttente(): JsonResponse
    {
        try {
            $avisEnAttente = $this->avisService->getAvisEnAttente(20);

            return response()->json([
                'success' => true,
                'data' => [
                    'avis' => $avisEnAttente->map(function ($avis) {
                        return $this->formatAvisResponse($avis);
                    })
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des avis en attente', [
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des avis en attente'
            ], 500);
        }
    }

    /**
     * Obtenir les options pour les filtres
     */
    public function options(): JsonResponse
    {
        try {
            $produits = Produit::where('est_visible', true)
                ->select('id', 'nom')
                ->orderBy('nom')
                ->get();

            $clients = Client::select('id', 'nom', 'prenom')
                ->orderBy('nom')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'statuts' => [
                        ['value' => 'en_attente', 'label' => 'En attente'],
                        ['value' => 'approuve', 'label' => 'Approuvé'],
                        ['value' => 'rejete', 'label' => 'Rejeté'],
                        ['value' => 'masque', 'label' => 'Masqué']
                    ],
                    'notes' => [
                        ['value' => 1, 'label' => '1 étoile'],
                        ['value' => 2, 'label' => '2 étoiles'],
                        ['value' => 3, 'label' => '3 étoiles'],
                        ['value' => 4, 'label' => '4 étoiles'],
                        ['value' => 5, 'label' => '5 étoiles']
                    ],
                    'produits' => $produits,
                    'clients' => $clients
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des options', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des options'
            ], 500);
        }
    }

    // ========== MÉTHODES PRIVÉES ==========

    /**
     * Formater la réponse d'un avis
     */
    private function formatAvisResponse(AvisClient $avis, bool $detailed = false): array
    {
        // photos_avis est casté en array (modèle) -> ne plus json_decode (TypeError PHP 8).
        $photos = collect($avis->photos_avis ?? [])
            ->map(fn ($p) => \Illuminate\Support\Facades\Storage::disk('public')->url($p))
            ->all();
        
        $data = [
            'id' => $avis->id,
            'titre' => $avis->titre,
            'commentaire' => $avis->commentaire,
            'note_globale' => $avis->note_globale,
            'nom_affiche' => $avis->nom_affiche,
            'recommande_produit' => $avis->recommande_produit,
            'recommande_boutique' => $avis->recommande_boutique,
            'statut' => $avis->statut,
            'statut_label' => $this->getStatutLabel($avis->statut),
            'statut_color' => $this->getStatutColor($avis->statut),
            'est_visible' => $avis->est_visible,
            'est_mis_en_avant' => $avis->est_mis_en_avant,
            'avis_verifie' => $avis->avis_verifie,
            'nombre_likes' => $avis->nombre_likes,
            'nombre_dislikes' => $avis->nombre_dislikes,
            'a_photos' => !empty($photos),
            'nombre_photos' => count($photos),
            'a_reponse' => !empty($avis->reponse_boutique),
            'created_at' => $avis->created_at?->format('d/m/Y H:i'),
            'client' => $avis->client ? [
                'id' => $avis->client->id,
                'nom_complet' => $avis->client->nom . ' ' . $avis->client->prenom,
                'type_client' => $avis->client->type_client ?? 'nouveau'
            ] : null,
            'produit' => $avis->produit ? [
                'id' => $avis->produit->id,
                'nom' => $avis->produit->nom,
                'note_moyenne' => $avis->produit->note_moyenne,
                'nombre_avis' => $avis->produit->nombre_avis
            ] : null
        ];

        if ($detailed) {
            $data = array_merge($data, [
                'note_qualite' => $avis->note_qualite,
                'note_taille' => $avis->note_taille,
                'note_couleur' => $avis->note_couleur,
                'note_livraison' => $avis->note_livraison,
                'note_service' => $avis->note_service,
                'raison_rejet' => $avis->raison_rejet,
                'date_moderation' => $avis->date_moderation?->format('d/m/Y H:i'),
                'modere_par' => $avis->modere_par,
                'ordre_affichage' => $avis->ordre_affichage,
                'adresse_ip' => $avis->adresse_ip,
                'user_agent' => $avis->user_agent,
                'photos' => $photos,
                'reponse_boutique' => $avis->reponse_boutique,
                'date_reponse' => $avis->date_reponse?->format('d/m/Y H:i'),
                'repondu_par' => $avis->repondu_par,
                'commande' => $avis->commande ? [
                    'id' => $avis->commande->id,
                    'numero_commande' => $avis->commande->numero_commande,
                    'date_commande' => $avis->commande->created_at?->format('d/m/Y')
                ] : null,
                'client_detaille' => $avis->client ? [
                    'id' => $avis->client->id,
                    'nom' => $avis->client->nom,
                    'prenom' => $avis->client->prenom,
                    'email' => $avis->client->email,
                    'telephone' => $avis->client->telephone,
                    'ville' => $avis->client->ville,
                    'type_client' => $avis->client->type_client ?? 'nouveau',
                    'score_fidelite' => $avis->client->score_fidelite ?? 0,
                    'nombre_commandes' => $avis->client->nombre_commandes ?? 0,
                    'total_depense' => $avis->client->total_depense ?? 0
                ] : null
            ]);
        }

        return $data;
    }

    /**
     * Obtenir le libellé du statut
     */
    private function getStatutLabel(?string $statut): string
    {
        if ($statut === null) {
            return 'Inconnu';
        }

        $labels = [
            'en_attente' => 'En attente',
            'approuve' => 'Approuvé',
            'rejete' => 'Rejeté',
            'masque' => 'Masqué'
        ];

        return $labels[$statut] ?? $statut;
    }

    /**
     * Obtenir la couleur du statut
     */
    private function getStatutColor(?string $statut): string
    {
        $colors = [
            'en_attente' => 'bg-yellow-100 text-yellow-800',
            'approuve' => 'bg-green-100 text-green-800',
            'rejete' => 'bg-red-100 text-red-800',
            'masque' => 'bg-gray-100 text-gray-800'
        ];

        return $colors[$statut] ?? 'bg-gray-100 text-gray-800';
    }

    private function clearPublicReviewCaches(): void
    {
        Cache::forget('client_home_data');
        Cache::forget('home_page_data_' . now()->format('Y-m-d-H'));

        try {
            Cache::tags(['api_responses'])->flush();
        } catch (\Throwable $e) {
            Log::debug('Cache tags non disponible pour les avis publics', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
