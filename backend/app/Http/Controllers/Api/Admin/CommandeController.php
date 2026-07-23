<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\Client;
use App\Models\Produit;
use App\Http\Requests\Admin\CommandeRequest;
use App\Services\Admin\CommandeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CommandeController extends Controller
{
    protected CommandeService $commandeService;

    public function __construct(CommandeService $commandeService)
    {
        $this->commandeService = $commandeService;
    }

    /**
     * Liste des commandes avec recherche et filtres
     */
    public function index(Request $request): JsonResponse
{
    try {
        $perPage = $request->get('per_page', 15);

        $filters = [
            'numero_commande' => $request->get('numero_commande'),
            'client_search'   => $request->get('client_search'),
            'produit_search'  => $request->get('produit_search'),
            'statut'          => $request->get('statut'),
            'date_debut'      => $request->get('date_debut'),
            'date_fin'        => $request->get('date_fin'),
            'priorite'        => $request->get('priorite'),
        ];

        $commandes = $this->commandeService->searchCommandes($filters)->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'commandes' => $commandes->map(fn ($c) => $this->formatCommandeList($c)),
                'pagination' => [
                    'current_page' => $commandes->currentPage(),
                    'per_page'     => $commandes->perPage(),
                    'total'        => $commandes->total(),
                    'last_page'    => $commandes->lastPage(),
                ],
            ],
        ]);

    } catch (\Exception $e) {
        Log::error('Erreur lors de la récupération des commandes', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des commandes',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Créer une nouvelle commande
     */
    public function store(CommandeRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $commande = $this->commandeService->createCommande($request->validated());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Commande créée avec succès',
                'data' => [
                    'commande' => $this->formatCommandeDetail($commande)
                ]
            ], 201);

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            
            Log::error('Erreur création commande', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la commande',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher les détails d'une commande
     */
    public function show(Commande $commande): JsonResponse
    {
        try {
            $commande->load([
                'client.mesures',
                'articles_commandes.produit.category',
                'articles_commandes.produit.images_produits', // Charger les images
                'paiements' => function($query) {
                    $query->where('statut', 'valide');
                }
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'commande' => $this->formatCommandeDetail($commande)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération de la commande', [
                'commande_id' => $commande->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la commande'
            ], 500);
        }
    }

    /**
     * Mettre à jour une commande
     */
    public function update(CommandeRequest $request, Commande $commande): JsonResponse
    {
        try {
            DB::beginTransaction();

            $updatedCommande = $this->commandeService->updateCommande($commande, $request->validated());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Commande modifiée avec succès',
                'data' => [
                    'commande' => $this->formatCommandeDetail($updatedCommande)
                ]
            ]);

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            
            Log::error('Erreur mise à jour commande', [
                'commande_id' => $commande->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Supprimer une commande
     */
    public function destroy(Commande $commande): JsonResponse
    {
        try {
            $this->commandeService->deleteCommande($commande);

            return response()->json([
                'success' => true,
                'message' => 'Commande supprimée avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur suppression commande', [
                'commande_id' => $commande->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Obtenir les clients avec leurs mesures
     */
    public function getClientsWithMesures(Request $request): JsonResponse
    {
        try {
            $search = trim((string) $request->get('q', ''));
            $limit = min(max((int) $request->get('limit', 15), 5), 30);

            $clients = Client::with('mesures')
                ->select(
                    'id', 
                    'nom', 
                    'prenom', 
                    'telephone', 
                    'email',
                    'adresse_principale',
                    'quartier',
                    'ville',
                    'indications_livraison'
                )
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('nom', 'ILIKE', "%{$search}%")
                            ->orWhere('prenom', 'ILIKE', "%{$search}%")
                            ->orWhere('telephone', 'ILIKE', "%{$search}%")
                            ->orWhere('email', 'ILIKE', "%{$search}%");
                    });
                })
                ->orderBy('nom')
                ->limit($limit)
                ->get()
                ->map(function ($client) {
                    return [
                        'id' => $client->id,
                        'nom_complet' => $client->nom . ' ' . $client->prenom,
                        'telephone' => $client->telephone,
                        'email' => $client->email,
                        'adresse_principale' => $client->adresse_principale,
                        'quartier' => $client->quartier,
                        'ville' => $client->ville,
                        'indications_livraison' => $client->indications_livraison,
                        'a_mesures' => $client->mesures !== null,
                        'mesures' => $client->mesures ? $client->mesures->getMesuresRemplies() : null
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => ['clients' => $clients]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur récupération clients avec mesures', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des clients'
            ], 500);
        }
    }

    /**
     * Obtenir les produits actifs
     */
    public function getProduits(Request $request): JsonResponse
    {
        try {
            $search = trim((string) $request->get('q', ''));
            $limit = min(max((int) $request->get('limit', 15), 5), 30);

            $produits = Produit::with('category')
                ->where('est_visible', true)
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('nom', 'ILIKE', "%{$search}%")
                            ->orWhere('slug', 'ILIKE', "%{$search}%")
                            ->orWhere('description_courte', 'ILIKE', "%{$search}%");
                    });
                })
                ->orderBy('nom')
                ->limit($limit)
                ->get()
                ->map(function ($produit) {
                    return [
                        'id' => $produit->id,
                        'nom' => $produit->nom,
                        'prix' => $produit->prix,
                        'prix_promo' => $produit->prix_promo,
                        'stock_disponible' => $produit->stock_disponible,
                        'gestion_stock' => $produit->gestion_stock,
                        'fait_sur_mesure' => $produit->fait_sur_mesure,
                        'tailles_disponibles' => $produit->tailles_disponibles
                            ? json_decode($produit->tailles_disponibles, true) : [],
                        'couleurs_disponibles' => $produit->couleurs_disponibles
                            ? json_decode($produit->couleurs_disponibles, true) : [],
                        'couleur_tailles' => $produit->couleur_tailles
                            ? json_decode($produit->couleur_tailles, true) : null,
                        'couleur_tailles_stock' => $produit->couleur_tailles_stock
                            ? json_decode($produit->couleur_tailles_stock, true) : null,
                        'categorie' => $produit->category ? $produit->category->nom : null,
                        'image' => $produit->image // Utilise l'accessor
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => ['produits' => $produits]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur récupération produits', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des produits'
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques des commandes
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $stats = $this->commandeService->getStatistics();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des statistiques', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques'
            ], 500);
        }
    }

    /**
     * Mettre à jour le statut d'une commande
     */
    public function updateStatus(Request $request, Commande $commande): JsonResponse
    {
        try {
            $validated = $request->validate([
                'statut' => 'required|in:en_attente,confirmee,en_preparation,prete,en_livraison,livree,annulee',
                'notes_admin' => 'nullable|string|max:1000'
            ]);

            DB::beginTransaction();

            $commande->update($validated);

            if (in_array($validated['statut'], ['confirmee', 'en_preparation', 'prete', 'en_livraison', 'livree'], true)) {
                $this->commandeService->confirmCommandeStock($commande);
            }

            DB::commit();

            Log::info('Statut de commande mis à jour', [
                'commande_id' => $commande->id,
                'nouveau_statut' => $validated['statut'],
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Statut mis à jour avec succès',
                'data' => [
                    'commande' => $this->formatCommandeList($commande->fresh())
                ]
            ]);

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('Erreur mise à jour statut', [
                'commande_id' => $commande->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut'
            ], 500);
        }
    }

    // ========== MÉTHODES PRIVÉES ==========

    /**
     * Formater une commande pour la liste
     */
    private function formatCommandeList(Commande $commande): array
{
    $formatted = [
        'id' => $commande->id,
        'numero_commande' => $commande->numero_commande,
        'client' => $commande->client ? [
            'id' => $commande->client->id,
            'nom_complet' => $commande->client->nom . ' ' . $commande->client->prenom,
            'telephone' => $commande->client->telephone
        ] : null,
        'nom_destinataire' => $commande->nom_destinataire,
        'telephone_livraison' => $commande->telephone_livraison,
        'adresse_livraison' => $commande->adresse_livraison,
        'montant_total' => $commande->montant_total,
        'statut' => $commande->statut,
        'statut_label' => $this->getStatutLabel($commande->statut),
        'priorite' => $commande->priorite,
        'source' => $commande->source,
        'created_at' => $commande->created_at->toISOString(),
        'date_commande' => $commande->created_at->format('d/m/Y H:i'),
        'date_livraison_prevue' => $commande->date_livraison_prevue?->format('d/m/Y'),
        'nb_articles' => $commande->articles_commandes->sum('quantite'),
        'est_payee' => $this->isCommandePaid($commande),
        'peut_modifier' => !$this->isCommandePaid($commande),
        'peut_supprimer' => !$this->isCommandePaid($commande),
        'est_en_retard' => $commande->date_livraison_prevue &&
                           $commande->date_livraison_prevue < now() &&
                           !in_array($commande->statut, ['livree', 'annulee']),
    ];

    return $formatted;
}

    /**
     * Formater une commande pour les détails complets
     */
    private function formatCommandeDetail(Commande $commande): array
    {
        $data = $this->formatCommandeList($commande);
        
        $data = array_merge($data, [
            'adresse_livraison' => $commande->adresse_livraison,
            'instructions_livraison' => $commande->instructions_livraison,
            'mode_livraison' => $commande->mode_livraison,
            'notes_client' => $commande->notes_client,
            'notes_admin' => $commande->notes_admin,
            'est_cadeau' => $commande->est_cadeau,
            'message_cadeau' => $commande->message_cadeau,
            'code_promo' => $commande->code_promo,
            'sous_total' => $commande->sous_total,
            'frais_livraison' => $commande->frais_livraison,
            'zone_livraison_nom' => $commande->zone_livraison_nom,
            'remise' => $commande->remise,
            'source' => $commande->source,
            
            // Informations client détaillées avec mesures
            'client_details' => $commande->client ? [
                'id' => $commande->client->id,
                'nom_complet' => $commande->client->nom . ' ' . $commande->client->prenom,
                'telephone' => $commande->client->telephone,
                'email' => $commande->client->email,
                'adresse_principale' => $commande->client->adresse_principale,
                'ville' => $commande->client->ville,
                'a_mesures' => $commande->client->mesures !== null,
                'mesures_client' => $commande->client->mesures ? [
                    'date_prise' => $commande->client->mesures->date_prise_mesures?->format('d/m/Y'),
                    'mesures_valides' => $commande->client->mesures->mesures_valides,
                    'mesures' => $commande->client->mesures->getMesuresRemplies(),
                    'notes_mesures' => $commande->client->mesures->notes_mesures
                ] : null
            ] : null,
            
            // Articles détaillés avec mesures spécifiques
            'articles' => $commande->articles_commandes->map(function ($article) {
                $articleData = [
                    'id' => $article->id,
                    'produit' => [
                        'id' => $article->produit->id,
                        'nom' => $article->produit->nom,
                        'image' => $article->produit->image, // Utilise l'accessor qui gère le fallback
                        'categorie' => $article->produit->category->nom ?? null,
                        'fait_sur_mesure' => $article->produit->fait_sur_mesure
                    ],
                    'quantite' => $article->quantite,
                    'prix_unitaire' => $article->prix_unitaire,
                    'prix_total' => $article->prix_total_article,
                    'taille_choisie' => $article->taille_choisie,
                    'couleur_choisie' => $article->couleur_choisie,
                    'demandes_personnalisation' => $article->demandes_personnalisation,
                    'statut_production' => $article->statut_production,
                    'statut_production_label' => $this->getStatutProductionLabel($article->statut_production),
                    
                    // Gestion détaillée des mesures
                    'type_confection' => $this->getTypeConfection($article),
                    'mesures_utilisees' => null,
                    'utilise_mesures_client' => false,
                    'mesures_personnalisees' => false
                ];

                // Déterminer le type de mesures utilisées
                if ($article->taille_choisie) {
                    $articleData['type_confection'] = 'taille_standard';
                } elseif ($article->mesures_client) {
                    $mesures = json_decode($article->mesures_client, true);
                    if (!empty($mesures)) {
                        $articleData['type_confection'] = 'mesures_specifiques';
                        $articleData['mesures_utilisees'] = $mesures;
                        
                        // Vérifier si ce sont les mesures du client ou des mesures personnalisées
                        $mesuresClient = $article->commande->client->mesures ? 
                            $article->commande->client->mesures->getMesuresRemplies() : [];
                        
                        if (!empty($mesuresClient) && $this->compareMesures($mesures, $mesuresClient)) {
                            $articleData['utilise_mesures_client'] = true;
                            $articleData['source_mesures'] = 'Mesures du client';
                        } else {
                            $articleData['mesures_personnalisees'] = true;
                            $articleData['source_mesures'] = 'Mesures personnalisées pour cet article';
                        }
                        
                        // Formater les mesures pour l'affichage
                        $articleData['mesures_formatted'] = $this->formatMesuresForDisplay($mesures);
                    }
                }

                return $articleData;
            }),

            // Paiements
            'paiements' => $commande->paiements->map(function ($paiement) {
                return [
                    'id' => $paiement->id,
                    'montant' => $paiement->montant,
                    'methode' => $paiement->methode_paiement,
                    'statut' => $paiement->statut,
                    'date' => $paiement->created_at->format('d/m/Y H:i'),
                    'reference' => $paiement->reference_paiement
                ];
            }),

            'montant_paye' => $commande->paiements->where('statut', 'valide')->sum('montant'),
            'montant_restant' => max(0, $commande->montant_total - $commande->paiements->where('statut', 'valide')->sum('montant')),
            
            // Informations de production
            'production_info' => [
                'articles_avec_mesures' => $commande->articles_commandes->filter(function($article) {
                    return !empty($article->mesures_client);
                })->count(),
                'articles_taille_standard' => $commande->articles_commandes->filter(function($article) {
                    return !empty($article->taille_choisie);
                })->count(),
                'delai_production_estime' => $this->calculateDelaiProduction($commande),
                'difficulte_globale' => $this->calculateDifficulteGlobale($commande)
            ]
        ]);

        return $data;
    }

    /**
     * Obtenir le type de confection pour un article
     */
    private function getTypeConfection($article): string
    {
        if ($article->taille_choisie) {
            return 'taille_standard';
        } elseif ($article->mesures_client) {
            return 'mesures_specifiques';
        }
        return 'non_defini';
    }

    /**
     * Comparer deux ensembles de mesures pour voir s'ils sont identiques
     */
    private function compareMesures(array $mesures1, array $mesures2): bool
    {
        // Comparer les mesures principales
        $mesuresPrincipales = ['epaule', 'poitrine', 'taille', 'longueur_robe', 'tour_bras'];
        
        $correspondances = 0;
        $total = 0;
        
        foreach ($mesuresPrincipales as $mesure) {
            if (isset($mesures1[$mesure]) && isset($mesures2[$mesure])) {
                $total++;
                if (abs($mesures1[$mesure] - $mesures2[$mesure]) < 1) { // Tolérance de 1cm
                    $correspondances++;
                }
            }
        }
        
        return $total > 0 && ($correspondances / $total) >= 0.8; // 80% de correspondance
    }

    /**
     * Formater les mesures pour l'affichage
     */
    private function formatMesuresForDisplay(array $mesures): array
    {
        $mesuresFormatted = [];
        
        $labelsMesures = [
            'epaule' => 'Épaule',
            'poitrine' => 'Poitrine',
            'taille' => 'Taille',
            'longueur_robe' => 'Longueur robe',
            'tour_bras' => 'Tour de bras',
            'tour_cuisses' => 'Tour de cuisses',
            'longueur_jupe' => 'Longueur jupe',
            'ceinture' => 'Ceinture',
            'tour_fesses' => 'Tour de fesses',
            'buste' => 'Buste',
            'longueur_manches_longues' => 'Manches longues',
            'longueur_manches_courtes' => 'Manches courtes',
            'longueur_short' => 'Longueur short',
            'cou' => 'Tour de cou',
            'longueur_taille_basse' => 'Taille basse'
        ];
        
        foreach ($mesures as $key => $value) {
            if ($value && isset($labelsMesures[$key])) {
                $mesuresFormatted[] = [
                    'label' => $labelsMesures[$key],
                    'valeur' => $value,
                    'unite' => 'cm',
                    'affichage' => $labelsMesures[$key] . ': ' . $value . 'cm'
                ];
            }
        }
        
        return $mesuresFormatted;
    }

    /**
     * Obtenir le libellé du statut de production
     */
    private function getStatutProductionLabel(string $statut): string
    {
        $labels = [
            'en_attente' => 'En attente',
            'en_cours' => 'En cours de production',
            'pause' => 'En pause',
            'termine' => 'Terminé',
            'controle_qualite' => 'Contrôle qualité',
            'retouches' => 'Retouches nécessaires',
            'pret' => 'Prêt'
        ];

        return $labels[$statut] ?? ucfirst(str_replace('_', ' ', $statut));
    }

    /**
     * Calculer le délai de production estimé
     */
    private function calculateDelaiProduction(Commande $commande): int
    {
        $delaiTotal = 0;
        
        foreach ($commande->articles_commandes as $article) {
            $delaiArticle = 1; // Délai de base : 1 jour
            
            // Ajouter du temps si mesures spécifiques
            if ($article->mesures_client) {
                $delaiArticle += 2; // +2 jours pour mesures
            }
            
            // Ajouter du temps selon le produit
            if ($article->produit->delai_production_jours) {
                $delaiArticle = max($delaiArticle, $article->produit->delai_production_jours);
            }
            
            // Multiplier par la quantité (avec dégressivité)
            if ($article->quantite > 1) {
                $delaiArticle += ceil(($article->quantite - 1) * 0.5);
            }
            
            $delaiTotal = max($delaiTotal, $delaiArticle); // Prendre le plus long (production en parallèle)
        }
        
        return $delaiTotal;
    }

    /**
     * Calculer la difficulté globale de la commande
     */
    private function calculateDifficulteGlobale(Commande $commande): string
    {
        $scoreTotal = 0;
        $nombreArticles = $commande->articles_commandes->count();
        
        foreach ($commande->articles_commandes as $article) {
            $scoreArticle = 1; // Score de base
            
            // +2 si mesures spécifiques
            if ($article->mesures_client) {
                $scoreArticle += 2;
            }
            
            // +1 si personnalisations
            if ($article->demandes_personnalisation) {
                $scoreArticle += 1;
            }
            
            // +1 si quantité élevée
            if ($article->quantite > 3) {
                $scoreArticle += 1;
            }
            
            $scoreTotal += $scoreArticle;
        }
        
        $scoreMoyen = $scoreTotal / $nombreArticles;
        
        if ($scoreMoyen <= 2) {
            return 'facile';
        } elseif ($scoreMoyen <= 3.5) {
            return 'moyen';
        } else {
            return 'difficile';
        }
    }

    /**
     * Obtenir le libellé du statut
     */
    private function getStatutLabel(string $statut): string
    {
        $labels = [
            'en_attente' => 'En attente',
            'confirmee' => 'Confirmée',
            'en_preparation' => 'En préparation',
            'prete' => 'Prête',
            'en_livraison' => 'En livraison',
            'livree' => 'Livrée',
            'annulee' => 'Annulée'
        ];

        return $labels[$statut] ?? $statut;
    }

    /**
     * Vérifier si une commande est payée
     */
    private function isCommandePaid(Commande $commande): bool
    {
        return $commande->paiements()
            ->where('statut', 'valide')
            ->sum('montant') >= $commande->montant_total;
    }

    /**
     * Obtenir les commandes en retard
     */
    public function getCommandesEnRetard(): JsonResponse
    {
        try {
            $commandesEnRetard = Commande::with(['client', 'articles_commandes.produit'])
                ->where('date_livraison_prevue', '<', now())
                ->whereNotIn('statut', ['livree', 'annulee'])
                ->orderBy('date_livraison_prevue')
                ->get()
                ->map(function ($commande) {
                    return $this->formatCommandeList($commande);
                });

            return response()->json([
                'success' => true,
                'data' => ['commandes' => $commandesEnRetard]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur récupération commandes en retard', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des commandes en retard'
            ], 500);
        }
    }

    /**
     * Obtenir les commandes urgentes
     */
    public function getCommandesUrgentes(): JsonResponse
    {
        try {
            $commandesUrgentes = Commande::with(['client', 'articles_commandes.produit'])
                ->whereIn('priorite', ['urgente', 'tres_urgente'])
                ->whereNotIn('statut', ['livree', 'annulee'])
                ->orderBy('priorite', 'desc')
                ->orderBy('created_at')
                ->get()
                ->map(function ($commande) {
                    return $this->formatCommandeList($commande);
                });

            return response()->json([
                'success' => true,
                'data' => ['commandes' => $commandesUrgentes]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur récupération commandes urgentes', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des commandes urgentes'
            ], 500);
        }
    }

    /**
     * Dupliquer une commande
     */
    public function duplicate(Commande $commande): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Préparer les données pour la nouvelle commande
            $commandeData = [
                'client_id' => $commande->client_id,
                'nom_destinataire' => $commande->nom_destinataire,
                'telephone_livraison' => $commande->telephone_livraison,
                'adresse_livraison' => $commande->adresse_livraison,
                'instructions_livraison' => $commande->instructions_livraison,
                'mode_livraison' => $commande->mode_livraison,
                'notes_client' => $commande->notes_client,
                'priorite' => $commande->priorite,
                'est_cadeau' => $commande->est_cadeau,
                'message_cadeau' => $commande->message_cadeau,
                'frais_livraison' => $commande->frais_livraison,
                'remise' => $commande->remise,
                'articles' => []
            ];

            // Préparer les articles
            foreach ($commande->articles_commandes as $article) {
                $articleData = [
                    'produit_id' => $article->produit_id,
                    'quantite' => $article->quantite,
                    'prix_unitaire' => $article->prix_unitaire,
                    'taille' => $article->taille_choisie,
                    'couleur' => $article->couleur_choisie,
                    'instructions' => $article->demandes_personnalisation
                ];

                // Ajouter les mesures si présentes
                if ($article->mesures_client) {
                    $articleData['mesures'] = json_decode($article->mesures_client, true);
                }

                $commandeData['articles'][] = $articleData;
            }

            // Créer la nouvelle commande
            $nouvelleCommande = $this->commandeService->createCommande($commandeData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Commande dupliquée avec succès',
                'data' => [
                    'commande' => $this->formatCommandeDetail($nouvelleCommande)
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Erreur duplication commande', [
                'commande_id' => $commande->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la duplication de la commande'
            ], 500);
        }
    }

    /**
     * Recherche rapide de commandes
     */
    public function quickSearch(Request $request): JsonResponse
    {
        try {
            $search = $request->get('q', '');
            
            if (strlen($search) < 2) {
                return response()->json([
                    'success' => true,
                    'data' => ['commandes' => []]
                ]);
            }

            $commandes = Commande::with(['client', 'articles_commandes.produit'])
                ->where(function($query) use ($search) {
                    $query->where('numero_commande', 'ILIKE', "%{$search}%")
                          ->orWhere('nom_destinataire', 'ILIKE', "%{$search}%")
                          ->orWhere('telephone_livraison', 'ILIKE', "%{$search}%")
                          ->orWhereHas('client', function($clientQuery) use ($search) {
                              $clientQuery->where('nom', 'ILIKE', "%{$search}%")
                                        ->orWhere('prenom', 'ILIKE', "%{$search}%")
                                        ->orWhere('telephone', 'ILIKE', "%{$search}%");
                          });
                })
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($commande) {
                    return [
                        'id' => $commande->id,
                        'numero_commande' => $commande->numero_commande,
                        'client_nom' => $commande->client ? $commande->client->nom . ' ' . $commande->client->prenom : $commande->nom_destinataire,
                        'montant_total' => $commande->montant_total,
                        'statut' => $commande->statut,
                        'statut_label' => $this->getStatutLabel($commande->statut),
                        'date_commande' => $commande->created_at->format('d/m/Y')
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => ['commandes' => $commandes]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur recherche rapide commandes', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche'
            ], 500);
        }
    }

    /**
     * Exporter les commandes en CSV
     */
    public function export(Request $request): JsonResponse
    {
        try {
            // Construire les filtres
            $filters = [
                'numero_commande' => $request->get('numero_commande'),
                'client_search' => $request->get('client_search'),
                'produit_search' => $request->get('produit_search'),
                'statut' => $request->get('statut'),
                'date_debut' => $request->get('date_debut'),
                'date_fin' => $request->get('date_fin'),
                'priorite' => $request->get('priorite'),
            ];

            // Récupérer toutes les commandes correspondant aux filtres
            $commandes = $this->commandeService->searchCommandes($filters)->get();

            // Préparer les données pour l'export
            $exportData = $commandes->map(function ($commande) {
                return [
                    'Numéro' => $commande->numero_commande,
                    'Date' => $commande->created_at->format('d/m/Y H:i'),
                    'Client' => $commande->client ? $commande->client->nom . ' ' . $commande->client->prenom : $commande->nom_destinataire,
                    'Téléphone' => $commande->telephone_livraison,
                    'Montant Total' => $commande->montant_total,
                    'Statut' => $this->getStatutLabel($commande->statut),
                    'Priorité' => ucfirst($commande->priorite),
                    'Articles' => $commande->articles_commandes->sum('quantite'),
                    'Date Livraison' => $commande->date_livraison_prevue?->format('d/m/Y'),
                    'Adresse' => $commande->adresse_livraison
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'commandes' => $exportData,
                    'total' => $exportData->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur export commandes', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export'
            ], 500);
        }
    }

    // Dans CommandeController.php, ajouter cette méthode :

/**
 * Marquer une commande comme payée
 */
public function markAsPaid(Request $request, Commande $commande): JsonResponse
{
    try {
        $validated = $request->validate([
            'montant' => 'required|numeric|min:0',
            'methode_paiement' => 'required|string|in:especes,wave,orange_money,free_money,virement,cheque,carte',
            'reference_paiement' => 'nullable|string|max:100'
        ]);

        DB::beginTransaction();

        // Vérifier si la commande n'est pas déjà entièrement payée
        $montantDejaPaye = $commande->paiements()
            ->where('statut', 'valide')
            ->sum('montant');

        $montantRestant = $commande->montant_total - $montantDejaPaye;

        if ($montantRestant <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cette commande est déjà entièrement payée'
            ], 400);
        }

        // Limiter le montant au montant restant
        $montantAPayer = min($validated['montant'], $montantRestant);

        // Le schema paiements du salon impose numero_recu, type et mode_paiement
        // (enum precis). On mappe la methode saisie vers l'enum et on genere un
        // numero de recu unique. Paiement complet vs acompte selon le reste du.
        $modePaiement = match ($validated['methode_paiement']) {
            'especes' => 'especes',
            'wave' => 'wave',
            'orange_money' => 'orange_money',
            'carte' => 'carte_bancaire',
            'virement' => 'virement',
            default => 'autre', // free_money, cheque, ...
        };

        $paiement = \App\Models\Paiement::create([
            'commande_id' => $commande->id,
            'client_id' => $commande->client_id,
            'numero_recu' => 'RECU-' . now()->format('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 8)),
            'type' => $montantAPayer >= $montantRestant ? 'complet' : 'acompte',
            'mode_paiement' => $modePaiement,
            'montant' => $montantAPayer,
            'devise' => 'FCFA',
            'statut' => 'valide',
            'date_paiement' => now(),
            'reference' => $validated['reference_paiement'] ?: null,
            'notes' => 'Paiement enregistré manuellement par ' . (auth()->user()->name ?? 'admin')
                . ' (méthode : ' . $validated['methode_paiement'] . ')',
        ]);

        // Mettre à jour le statut de la commande si entièrement payée
        $nouveauMontantPaye = $montantDejaPaye + $montantAPayer;
        if ($nouveauMontantPaye >= $commande->montant_total) {
            $commande->update([
                'statut' => $commande->statut === 'en_attente' ? 'confirmee' : $commande->statut
            ]);

            $this->commandeService->confirmCommandeStock($commande);
        }

        DB::commit();

        Log::info('Paiement enregistré manuellement', [
            'commande_id' => $commande->id,
            'paiement_id' => $paiement->id,
            'montant' => $montantAPayer,
            'methode' => $validated['methode_paiement'],
            'user_id' => auth()->id()
        ]);

        return response()->json([
            'success' => true,
            'message' => $montantAPayer < $montantRestant 
                ? "Acompte de {$montantAPayer} F CFA enregistré. Reste à payer : " . ($montantRestant - $montantAPayer) . " F CFA"
                : 'Paiement complet enregistré avec succès',
            'data' => [
                'paiement' => [
                    'id' => $paiement->id,
                    'montant' => $paiement->montant,
                    'methode' => $paiement->methode_paiement,
                    'reference' => $paiement->reference_paiement,
                    'date' => $paiement->created_at->format('d/m/Y H:i')
                ],
                'commande' => [
                    'montant_total' => $commande->montant_total,
                    'montant_paye' => $nouveauMontantPaye,
                    'montant_restant' => max(0, $commande->montant_total - $nouveauMontantPaye),
                    'est_entierement_payee' => $nouveauMontantPaye >= $commande->montant_total
                ]
            ]
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('Erreur enregistrement paiement manuel', [
            'commande_id' => $commande->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de l\'enregistrement du paiement',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Obtenir le résumé du jour
     */
    public function getDailyReport(): JsonResponse
    {
        try {
            $today = now()->startOfDay();
            $tomorrow = now()->addDay()->startOfDay();

            $report = [
                'commandes_aujourd_hui' => Commande::whereBetween('created_at', [$today, $tomorrow])->count(),
                'ca_aujourd_hui' => Commande::whereBetween('created_at', [$today, $tomorrow])
                    ->whereIn('statut', ['livree', 'prete'])
                    ->sum('montant_total'),
                'commandes_a_traiter' => Commande::whereIn('statut', ['en_attente', 'confirmee'])->count(),
                'commandes_a_livrer' => Commande::where('date_livraison_prevue', '>=', $today)
                    ->where('date_livraison_prevue', '<', $tomorrow)
                    ->whereIn('statut', ['prete', 'en_livraison'])
                    ->count(),
                'commandes_en_retard' => Commande::where('date_livraison_prevue', '<', $today)
                    ->whereNotIn('statut', ['livree', 'annulee'])
                    ->count(),
                'nouvelles_commandes' => Commande::whereBetween('created_at', [$today, $tomorrow])
                    ->with(['client'])
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($commande) {
                        return [
                            'id' => $commande->id,
                            'numero_commande' => $commande->numero_commande,
                            'client' => $commande->client ? $commande->client->nom . ' ' . $commande->client->prenom : $commande->nom_destinataire,
                            'montant_total' => $commande->montant_total,
                            'heure' => $commande->created_at->format('H:i')
                        ];
                    })
            ];

            return response()->json([
                'success' => true,
                'data' => $report
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur rapport quotidien', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du rapport'
            ], 500);
        }
    }
 }
