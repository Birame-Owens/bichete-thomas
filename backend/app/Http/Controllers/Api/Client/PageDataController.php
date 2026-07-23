<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Services\Client\HomeService;
use App\Services\Client\CartService;
use App\Services\Client\WishlistService;
use App\Services\Client\NavigationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * ✅ CONTRÔLEUR OPTIMISÉ POUR DONNÉES COMBINÉES
 * 
 * Objectif: Réduire le nombre de requêtes HTTP en combinant
 * plusieurs endpoints en une seule réponse
 */
class PageDataController extends Controller
{
    public function __construct(
        private HomeService $homeService,
        private CartService $cartService,
        private WishlistService $wishlistService,
        private NavigationService $navigationService
    ) {}

    /**
     * ✅ DONNÉES INITIALES DE L'APPLICATION
     * Combine: config + navigation + compteurs
     */
    public function getInitialData(Request $request)
    {
        $cacheKey = 'app_initial_data_' . ($request->user()?->id ?? 'guest');
        
        return Cache::remember($cacheKey, 300, function () use ($request) { // 5 minutes
            $data = [
                // Configuration de base (mise en cache longue durée)
                'config' => $this->getAppConfig(),
                
                // Navigation (mise en cache moyenne durée)
                'navigation' => $this->navigationService->getMainMenu(),
                
                // Compteurs utilisateur (temps réel si connecté)
                'counters' => $this->getUserCounters($request),
                
                // Métadonnées
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => config('app.version', '1.0.0'),
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'cached' => false
            ]);
        });
    }

    /**
     * ✅ DONNÉES COMPLÈTES DE LA PAGE D'ACCUEIL
     * Combine: produits + promotions + stats + témoignages
     */
    public function getHomePageData(Request $request)
    {
        $cacheKey = 'home_page_data_' . now()->format('Y-m-d-H'); // Cache par heure
        
        return Cache::remember($cacheKey, 3600, function () { // 1 heure
            try {
                // ✅ Chargement parallèle des données
                $homeData = $this->homeService->getHomeData();
                
                $data = [
                    // Sections principales
                    'hero' => $homeData['hero'] ?? null,
                    'featured_products' => $homeData['featured_products'] ?? [],
                    'new_arrivals' => $homeData['new_arrivals'] ?? [],
                    'products_on_sale' => $homeData['products_on_sale'] ?? [],
                    'categories_preview' => $homeData['categories_preview'] ?? [],
                    
                    // Promotions actives
                    'active_promotions' => $homeData['active_promotions'] ?? [],
                    
                    // Stats et social proof
                    'shop_stats' => $homeData['shop_stats'] ?? null,
                    'testimonials' => $homeData['testimonials'] ?? [],
                    
                    // Métadonnées SEO
                    'seo' => [
                        'title' => 'NDEYA SHOP - Mode Africaine Authentique',
                        'description' => 'Découvrez notre collection de vêtements africains authentiques, faits sur mesure par nos tailleurs experts.',
                        'keywords' => 'mode africaine, vêtements sur mesure, tailleurs, Sénégal, NDEYA',
                    ]
                ];

                return response()->json([
                    'success' => true,
                    'data' => $data,
                    'cached' => false
                ]);

            } catch (\Exception $e) {
                \Log::error('Erreur chargement données accueil: ' . $e->getMessage());
                
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors du chargement des données',
                    'data' => $this->getFallbackHomeData()
                ], 500);
            }
        });
    }

    /**
     * ✅ DONNÉES COMBINÉES POUR PAGE PRODUIT
     * Combine: produit + images + avis + produits liés
     */
    public function getProductPageData(Request $request, $slug)
    {
        $cacheKey = "product_page_data_{$slug}";
        
        return Cache::remember($cacheKey, 1800, function () use ($slug, $request) { // 30 minutes
            try {
                // Charger le produit principal
                $product = \App\Models\Produit::where('slug', $slug)
                    ->where('est_visible', true)
                    ->with(['category', 'images_produits', 'avis_clients.client'])
                    ->firstOrFail();

                // Incrémenter les vues (asynchrone)
                dispatch(function () use ($product) {
                    $product->increment('nombre_vues');
                });

                // Charger les données liées
                $relatedProducts = \App\Models\Produit::where('categorie_id', $product->categorie_id)
                    ->where('id', '!=', $product->id)
                    ->where('est_visible', true)
                    ->limit(8)
                    ->get();

                $data = [
                    'product' => $product,
                    'related_products' => $relatedProducts,
                    'breadcrumb' => [
                        ['name' => 'Accueil', 'url' => '/'],
                        ['name' => $product->category->nom, 'url' => "/categories/{$product->category->slug}"],
                        ['name' => $product->nom, 'url' => null]
                    ],
                    'seo' => [
                        'title' => $product->meta_titre ?: $product->nom,
                        'description' => $product->meta_description ?: $product->description_courte,
                        'image' => $product->image_principale,
                    ]
                ];

                return response()->json([
                    'success' => true,
                    'data' => $data
                ]);

            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produit non trouvé'
                ], 404);
            }
        });
    }

    /**
     * ✅ DONNÉES UTILISATEUR TEMPS RÉEL
     * Combine: panier + wishlist + profil
     */
    public function getUserData(Request $request)
    {
        if (!$request->user()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'cart_count' => 0,
                    'wishlist_count' => 0,
                    'is_authenticated' => false
                ]
            ]);
        }

        try {
            $cartCount = $this->cartService->getCartCount($request->user());
            $wishlistCount = $this->wishlistService->getWishlistCount($request->user());

            return response()->json([
                'success' => true,
                'data' => [
                    'cart_count' => $cartCount,
                    'wishlist_count' => $wishlistCount,
                    'is_authenticated' => true,
                    'user' => [
                        'name' => $request->user()->name,
                        'email' => $request->user()->email,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des données utilisateur'
            ], 500);
        }
    }

    /**
     * ✅ MÉTHODES PRIVÉES
     */
    private function getAppConfig()
    {
        return Cache::remember('app_config', 86400, function () { // 24 heures
            return [
                'company' => [
                    'name' => config('app.name', 'NDEYA SHOP'),
                    'whatsapp' => config('services.whatsapp.number', '+221784661412'),
                    'email' => config('mail.from.address', 'contact@ndeyashop.sn'),
                    'phone' => config('services.phone.number', '+221784661412'),
                    'address' => config('company.address', 'Dakar, Sénégal')
                ],
                'currency' => 'FCFA',
                'shipping' => [
                    'free_threshold' => 50000,
                    'default_fee' => 2500
                ],
                'social' => [
                    'facebook' => config('services.social.facebook'),
                    'instagram' => config('services.social.instagram'),
                    'twitter' => config('services.social.twitter'),
                ]
            ];
        });
    }

    private function getUserCounters(Request $request)
    {
        if (!$request->user()) {
            return ['cart_count' => 0, 'wishlist_count' => 0];
        }

        return [
            'cart_count' => $this->cartService->getCartCount($request->user()),
            'wishlist_count' => $this->wishlistService->getWishlistCount($request->user()),
        ];
    }

    private function getFallbackHomeData()
    {
        return [
            'featured_products' => [],
            'new_arrivals' => [],
            'products_on_sale' => [],
            'categories_preview' => [],
            'message' => 'Données temporairement indisponibles'
        ];
    }
}
