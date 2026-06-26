<?php
// ================================================================
// 📝 FICHIER: app/Services/Client/ProductService.php
// ================================================================

namespace App\Services\Client;

use App\Models\Produit;
use App\Models\Category;
use App\Models\AvisClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Models\ShopSetting;

class ProductService
{
    /**
     * Obtenir les produits avec cache Redis optimisé
     * OPTIMISÉ: Cache intelligent par page et filtres (sans tagging)
     */
    public function getProducts(array $filters = []): array
    {
        // Générer clé de cache basée sur les filtres
        $cacheKey = 'products:list:' . md5(json_encode($filters));
        $cacheTtl = config('ndeya_cache.ttl.products_list', 3600);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($filters) {
            $query = Produit::where('est_visible', true)
                ->with(['category', 'images_produits' => function($q) {
                    $q->where('est_principale', true)->orWhere('ordre_affichage', 1);
                }]);

            // Filtres
            if (isset($filters['category'])) {
                $subcategoryIds = Category::where('parent_id', $filters['category'])->pluck('id');
                $categoryIds = $subcategoryIds->prepend($filters['category']);
                $query->whereIn('categorie_id', $categoryIds);
            }

            if (isset($filters['search'])) {
                $query->where(function($q) use ($filters) {
                    $q->where('nom', 'ILIKE', "%{$filters['search']}%")
                      ->orWhere('description', 'ILIKE', "%{$filters['search']}%");
                });
            }

            if (isset($filters['min_price']) && $filters['min_price']) {
                $query->where('prix', '>=', $filters['min_price']);
            }

            if (isset($filters['max_price']) && $filters['max_price']) {
                $query->where('prix', '<=', $filters['max_price']);
            }

            if (isset($filters['on_sale']) && $filters['on_sale']) {
                $query->whereNotNull('prix_promo');
            }

            if (isset($filters['est_nouveaute']) && $filters['est_nouveaute']) {
                $query->where('est_nouveaute', true);
            }

            if (isset($filters['est_populaire']) && $filters['est_populaire']) {
                $query->where('est_populaire', true);
            }

            // Tri
            $sort = $filters['sort'] ?? 'recent';
            
            switch ($sort) {
                case 'price_asc':
                    $query->orderBy('prix', 'asc');
                    break;
                case 'price_desc':
                    $query->orderBy('prix', 'desc');
                    break;
                case 'popular':
                    $query->orderBy('nombre_vues', 'desc');
                    break;
                case 'rating':
                    $query->orderBy('note_moyenne', 'desc');
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
            }

            $perPage = $filters['per_page'] ?? 20;
            $products = $query->paginate($perPage);

            // Formater les produits - convertir en array explicitement
            $formattedProducts = $products->getCollection()->map(function ($product) {
                return $this->formatProductCard($product);
            })->values()->toArray();

            return [
                'products' => $formattedProducts,
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'has_more' => $products->hasMorePages()
                ]
            ];
        });
    }

    /**
     * Obtenir un produit par slug avec cache
     * OPTIMISÉ: Cache produit détail + incrémentation asynchrone des vues
     */
    public function getProductBySlug(string $slug): ?array
    {
        $cacheKey = "product:detail:{$slug}";
        $cacheTtl = config('ndeya_cache.ttl.product_detail', 7200);

        $product = Cache::tags(['products'])->remember($cacheKey, $cacheTtl, function () use ($slug) {
            return Produit::where('slug', $slug)
                ->where('est_visible', true)
                ->with([
                    'category',
                    'images_produits' => function($q) {
                        $q->orderBy('ordre_affichage');
                    }
                ])
                ->first();
        });

        if (!$product) {
            return null;
        }

        // Incrémenter les vues en arrière-plan (non bloquant)
        dispatch(function () use ($product) {
            Produit::where('id', $product->id)->increment('nombre_vues');
        })->afterResponse();

        return $this->formatProductDetails($product);
    }

    public function getProductImages(int $productId): array
    {
        $product = Produit::find($productId);
        
        if (!$product) {
            return [];
        }

        return $product->images_produits->map(function ($image) {
            $urls = $this->imageUrls($image);

            return [
                'id' => $image->id,
                'original' => $urls['original'],
                'thumbnail' => $urls['thumbnail'],
                'medium' => $urls['medium'],
                'alt_text' => $image->alt_text ?: '',
                'est_principale' => $image->est_principale,
                'ordre' => $image->ordre_affichage
            ];
        })->toArray();
    }

    public function getRelatedProducts(int $productId, int $limit = 8): array
    {
        $product = Produit::find($productId);
        
        if (!$product) {
            return [];
        }

        $related = Produit::where('est_visible', true)
            ->where('id', '!=', $productId)
            ->where('categorie_id', $product->categorie_id)
            ->with(['images_produits' => function($q) {
                $q->where('est_principale', true);
            }])
            ->orderByDesc('nombre_ventes')
            ->orderByDesc('note_moyenne')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $related->map(function ($product) {
            return $this->formatProductCard($product);
        })->toArray();
    }

    public function getWhatsAppData(int $productId): array
    {
        $product = Produit::with(['images_produits' => function($q) {
            $q->where('est_principale', true);
        }])->find($productId);

        if (!$product) {
            return ['success' => false, 'message' => 'Produit non trouvé'];
        }

        $image = $product->images_produits->first();
        $imageUrl = $image ? $this->imageUrls($image)['medium'] : null;

        $message = "Bonjour NDEYA SHOP ! 👋\n\n";
        $message .= "Je suis intéressé(e) par ce produit :\n";
        $message .= "📦 *{$product->nom}*\n";
        $message .= "💰 Prix : " . number_format($product->prix_promo ?: $product->prix, 0, ',', ' ') . " FCFA\n";
        
        if ($product->prix_promo) {
            $message .= "🏷️ Prix promotionnel !\n";
        }
        
        $message .= "\nPourriez-vous me donner plus d'informations ?\n";
        $message .= "Merci ! 🙏";

        $whatsappNumber = config('app.whatsapp_number', '221784661412');
        
        return [
            'success' => true,
            'data' => [
                'message' => $message,
                'phone' => $whatsappNumber,
                'url' => "https://wa.me/{$whatsappNumber}?text=" . urlencode($message),
                'product_image' => $imageUrl
            ]
        ];
    }

   private function formatProductDetails(Produit $product): array
{
    // Récupérer l'image principale
    $imagePrincipale = asset('assets/images/placeholder.jpg');
    $firstImage = $product->images_produits->first();
    if ($firstImage) {
        $imagePrincipale = $this->imageUrls($firstImage)['medium'];
    } elseif ($product->image_principale) {
        $imagePrincipale = asset('storage/' . $product->image_principale);
    }
    
    return [
        'id' => $product->id,
        'nom' => $product->nom,
        'slug' => $product->slug,
        'description' => $product->description,
        'description_courte' => $product->description_courte,
        'prix' => $product->prix,
        'prix_promo' => $product->prix_promo,
        'prix_affiche' => $product->prix_promo ?: $product->prix,
        'en_promo' => $product->prix_promo !== null,
        'pourcentage_reduction' => $product->prix_promo ? 
            round(((($product->prix - $product->prix_promo) / $product->prix) * 100), 0) : 0,
        'image_principale' => $imagePrincipale,  // ✅ Ajouté
        'category' => $product->category ? [
            'id' => $product->category->id,
            'nom' => $product->category->nom,
            'slug' => $product->category->slug
        ] : null,
        'images' => $product->images_produits->count() > 0
            ? $product->images_produits->map(function ($image) use ($product) {
                $urls = $this->imageUrls($image);

                return [
                    'id' => $image->id,
                    'original' => $urls['original'],
                    'thumbnail' => $urls['thumbnail'],
                    'medium' => $urls['medium'],
                    'alt_text' => $image->alt_text ?: $product->nom,
                    'est_principale' => $image->est_principale,
                    'couleur_associee' => $image->couleur_associee,
                ];
            })->toArray()
            : ($product->image_principale ? [[
                'id' => 0,
                'original' => asset('storage/' . $product->image_principale),
                'thumbnail' => asset('storage/' . $product->image_principale),
                'medium' => asset('storage/' . $product->image_principale),
                'alt_text' => $product->nom,
                'est_principale' => true,
                'couleur_associee' => null,
            ]] : [[
                'id' => 0,
                'original' => asset('assets/images/placeholder.jpg'),
                'thumbnail' => asset('assets/images/placeholder.jpg'),
                'medium' => asset('assets/images/placeholder.jpg'),
                'alt_text' => $product->nom,
                'est_principale' => true,
                'couleur_associee' => null,
            ]]),
        'images_par_couleur' => $this->groupImagesByColor($product),
        'couleur_par_defaut' => $this->getDefaultColor($product),
        'type_variante' => $product->type_variante ?? 'vetement',
        'couleur_tailles' => $product->couleur_tailles ?
            json_decode($product->couleur_tailles, true) : null,
        'couleurs_disponibles' => $product->couleurs_disponibles
            ? json_decode($product->couleurs_disponibles, true)
            : ($product->couleur_tailles ? array_keys(json_decode($product->couleur_tailles, true) ?? []) : []),
        'tailles_disponibles' => $product->tailles_disponibles
            ? json_decode($product->tailles_disponibles, true)
            : ($product->couleur_tailles ? array_unique(array_merge(...array_values(json_decode($product->couleur_tailles, true) ?? [[]]))) : []),
        'couleur_tailles_stock' => $product->couleur_tailles_stock ?
            json_decode($product->couleur_tailles_stock, true) : null,
        'couleur_tailles_seuil' => $product->couleur_tailles_seuil ?
            json_decode($product->couleur_tailles_seuil, true) : null,
        'stock_disponible' => $product->gestion_stock ? $this->resolveStockTotal($product) : null,
        'en_stock' => !$product->gestion_stock || $this->resolveStockTotal($product) > 0,
        'stock_status' => $this->getStockStatus($product),
        'fait_sur_mesure' => $product->fait_sur_mesure,
        'delai_production_jours' => $product->delai_production_jours,
        'note_moyenne' => $product->note_moyenne,
        'nombre_avis' => $product->nombre_avis,
        'tags' => is_array($product->tags) ? $product->tags : ($product->tags ? explode(',', $product->tags) : []),
        'est_nouveaute' => $product->est_nouveaute,
        'est_populaire' => $product->est_populaire,
        'seo' => $this->buildProductSeo($product, $imagePrincipale),
        'meta' => [
            'views' => $product->nombre_vues,
            'sales' => $product->nombre_ventes,
            'created_at' => $product->created_at->toISOString()
        ]
    ];
}
    private function buildProductSeo(Produit $product, string $image): array
    {
        $canonical = $this->publicUrl('/produits/' . $product->slug);
        $description = $this->seoDescription($product);
        $title = $product->meta_titre ?: Str::limit($product->nom . ' | ' . $this->shopName(), 70, '');
        $keywords = collect([
            $product->nom,
            $product->category?->nom,
            $this->shopName(),
            'boutique en ligne Senegal',
            'Dakar',
            'livraison Senegal',
        ])->merge(is_array($product->tags) ? $product->tags : ($product->tags ? explode(',', $product->tags) : []))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'canonical' => $canonical,
            'image' => $image,
            'type' => 'product',
            'structured_data' => $this->productStructuredData($product, $image, $canonical, $description),
        ];
    }

    private function productStructuredData(Produit $product, string $image, string $canonical, string $description): array
    {
        $price = $product->prix_promo ?: $product->prix;
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->nom,
            'description' => $description,
            'image' => array_values(array_filter([$image])),
            'sku' => 'ND-' . $product->id,
            'category' => $product->category?->nom,
            'brand' => [
                '@type' => 'Brand',
                'name' => $this->shopName(),
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => $canonical,
                'priceCurrency' => 'XOF',
                'price' => (string) round((float) $price),
                'itemCondition' => 'https://schema.org/NewCondition',
                'availability' => !$product->gestion_stock || $this->resolveStockTotal($product) > 0
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
            ],
        ];

        if ($product->note_moyenne > 0 && $product->nombre_avis > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round((float) $product->note_moyenne, 1),
                'reviewCount' => (int) $product->nombre_avis,
            ];
        }

        $reviews = $this->productReviews($product);
        if (!empty($reviews)) {
            $schema['review'] = $reviews;
        }

        return $schema;
    }

    /**
     * Avis réels (approuvés et visibles) au format schema.org/Review.
     * Aucune donnée fabriquée : retourne un tableau vide si le produit n'a pas d'avis.
     */
    private function productReviews(Produit $product): array
    {
        return AvisClient::where('produit_id', $product->id)
            ->where('statut', 'approuve')
            ->where('est_visible', true)
            ->where('note_globale', '>', 0)
            ->with('client:id,prenom')
            ->orderByDesc('est_mis_en_avant')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (AvisClient $avis) => array_filter([
                '@type' => 'Review',
                'reviewRating' => [
                    '@type' => 'Rating',
                    'ratingValue' => round((float) $avis->note_globale, 1),
                    'bestRating' => 5,
                    'worstRating' => 1,
                ],
                'author' => [
                    '@type' => 'Person',
                    'name' => $avis->nom_affiche ?: ($avis->client?->prenom ?: 'Client'),
                ],
                'datePublished' => $avis->created_at?->toDateString(),
                'name' => $avis->titre ?: null,
                'reviewBody' => $avis->commentaire ?: null,
            ], fn ($value) => $value !== null))
            ->all();
    }

    private function seoDescription(Produit $product): string
    {
        $description = $product->meta_description ?: $product->description_courte ?: $product->description ?: '';
        $description = preg_replace('/\s+/', ' ', trim(strip_tags((string) $description)));

        if ($description === '') {
            $description = 'Achetez ' . $product->nom . ' chez ' . $this->shopName() . ' au Senegal avec livraison a Dakar et partout au pays.';
        }

        return Str::limit($description, 160, '');
    }

    private function publicUrl(string $path): string
    {
        $base = rtrim((string) env('FRONTEND_URL', config('app.url')), '/');
        $path = '/' . ltrim($path, '/');

        return $base . $path;
    }

    private function shopName(): string
    {
        return (string) ShopSetting::getValue('boutique_nom', config('app.name', 'ND WORLD'));
    }

    private function resolveStockTotal($product): int
    {
        if ($product->couleur_tailles_stock) {
            $stockData = json_decode($product->couleur_tailles_stock, true) ?? [];
            $total = 0;
            foreach ($stockData as $tailles) {
                foreach ($tailles as $qty) {
                    $total += (int) $qty;
                }
            }
            return $total;
        }
        return (int) ($product->stock_disponible ?? 0);
    }

    private function getStockStatus($product): ?array
    {
        if (!$product->gestion_stock) {
            return ['status' => 'unlimited', 'label' => 'Non limité', 'color' => 'blue'];
        }
        $stockTotal = $this->resolveStockTotal($product);
        if ($stockTotal <= 0) {
            return ['status' => 'out_of_stock', 'label' => 'Rupture de stock', 'color' => 'red'];
        }
        $seuil = (int) ($product->seuil_alerte ?? 5);
        if ($stockTotal <= $seuil) {
            return ['status' => 'low_stock', 'label' => 'Stock limité', 'color' => 'orange'];
        }
        return ['status' => 'in_stock', 'label' => 'En stock', 'color' => 'green'];
    }

    private function formatProductCard(Produit $product): array
{
    // Chercher d'abord dans images_produits, sinon utiliser image_principale, sinon placeholder
    $image = $product->images_produits->first();
    
    $imageUrl = asset('assets/images/placeholder.jpg');
    
    if ($image) {
        $imageUrl = $this->imageUrls($image)['medium'];
    } elseif ($product->image_principale) {
        $imageUrl = asset('storage/' . $product->image_principale);
    }
    
    // Formater toutes les images disponibles
    $formattedImages = $product->images_produits->map(function ($img) {
        $urls = $this->imageUrls($img);

        return [
            'id' => $img->id,
            'url' => $urls['medium'],
            'original' => $urls['original'],
            'thumb' => $urls['thumbnail'],
            'medium' => $urls['medium'],
            'alt' => $img->alt_text ?: '',
            'est_principale' => $img->est_principale,
            'ordre' => $img->ordre_affichage
        ];
    })->toArray();
    
    return [
        'id' => $product->id,
        'nom' => $product->nom,
        'slug' => $product->slug,
        'description_courte' => $product->description_courte,
        'prix' => $product->prix,
        'prix_promo' => $product->prix_promo,
        'prix_affiche' => $product->prix_promo ?: $product->prix,
        'en_promo' => $product->prix_promo !== null,
        'image_principale' => $imageUrl,  // ✅ Correspondance avec frontend
        'image' => $imageUrl,              // ✅ Compatibilité arrière
        'images' => $formattedImages,      // ✅ Toutes les images
        'note_moyenne' => $product->note_moyenne ?? 0,
        'nombre_avis' => $product->nombre_avis ?? 0,
        'est_nouveaute' => $product->est_nouveaute,
        'est_populaire' => $product->est_populaire,
        'type_variante' => $product->type_variante ?? 'vetement',
        'stock_disponible' => $product->gestion_stock ? $this->resolveStockTotal($product) : null,
        'stock_status' => $this->getStockStatus($product),
        'url' => "/produits/{$product->slug}",
        'badge' => $this->getProductBadge($product)
    ];
}

    private function getProductBadge(Produit $product): ?array
    {
        if ($product->prix_promo) {
            $reduction = round(((($product->prix - $product->prix_promo) / $product->prix) * 100), 0);
            return ['text' => "-{$reduction}%", 'color' => 'red'];
        }
        
        if ($product->est_nouveaute || $product->created_at->gt(now()->subDays(30))) {
            return ['text' => 'Nouveau', 'color' => 'blue'];
        }
        
        if ($product->est_populaire) {
            return ['text' => 'Populaire', 'color' => 'yellow'];
        }
        
        return null;
    }

    /**
     * Regroupe les images par couleur pour l'affichage Shein-style.
     * Les images sans couleur sont placées sous la clé null.
     */
    private function groupImagesByColor(Produit $product): array
    {
        $grouped = [];

        foreach ($product->images_produits as $image) {
            $urls = $this->imageUrls($image);
            $entry = [
                'id' => $image->id,
                'original' => $urls['original'],
                'thumbnail' => $urls['thumbnail'],
                'medium' => $urls['medium'],
                'alt_text' => $image->alt_text ?: $product->nom,
                'est_principale' => $image->est_principale,
                'ordre_affichage' => $image->ordre_affichage,
            ];

            $key = $image->couleur_associee ?? '__sans_couleur__';
            $grouped[$key][] = $entry;
        }

        return $grouped;
    }

    /**
     * Retourne la couleur par défaut à sélectionner à l'ouverture de la fiche.
     * Priorité : première couleur qui a des images > première couleur disponible > null.
     */
    private function getDefaultColor(Produit $product): ?string
    {
        $couleurs = $product->couleurs_disponibles
            ? json_decode($product->couleurs_disponibles, true)
            : [];

        if (empty($couleurs)) {
            return null;
        }

        $couleursAvecImages = $product->images_produits
            ->pluck('couleur_associee')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        foreach ($couleurs as $couleur) {
            if (in_array($couleur, $couleursAvecImages, true)) {
                return $couleur;
            }
        }

        return $couleurs[0];
    }

    private function imageUrls($image): array
    {
        $placeholder = asset('assets/images/placeholder.jpg');
        $original = $image->chemin_original ? asset('storage/' . $image->chemin_original) : $placeholder;
        $medium = $image->chemin_moyen ? asset('storage/' . $image->chemin_moyen) : $original;
        $thumbnail = $image->chemin_miniature ? asset('storage/' . $image->chemin_miniature) : $medium;

        return [
            'original' => $original,
            'medium' => $medium,
            'thumbnail' => $thumbnail,
        ];
    }



    
    public function getProductDetailPageData(string $slug): array
    {
        $cacheKey = "product:page_data:{$slug}";
        $cacheTtl = config('ndeya_cache.ttl.product_detail', 7200);

        $cached = Cache::remember($cacheKey, $cacheTtl, function () use ($slug) {
            $product = Produit::where('slug', $slug)
                ->where('est_visible', true)
                ->with([
                    'category',
                    'images_produits' => function ($q) {
                        $q->orderBy('ordre_affichage');
                    }
                ])
                ->first();

            if (!$product) {
                return null;
            }

            $related = Produit::where('est_visible', true)
                ->where('id', '!=', $product->id)
                ->where('categorie_id', $product->categorie_id)
                ->with(['images_produits' => function ($q) {
                    $q->where('est_principale', true);
                }])
                ->orderByDesc('nombre_ventes')
                ->orderByDesc('note_moyenne')
                ->limit(8)
                ->get();

            return [
                'product'          => $this->formatProductDetails($product),
                'related_products' => $related->map(fn($p) => $this->formatProductCard($p))->toArray(),
                'product_id'       => $product->id,
            ];
        });

        if (!$cached) {
            return ['success' => false, 'message' => 'Produit non trouvé'];
        }

        // Incrémenter les vues hors du cache, en arrière-plan (non bloquant)
        dispatch(function () use ($cached) {
            Produit::where('id', $cached['product_id'])->increment('nombre_vues');
        })->afterResponse();

        return [
            'success' => true,
            'data'    => [
                'product'          => $cached['product'],
                'related_products' => $cached['related_products'],
            ],
        ];
    }
}
