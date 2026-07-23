<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\Category;
use App\Models\Produit;
use App\Services\Admin\PromotionService;
use App\Http\Requests\Admin\PromotionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PromotionController extends Controller
{
    protected PromotionService $promotionService;

    public function __construct(PromotionService $promotionService)
    {
        $this->promotionService = $promotionService;
    }

    /**a
     * Liste toutes les promotions
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');
            $statut = $request->get('statut');
            $type = $request->get('type');
            $dateDebut = $request->get('date_debut');
            $dateFin = $request->get('date_fin');
            $sort = $request->get('sort', 'created_at');
            $direction = $request->get('direction', 'desc');

            $query = Promotion::query();

            // Recherche
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nom', 'ILIKE', "%{$search}%")
                      ->orWhere('code', 'ILIKE', "%{$search}%")
                      ->orWhere('description', 'ILIKE', "%{$search}%");
                });
            }

            // Filtrer par statut
            switch ($statut) {
                case 'active':
                    $query->where('est_active', true)
                          ->where('date_debut', '<=', now())
                          ->where('date_fin', '>=', now());
                    break;
                case 'inactive':
                    $query->where('est_active', false);
                    break;
                case 'expiree':
                    $query->where('date_fin', '<', now());
                    break;
                case 'future':
                    $query->where('date_debut', '>', now());
                    break;
            }

            // Filtrer par type
            if ($type) {
                $query->where('type_promotion', $type);
            }

            // Filtrer par période de création
            if ($dateDebut) {
                $query->whereDate('created_at', '>=', $dateDebut);
            }
            if ($dateFin) {
                $query->whereDate('created_at', '<=', $dateFin);
            }

            // Tri
            $allowedSorts = ['nom', 'code', 'type_promotion', 'valeur', 'date_debut', 'date_fin', 'nombre_utilisations', 'created_at'];
            if (in_array($sort, $allowedSorts)) {
                $query->orderBy($sort, $direction);
            }

            $promotions = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'promotions' => $promotions->map(function ($promotion) {
                        return $this->formatPromotionResponse($promotion);
                    }),
                    'pagination' => [
                        'current_page' => $promotions->currentPage(),
                        'per_page' => $promotions->perPage(),
                        'total' => $promotions->total(),
                        'last_page' => $promotions->lastPage(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des promotions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des promotions'
            ], 500);
        }
    }

    /**
     * Créer une nouvelle promotion
     */
    public function store(PromotionRequest $request): JsonResponse
    {
        try {
            $promotion = $this->promotionService->createPromotion($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Promotion créée avec succès',
                'data' => [
                    'promotion' => $this->formatPromotionResponse($promotion, true)
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la création de la promotion', [
                'error' => $e->getMessage(),
                'data' => $request->validated(),
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher une promotion spécifique
     */
    public function show(Promotion $promotion): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'promotion' => $this->formatPromotionResponse($promotion, true)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération de la promotion', [
                'promotion_id' => $promotion->id,
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la promotion'
            ], 500);
        }
    }

    /**
     * Mettre à jour une promotion
     */
    public function update(PromotionRequest $request, Promotion $promotion): JsonResponse
    {
        try {
            $updatedPromotion = $this->promotionService->updatePromotion($promotion, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Promotion mise à jour avec succès',
                'data' => [
                    'promotion' => $this->formatPromotionResponse($updatedPromotion, true)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour de la promotion', [
                'promotion_id' => $promotion->id,
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer une promotion
     */
    public function destroy(Promotion $promotion): JsonResponse
    {
        try {
            $this->promotionService->deletePromotion($promotion);

            return response()->json([
                'success' => true,
                'message' => 'Promotion supprimée avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression de la promotion', [
                'promotion_id' => $promotion->id,
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activer/Désactiver une promotion
     */
    public function toggleStatus(Promotion $promotion): JsonResponse
    {
        try {
            $newStatus = $this->promotionService->toggleStatus($promotion);

            return response()->json([
                'success' => true,
                'message' => $newStatus ? 'Promotion activée' : 'Promotion désactivée',
                'data' => [
                    'promotion' => $this->formatPromotionResponse($promotion->fresh())
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors du changement de statut', [
                'promotion_id' => $promotion->id,
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de statut'
            ], 500);
        }
    }

    /**
     * Dupliquer une promotion
     */
    public function duplicate(Promotion $promotion): JsonResponse
    {
        try {
            $data = $promotion->toArray();
            
            // Modifier les données pour la duplication
            $data['nom'] = $data['nom'] . ' (Copie)';
            $data['code'] = null; // Sera généré automatiquement
            $data['est_active'] = false; // Désactivé par défaut
            $data['nombre_utilisations'] = 0;
            $data['chiffre_affaires_genere'] = 0;
            $data['nombre_commandes'] = 0;
            
            // Ajuster les dates
            $data['date_debut'] = now()->addDay()->format('Y-m-d');
            $data['date_fin'] = now()->addMonth()->format('Y-m-d');

            unset($data['id'], $data['created_at'], $data['updated_at'], $data['deleted_at']);

            $newPromotion = $this->promotionService->createPromotion($data);

            return response()->json([
                'success' => true,
                'message' => 'Promotion dupliquée avec succès',
                'data' => [
                    'promotion' => $this->formatPromotionResponse($newPromotion, true)
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la duplication', [
                'promotion_id' => $promotion->id,
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la duplication'
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques des promotions
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->promotionService->getStatistics();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des statistiques', [
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
     * Valider un code promo
     */
    public function validateCode(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'code' => 'required|string',
                'montant_commande' => 'required|numeric|min:0'
            ]);

            $promotion = Promotion::where('code', $request->code)
                ->where('est_active', true)
                ->where('date_debut', '<=', now())
                ->where('date_fin', '>=', now())
                ->first();

            if (!$promotion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Code promo invalide ou expiré'
                ], 404);
            }

            // Vérifier le montant minimum
            if ($promotion->montant_minimum && $request->montant_commande < $promotion->montant_minimum) {
                return response()->json([
                    'success' => false,
                    'message' => "Montant minimum requis : " . number_format($promotion->montant_minimum, 0, ',', ' ') . " FCFA"
                ], 400);
            }

            // Calculer la réduction potentielle
            $reduction = $this->calculerReductionPotentielle($promotion, $request->montant_commande);

            return response()->json([
                'success' => true,
                'data' => [
                    'promotion' => $this->formatPromotionResponse($promotion),
                    'reduction' => $reduction,
                    'nouveau_total' => $request->montant_commande - $reduction
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur validation code promo', [
                'error' => $e->getMessage(),
                'code' => $request->code ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la validation du code'
            ], 500);
        }
    }

    /**
     * Obtenir les options pour les formulaires
     */
    /**
 * Obtenir les options pour les formulaires
 */

/**
 * Obtenir les options pour les formulaires
 */
/**
 * Obtenir les options pour les formulaires
 */
public function options(): JsonResponse
{
    try {
        $categories = [];
        $produits = [];
        
        // Charger les catégories
        try {
            if (class_exists('\App\Models\Category')) {
                $categories = \App\Models\Category::where('est_active', true)
                    ->select('id', 'nom')
                    ->get();
            }
        } catch (\Exception $e) {
            Log::warning('Impossible de charger les catégories', ['error' => $e->getMessage()]);
        }

        // Charger les produits
        try {
            if (class_exists('\App\Models\Produit')) {
                $produits = \App\Models\Produit::where('est_visible', true)
                    ->select('id', 'nom', 'prix')
                    ->get();
            }
        } catch (\Exception $e) {
            Log::warning('Impossible de charger les produits', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'types_promotion' => [
                    ['value' => 'pourcentage', 'label' => 'Pourcentage (%)'],
                    ['value' => 'montant_fixe', 'label' => 'Montant fixe (FCFA)'],
                    ['value' => 'livraison_gratuite', 'label' => 'Livraison gratuite']
                ],
                'cibles_client' => [
                    ['value' => 'tous', 'label' => 'Tous les clients'],
                    ['value' => 'nouveaux', 'label' => 'Nouveaux clients'],
                    ['value' => 'vip', 'label' => 'Clients VIP'],
                    ['value' => 'reguliers', 'label' => 'Clients réguliers']
                ],
                'jours_semaine' => [
                    ['value' => 1, 'label' => 'Lundi'],
                    ['value' => 2, 'label' => 'Mardi'],
                    ['value' => 3, 'label' => 'Mercredi'],
                    ['value' => 4, 'label' => 'Jeudi'],
                    ['value' => 5, 'label' => 'Vendredi'],
                    ['value' => 6, 'label' => 'Samedi'],
                    ['value' => 0, 'label' => 'Dimanche']
                ],
                'couleurs' => [
                    ['value' => '#ef4444', 'label' => 'Rouge'],
                    ['value' => '#f97316', 'label' => 'Orange'],
                    ['value' => '#eab308', 'label' => 'Jaune'],
                    ['value' => '#22c55e', 'label' => 'Vert'],
                    ['value' => '#3b82f6', 'label' => 'Bleu'],
                    ['value' => '#8b5cf6', 'label' => 'Violet'],
                    ['value' => '#ec4899', 'label' => 'Rose']
                ],
                'categories' => $categories,
                'produits' => $produits
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Erreur récupération options', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des options'
        ], 500);
    }
}

    // ========== MÉTHODES PRIVÉES ==========

    /**
     * Formater la réponse d'une promotion
     */
    private function formatPromotionResponse(Promotion $promotion, bool $detailed = false): array
    {
        $now = now();
        $isActive = $promotion->est_active && 
                   $promotion->date_debut <= $now && 
                   $promotion->date_fin >= $now;

        $data = [
            'id' => $promotion->id,
            'nom' => $promotion->nom,
            'code' => $promotion->code,
            'description' => $promotion->description,
            'type_promotion' => $promotion->type_promotion,
            'type_label' => $this->getTypeLabel($promotion->type_promotion),
            'valeur' => $promotion->valeur,
            'valeur_formatted' => $this->formatValeur($promotion),
            'image' => $promotion->image ? asset('storage/' . $promotion->image) : null,
            'est_active' => $promotion->est_active,
            'is_current_active' => $isActive,
            'statut' => $this->getStatut($promotion),
            'statut_label' => $this->getStatutLabel($promotion),
            'date_debut' => $promotion->date_debut->format('d/m/Y'),
            'date_fin' => $promotion->date_fin->format('d/m/Y'),
            'nombre_utilisations' => $promotion->nombre_utilisations,
            'chiffre_affaires_genere' => $promotion->chiffre_affaires_genere,
            'nombre_commandes' => $promotion->nombre_commandes,
            'jours_restants' => max(0, $promotion->date_fin->diffInDays($now, false)),
            'created_at' => $promotion->created_at->format('d/m/Y H:i'),
            // Champs utiles pour la liste
            'cible_client' => $promotion->cible_client,
            'montant_minimum' => $promotion->montant_minimum,
            'utilisation_maximum' => $promotion->utilisation_maximum,
            'utilisation_par_client' => $promotion->utilisation_par_client,
            'jours_semaine_valides' => $promotion->jours_semaine_valides ?
                json_decode($promotion->jours_semaine_valides, true) : null,
            'categories_eligibles' => $promotion->categories_eligibles ?
                json_decode($promotion->categories_eligibles, true) : null,
            'produits_eligibles' => $promotion->produits_eligibles ?
                json_decode($promotion->produits_eligibles, true) : null,
        ];

        if ($detailed) {
            $data = array_merge($data, [
                'reduction_maximum' => $promotion->reduction_maximum,
                'cumul_avec_autres' => $promotion->cumul_avec_autres,
                'premiere_commande_seulement' => $promotion->premiere_commande_seulement,
                'afficher_site' => $promotion->afficher_site,
                'envoyer_whatsapp' => $promotion->envoyer_whatsapp,
                'envoyer_email' => $promotion->envoyer_email,
                'couleur_affichage' => $promotion->couleur_affichage,
                'date_debut_iso' => $promotion->date_debut->toISOString(),
                'date_fin_iso' => $promotion->date_fin->toISOString(),
                'taux_utilisation' => $promotion->utilisation_maximum ?
                    round($promotion->nombre_utilisations / $promotion->utilisation_maximum * 100, 1) : null
            ]);
        }

        return $data;
    }

    /**
     * Obtenir le libellé du type
     */
    private function getTypeLabel(string $type): string
    {
        $labels = [
            'pourcentage' => 'Pourcentage',
            'montant_fixe' => 'Montant fixe',
            'livraison_gratuite' => 'Livraison gratuite'
        ];

        return $labels[$type] ?? $type;
    }

    /**
     * Formater la valeur selon le type
     */
    private function formatValeur(Promotion $promotion): string
    {
        switch ($promotion->type_promotion) {
            case 'pourcentage':
                return $promotion->valeur . '%';
            case 'montant_fixe':
                return number_format($promotion->valeur, 0, ',', ' ') . ' FCFA';
            case 'livraison_gratuite':
                return 'Livraison gratuite';
            default:
                return (string) $promotion->valeur;
        }
    }

    /**
     * Obtenir le statut actuel
     */
    private function getStatut(Promotion $promotion): string
    {
        $now = now();
        
        if (!$promotion->est_active) {
            return 'inactive';
        }
        
        if ($promotion->date_debut > $now) {
            return 'future';
        }
        
        if ($promotion->date_fin < $now) {
            return 'expiree';
        }
        
        if ($promotion->utilisation_maximum && 
            $promotion->nombre_utilisations >= $promotion->utilisation_maximum) {
            return 'epuisee';
        }
        
        return 'active';
    }

    /**
     * Obtenir le libellé du statut
     */
    private function getStatutLabel(Promotion $promotion): string
    {
        $statut = $this->getStatut($promotion);
        
        $labels = [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'future' => 'Programmée',
            'expiree' => 'Expirée',
            'epuisee' => 'Épuisée'
        ];

        return $labels[$statut] ?? 'Inconnue';
    }

    /**
     * Calculer la réduction potentielle
     */
    private function calculerReductionPotentielle(Promotion $promotion, float $montant): float
    {
        $reduction = 0;

        switch ($promotion->type_promotion) {
            case 'pourcentage':
                $reduction = ($montant * $promotion->valeur) / 100;
                break;
            
            case 'montant_fixe':
                $reduction = min($promotion->valeur, $montant);
                break;
            
            case 'livraison_gratuite':
                // Supposons des frais de livraison standard
                $reduction = 2000; // 2000 FCFA par exemple
                break;
        }

        // Appliquer la réduction maximum si définie
        if ($promotion->reduction_maximum) {
            $reduction = min($reduction, $promotion->reduction_maximum);
        }

        return $reduction;
    }
}