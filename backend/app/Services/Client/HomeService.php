<?php

namespace App\Services\Client;

use App\Models\Produit;
use App\Models\Category;
use App\Models\Promotion;
use App\Models\AvisClient;
use App\Models\Client;
use App\Models\Commande;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class HomeService
{
    /**
     * Obtenir toutes les données de la page d'accueil
     * Simplifié pour afficher uniquement produits populaires et nouveautés
     */
    public function getHomeData(): array
    {
        return [
            'hero_banner' => $this->getHeroBanner(),
            'categories_preview' => $this->getCategoriesPreview(), // Catégories en cercles
            'featured_products' => $this->getFeaturedProducts(8), // Produits populaires
            'new_arrivals' => $this->getNewArrivals(8), // Nouveautés
            'active_promotions' => $this->getActivePromotions(),
            'testimonials' => $this->getFeaturedTestimonials(6), // Avis clients
            'shop_stats' => $this->getPublicShopStats(),
            'flash_sale' => $this->getFlashSale()
        ];
    }

    /**
     * Bannière hero avec promotion principale
     */
    private function getHeroBanner(): array
    {
        $mainPromo = Promotion::where('est_active', true)
            ->where('afficher_site', true)
            ->where('date_debut', '<=', now())
            ->where('date_fin', '>=', now())
            ->orderBy('valeur', 'desc')
            ->first();

        return [
            'has_promotion' => $mainPromo !== null,
            'promotion' => $mainPromo ? [
                'id' => $mainPromo->id,
                'nom' => $mainPromo->nom,
                'description' => $mainPromo->description,
                'code' => $mainPromo->code,
                'valeur' => $mainPromo->valeur,
                'type' => $mainPromo->type_promotion,
                'image' => $mainPromo->image ? asset('storage/' . $mainPromo->image) : null,
                'couleur' => $mainPromo->couleur_affichage ?? '#ef4444',
                'date_fin' => $mainPromo->date_fin->toISOString(),
                'jours_restants' => $mainPromo->date_fin->diffInDays(now())
            ] : null,
            'default_message' => [
                'titre' => 'NDEYA SHOP',
                'sous_titre' => 'Mode Africaine Authentique',
                'description' => 'Découvrez notre collection exclusive de vêtements traditionnels et modernes',
                'cta' => 'Découvrir la Collection'
            ]
        ];
    }

    /**
     * Produits populaires (marqués en admin)
     */
    public function getFeaturedProducts(int $limit = 8): array
    {
        $produits = Produit::where('est_visible', true)
            ->where('est_populaire', true)
            ->with(['category', 'images_produits' => function($q) {
                $q->where('est_principale', true)->orWhere('ordre_affichage', 1);
            }])
            ->orderByDesc('nombre_ventes')
            ->orderByDesc('note_moyenne')
            ->limit($limit)
            ->get();

        return $produits->map(function ($produit) {
            return $this->formatProductForClient($produit);
        })->toArray();
    }

    /**
     * Nouveautés (produits marqués comme nouveauté en admin)
     */
    public function getNewArrivals(int $limit = 8): array
    {
        $produits = Produit::where('est_visible', true)
            ->where('est_nouveaute', true)
            ->with(['category', 'images_produits' => function($q) {
                $q->where('est_principale', true)->orWhere('ordre_affichage', 1);
            }])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $produits->map(function ($produit) {
            return $this->formatProductForClient($produit, ['badge' => 'Nouveau']);
        })->toArray();
    }

    /**
     * Produits en promotion
     */
    public function getProductsOnSale(int $limit = 8): array
    {
        $produits = Produit::where('est_visible', true)
            ->whereNotNull('prix_promo')
            ->where(function($query) {
                $query->whereNull('debut_promo')
                    ->orWhere('debut_promo', '<=', now());
            })
            ->where(function($query) {
                $query->whereNull('fin_promo')
                    ->orWhere('fin_promo', '>=', now());
            })
            ->with(['category', 'images_produits' => function($q) {
                $q->where('est_principale', true)->orWhere('ordre_affichage', 1);
            }])
            ->orderByRaw('((prix - prix_promo) / prix) DESC')
            ->limit($limit)
            ->get();

        return $produits->map(function ($produit) {
            $reduction = round(((($produit->prix - $produit->prix_promo) / $produit->prix) * 100), 0);
            return $this->formatProductForClient($produit, [
                'badge' => '-' . $reduction . '%',
                'badge_color' => 'red'
            ]);
        })->toArray();
    }

    /**
     * Aperçu des catégories principales
     */
    public function getCategoriesPreview(): array
    {
        $categories = Category::where('est_active', true)
            ->whereNull('parent_id')
            ->withCount('produits')
            ->orderBy('ordre_affichage')
            ->limit(12)
            ->get();

        return $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'parent_id' => $category->parent_id,
                'nom' => $category->nom,
                'slug' => $category->slug,
                'description' => $category->description,
                'image' => $category->image ? asset('storage/' . $category->image) : null,
                'couleur_theme' => $category->couleur_theme,
                'produits_count' => $category->produits_count,
                'est_populaire' => $category->est_populaire ?? false,
                'url' => '/categories/' . $category->slug
            ];
        })->toArray();
    }
    /**
     * Promotions actives pour les bannières
     */
    public function getActivePromotions(): array
    {
        $promotions = Promotion::where('est_active', true)
            ->where('afficher_site', true)
            ->where('date_debut', '<=', now())
            ->where('date_fin', '>=', now())
            ->orderBy('valeur', 'desc')
            ->limit(3)
            ->get();

        return $promotions->map(function ($promotion) {
            return [
                'id' => $promotion->id,
                'nom' => $promotion->nom,
                'description' => $promotion->description,
                'code' => $promotion->code,
                'valeur' => $promotion->valeur,
                'type' => $promotion->type_promotion,
                'valeur_formatted' => $this->formatPromotionValue($promotion),
                'image' => $promotion->image ? asset('storage/' . $promotion->image) : null,
                'couleur' => $promotion->couleur_affichage ?? '#ef4444',
                'date_fin' => $promotion->date_fin->toISOString(),
                'jours_restants' => $promotion->date_fin->diffInDays(now()),
                'is_flash_sale' => $promotion->date_fin->diffInHours(now()) <= 24
            ];
        })->toArray();
    }

    /**
     * Produits avec prix_promo actif pour la barre d'annonce
     */
    public function getProductPromoStats(): array
    {
        $query = \DB::table('produits')
            ->whereNotNull('prix_promo')
            ->where('est_visible', true)
            ->where(function ($q) {
                $q->whereNull('debut_promo')->orWhere('debut_promo', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('fin_promo')->orWhere('fin_promo', '>=', now());
            });

        $count = $query->count();

        if ($count === 0) {
            return ['active' => false, 'count' => 0, 'max_remise' => 0];
        }

        $maxRemise = $query->selectRaw('MAX(ROUND(((prix - prix_promo) / prix) * 100)) as max_remise')
            ->value('max_remise') ?? 0;

        return [
            'active' => true,
            'count'  => $count,
            'max_remise' => (int) $maxRemise,
        ];
    }

    /**
     * Témoignages clients en vedette
     */
    public function getFeaturedTestimonials(int $limit = 6): array
    {
        $avis = AvisClient::where('statut', 'approuve')
            ->where('est_visible', true)
            ->where('note_globale', '>=', 4)
            ->with(['client', 'produit'])
            ->orderByDesc('est_mis_en_avant')
            ->orderBy('ordre_affichage')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $avis->map(function ($avis) {
            return [
                'id' => $avis->id,
                'nom_client' => $avis->nom_affiche ?: ($avis->client ? $avis->client->prenom : 'Client anonyme'),
                'note' => $avis->note_globale,
                'commentaire' => $avis->commentaire,
                'produit_nom' => $avis->produit->nom,
                'date' => $avis->created_at->format('M Y'),
                'avis_verifie' => $avis->avis_verifie,
                'photos' => collect($avis->photos_avis ?? [])
                    ->map(fn ($p) => \Illuminate\Support\Facades\Storage::disk('public')->url($p))
                    ->all(),
                'produit_slug' => $avis->produit->slug ?? null,
            ];
        })->toArray();
    }

    /**
     * Statistiques publiques de la boutique
     */
    public function getPublicShopStats(): array
    {
        return [
            'produits_disponibles' => Produit::where('est_visible', true)->count(),
            'clients_satisfaits' => Client::count(),
            'commandes_livrees' => Commande::where('statut', 'livree')->count(),
            'note_moyenne' => $this->getAverageRating(),
            'annees_experience' => now()->year - 2020, // Supposons que la boutique a été créée en 2020
            'livraison_gratuite_seuil' => 50000 // FCFA
        ];
    }

    /**
     * Vente flash actuelle
     */
    public function getFlashSale(): ?array
    {
        $flashPromo = Promotion::where('est_active', true)
            ->where('afficher_site', true)
            ->where('date_debut', '<=', now())
            ->where('date_fin', '>=', now())
            ->where('date_fin', '<=', now()->addHours(24)) // Se termine dans les 24h
            ->orderBy('date_fin')
            ->first();

        if (!$flashPromo) {
            return null;
        }

        // Obtenir les produits éligibles pour cette promo
        $produits = [];
        if ($flashPromo->produits_eligibles) {
            $produitIds = json_decode($flashPromo->produits_eligibles, true);
            $produits = Produit::whereIn('id', $produitIds)
                ->where('est_visible', true)
                ->with(['images_produits' => function($q) {
                    $q->where('est_principale', true);
                }])
                ->limit(4)
                ->get()
                ->map(function ($produit) {
                    return $this->formatProductForClient($produit);
                })->toArray();
        }

        return [
            'id' => $flashPromo->id,
            'nom' => $flashPromo->nom,
            'description' => $flashPromo->description,
            'valeur' => $flashPromo->valeur,
            'type' => $flashPromo->type_promotion,
            'code' => $flashPromo->code,
            'date_fin' => $flashPromo->date_fin->toISOString(),
            'heures_restantes' => $flashPromo->date_fin->diffInHours(now()),
            'minutes_restantes' => $flashPromo->date_fin->diffInMinutes(now()) % 60,
            'produits' => $produits,
            'couleur' => $flashPromo->couleur_affichage ?? '#ef4444'
        ];
    }

    /**
     * Vérifier s'il y a une vente flash active
     */
    public function hasFlashSale(): bool
    {
        return Promotion::where('est_active', true)
            ->where('afficher_site', true)
            ->where('date_debut', '<=', now())
            ->where('date_fin', '>=', now())
            ->where('date_fin', '<=', now()->addHours(24))
            ->exists();
    }

    /**
     * Calculer les économies totales sur les produits en promo
     */
    public function calculateTotalSavings(array $produits): array
    {
        $totalEconomies = 0;
        $totalOriginal = 0;

        foreach ($produits as $produit) {
            if (isset($produit['prix_promo']) && $produit['prix_promo']) {
                $totalOriginal += $produit['prix'];
                $totalEconomies += ($produit['prix'] - $produit['prix_promo']);
            }
        }

        return [
            'total_economies' => $totalEconomies,
            'pourcentage_moyen' => $totalOriginal > 0 ? round(($totalEconomies / $totalOriginal) * 100) : 0
        ];
    }

    /**
     * Note moyenne générale
     */
    public function getAverageRating(): float
    {
        return round(AvisClient::where('statut', 'approuve')
            ->avg('note_globale') ?: 4.5, 1);
    }

    /**
     * Inscription à la newsletter
     */
    public function subscribeToNewsletter(array $data): array
    {
        try {
            // Vérifier si l'email existe déjà
            $existingClient = Client::where('email', $data['email'])->first();
            
            if ($existingClient) {
                // Mettre à jour les préférences marketing
                $existingClient->update(['accepte_email' => true]);
                
                return [
                    'success' => true,
                    'message' => 'Merci ! Vos préférences ont été mises à jour.'
                ];
            }

            // Créer un nouveau prospect/client newsletter
            Client::create([
                'email' => $data['email'],
                'nom' => $data['nom'] ?? 'Newsletter',
                'prenom' => $data['prenom'] ?? 'Abonné',
                'telephone' => '000000000', // Obligatoire dans notre modèle
                'ville' => 'Dakar',
                'accepte_email' => true,
                'accepte_promotions' => true,
                'type_client' => 'prospect'
            ]);

            Log::info('Nouvelle inscription newsletter', [
                'email' => $data['email']
            ]);

            return [
                'success' => true,
                'message' => 'Merci pour votre inscription ! Vous recevrez nos dernières nouveautés.'
            ];

        } catch (\Exception $e) {
            Log::error('Erreur inscription newsletter', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'message' => 'Une erreur est survenue. Veuillez réessayer.'
            ];
        }
    }

    /**
     * Recherche rapide
     */
    public function quickSearch(string $query): array
    {
        $query = trim(mb_strtolower($query));
        $cacheKey = 'quick_search:' . md5($query);

        return Cache::remember($cacheKey, 120, function () use ($query) {
        // Recherche dans les produits
        $produits = Produit::where('est_visible', true)
            ->where(function($q) use ($query) {
                $q->where('nom', 'ILIKE', "%{$query}%")
                  ->orWhere('description', 'ILIKE', "%{$query}%")
                  ->orWhereRaw('tags::text ILIKE ?', ["%{$query}%"]);
            })
            ->with(['category', 'images_produits' => function($q) {
                $q->where('est_principale', true);
            }])
            ->orderByDesc('nombre_ventes')
            ->orderByDesc('note_moyenne')
            ->limit(5)
            ->get();

        // Recherche dans les catégories
        $categories = Category::where('est_active', true)
            ->where('nom', 'ILIKE', "%{$query}%")
            ->limit(3)
            ->get();

        return [
            'produits' => $produits->map(function ($produit) {
                return $this->formatProductForClient($produit, ['compact' => true]);
            })->toArray(),
            'categories' => $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'nom' => $category->nom,
                    'slug' => $category->slug,
                    'url' => '/categories/' . $category->slug
                ];
            })->toArray(),
            'suggestions' => $this->getSearchSuggestions($query)
        ];
        });
    }

    // ========== MÉTHODES PRIVÉES ==========

    /**
     * Formater un produit pour l'affichage client
     */
    private function formatProductForClient(Produit $produit, array $options = []): array
    {
        $isCompact = $options['compact'] ?? false;

        // Utilise l'accesseur getImageAttribute() du modèle qui gère
        // images_produits (via url accessor) + fallback image_principale
        $imageUrl = $produit->image;

        $prixActuel = $produit->prix_promo ?: $produit->prix;

        $data = [
            'id'               => $produit->id,
            'nom'              => $produit->nom,
            'slug'             => $produit->slug,
            'prix'             => $produit->prix,
            'prix_promo'       => $produit->prix_promo,
            'prix_actuel'      => $prixActuel,
            'en_promo'         => $produit->prix_promo !== null,
            'image_principale' => $imageUrl,
            'url'              => '/produits/' . $produit->slug,
            'est_nouveaute'    => $produit->est_nouveaute,
            'est_populaire'    => $produit->est_populaire,
            'stock_quantite'   => $produit->gestion_stock ? $this->resolveStockTotal($produit) : 999,
            'en_stock'         => !$produit->gestion_stock || $this->resolveStockTotal($produit) > 0,
            'type_variante'    => $produit->type_variante ?? 'vetement',
        ];

        if (!$isCompact) {
            $data = array_merge($data, [
                'description_courte'  => $produit->description_courte,
                'categorie'           => $produit->category ? [
                    'nom'  => $produit->category->nom,
                    'slug' => $produit->category->slug,
                ] : null,
                'note_moyenne'        => $produit->note_moyenne ?? 0,
                'nombre_avis'         => $produit->nombre_avis ?? 0,
                'fait_sur_mesure'     => $produit->fait_sur_mesure,
                'stock_disponible'    => $produit->gestion_stock ? $this->resolveStockTotal($produit) : null,
                'stock_status'        => $this->getProductStockStatus($produit),
                'tailles_disponibles' => $produit->tailles_disponibles
                    ? json_decode($produit->tailles_disponibles, true) : [],
                'couleurs_disponibles' => $produit->couleurs_disponibles
                    ? json_decode($produit->couleurs_disponibles, true) : [],
            ]);
        }

        if (isset($options['badge'])) {
            $data['badge'] = [
                'text'  => $options['badge'],
                'color' => $options['badge_color'] ?? 'blue',
            ];
        }

        return $data;
    }

    /**
     * Calcule le stock total réel d'un produit (variant ou simple)
     */
    private function getProductStockStatus($produit): array
    {
        if (!$produit->gestion_stock) {
            return ['status' => 'unlimited', 'label' => 'Non limité', 'color' => 'blue'];
        }
        $stockTotal = $this->resolveStockTotal($produit);
        if ($stockTotal <= 0) {
            return ['status' => 'out_of_stock', 'label' => 'Rupture de stock', 'color' => 'red'];
        }
        $seuil = (int) ($produit->seuil_alerte ?? 5);
        if ($stockTotal <= $seuil) {
            return ['status' => 'low_stock', 'label' => 'Stock limité', 'color' => 'orange'];
        }
        return ['status' => 'in_stock', 'label' => 'En stock', 'color' => 'green'];
    }

    private function resolveStockTotal($produit): int
    {
        if ($produit->couleur_tailles_stock) {
            $stockData = json_decode($produit->couleur_tailles_stock, true) ?? [];
            $total = 0;
            foreach ($stockData as $tailles) {
                foreach ($tailles as $qty) {
                    $total += (int) $qty;
                }
            }
            return $total;
        }
        return (int) ($produit->stock_disponible ?? 0);
    }

    /**
     * Formater la valeur d'une promotion
     */
    private function formatPromotionValue(Promotion $promotion): string
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
     * Générer l'URL WhatsApp pour un produit
     */
    private function generateWhatsAppUrl(Produit $produit): string
    {
        $message = "Bonjour NDEYA SHOP ! 👋\n\n";
        $message .= "Je suis intéressé(e) par ce produit :\n";
        $message .= "📦 *{$produit->nom}*\n";
        $message .= "💰 Prix : " . number_format($produit->prix_promo ?: $produit->prix, 0, ',', ' ') . " FCFA\n";
        
        if ($produit->prix_promo) {
            $message .= "🏷️ Prix promotionnel !\n";
        }
        
        $message .= "\nPourriez-vous me donner plus d'informations ?\n";
        $message .= "Merci ! 🙏";

        // Numéro WhatsApp de la boutique (à configurer)
        $whatsappNumber = config('app.whatsapp_number', '221784661412');
        
        return "https://wa.me/{$whatsappNumber}?text=" . urlencode($message);
    }

    /**
     * Obtenir des suggestions de recherche
     */
    private function getSearchSuggestions(string $query): array
    {
        // Suggestions basées sur les recherches populaires et les tags
        $suggestions = [];
        
        // Rechercher dans les tags populaires
        $tags = DB::table('produits')
            ->whereNotNull('tags')
            ->where('est_visible', true)
            ->whereRaw('tags::text ILIKE ?', ["%{$query}%"])
            ->limit(20)
            ->pluck('tags')
            ->flatMap(function ($tagString) use ($query) {
                if (!$tagString) return [];
                
                $tags = explode(',', $tagString);
                return array_filter($tags, function($tag) use ($query) {
                    return stripos(trim($tag), $query) !== false;
                });
            })
            ->unique()
            ->take(3)
            ->values()
            ->toArray();

        // Ajouter des suggestions contextuelles
        $contextualSuggestions = [
            'robe' => ['robe africaine', 'robe traditionnelle', 'robe moderne'],
            'boubou' => ['boubou homme', 'grand boubou', 'boubou brodé'],
            'wax' => ['tissu wax', 'robe wax', 'ensemble wax'],
            'traditionnel' => ['tenue traditionnelle', 'habit traditionnel'],
            'moderne' => ['style moderne', 'coupe moderne']
        ];

        foreach ($contextualSuggestions as $keyword => $suggestions_list) {
            if (stripos($query, $keyword) !== false) {
                $suggestions = array_merge($suggestions, $suggestions_list);
                break;
            }
        }

        return array_unique(array_merge($tags, $suggestions));
    }
}
