<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Services\Client\HomeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    protected HomeService $homeService;

    public function __construct(HomeService $homeService)
    {
        $this->homeService = $homeService;
    }

    /**
     * Cache avec protection anti-stampede (atomic lock).
     * Sans ça, 200 VUs simultanés ratent le cache en même temps
     * et lancent tous un rebuild DB → saturation totale.
     */
    private function lockedCache(string $key, int $ttl, callable $callback): mixed
    {
        $data = Cache::get($key);
        if ($data !== null) {
            return $data;
        }

        // Un seul worker reconstruit le cache, les autres attendent max 8s
        return Cache::lock('build_' . $key, 30)
            ->block(8, function () use ($key, $ttl, $callback) {
                // Double-check : un autre worker a peut-être déjà reconstruit
                return Cache::remember($key, $ttl, $callback);
            });
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $data = $this->lockedCache('client_home_data', 600, function () {
                return $this->homeService->getHomeData();
            });

            return response()->json([
                'success' => true,
                'message' => 'Données d\'accueil récupérées avec succès',
                'data'    => $data,
                'meta'    => ['timestamp' => now()->toISOString(), 'cache_expires_in' => 600],
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur page d\'accueil client', [
                'error' => $e->getMessage(),
                'ip'    => $request->ip(),
            ]);
            return response()->json([
                'success'    => false,
                'message'    => 'Erreur lors du chargement de la page d\'accueil',
                'error_code' => 'HOME_LOAD_ERROR',
            ], 500);
        }
    }

    public function featuredProducts(Request $request): JsonResponse
    {
        try {
            $limit = (int) $request->get('limit', 8);
            $data  = $this->lockedCache("home:featured_products:{$limit}", 300, function () use ($limit) {
                $produits = $this->homeService->getFeaturedProducts($limit);
                return ['produits' => $produits, 'total' => count($produits)];
            });
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('Erreur produits en vedette', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur lors du chargement des produits en vedette'], 500);
        }
    }

    public function newArrivals(Request $request): JsonResponse
    {
        try {
            $limit = (int) $request->get('limit', 8);
            $data  = $this->lockedCache("home:new_arrivals:{$limit}", 300, function () use ($limit) {
                $produits = $this->homeService->getNewArrivals($limit);
                return ['produits' => $produits, 'total' => count($produits)];
            });
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('Erreur nouveautés', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur lors du chargement des nouveautés'], 500);
        }
    }

    public function productsOnSale(Request $request): JsonResponse
    {
        try {
            $limit = (int) $request->get('limit', 8);
            $data  = $this->lockedCache("home:products_on_sale:{$limit}", 300, function () use ($limit) {
                $produits = $this->homeService->getProductsOnSale($limit);
                return [
                    'produits'     => $produits,
                    'total'        => count($produits),
                    'savings_info' => $this->homeService->calculateTotalSavings($produits),
                ];
            });
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('Erreur produits en promo', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur lors du chargement des produits en promotion'], 500);
        }
    }

    public function categoriesPreview(): JsonResponse
    {
        try {
            $categories = $this->lockedCache('home:categories_preview', 600, function () {
                return $this->homeService->getCategoriesPreview();
            });
            return response()->json(['success' => true, 'data' => ['categories' => $categories]]);
        } catch (\Exception $e) {
            Log::error('Erreur aperçu catégories', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur lors du chargement des catégories'], 500);
        }
    }

    public function activePromotions(): JsonResponse
    {
        try {
            $data = $this->lockedCache('home:active_promotions', 300, function () {
                return [
                    'promotions'     => $this->homeService->getActivePromotions(),
                    'has_flash_sale' => $this->homeService->hasFlashSale(),
                    'product_promos' => $this->homeService->getProductPromoStats(),
                ];
            });
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('Erreur promotions actives', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur lors du chargement des promotions'], 500);
        }
    }

    public function shopStats(): JsonResponse
    {
        try {
            $stats = $this->lockedCache('home:shop_stats', 3600, function () {
                return $this->homeService->getPublicShopStats();
            });
            return response()->json(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            Log::error('Erreur stats boutique', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur lors du chargement des statistiques'], 500);
        }
    }

    public function testimonials(Request $request): JsonResponse
    {
        try {
            $limit = (int) $request->get('limit', 6);
            $data  = $this->lockedCache("home:testimonials:{$limit}", 1800, function () use ($limit) {
                return [
                    'testimonials'   => $this->homeService->getFeaturedTestimonials($limit),
                    'average_rating' => $this->homeService->getAverageRating(),
                ];
            });
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('Erreur témoignages', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur lors du chargement des témoignages'], 500);
        }
    }

    public function subscribeNewsletter(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email'  => 'required|email|max:255',
                'nom'    => 'nullable|string|max:100',
                'prenom' => 'nullable|string|max:100',
            ]);

            $result = $this->homeService->subscribeToNewsletter($validated);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
            ], $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            Log::error('Erreur inscription newsletter', [
                'error' => $e->getMessage(),
                'email' => $request->get('email'),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur lors de l\'inscription à la newsletter'], 500);
        }
    }

    public function quickSearch(Request $request): JsonResponse
    {
        try {
            $query = $request->get('q', '');

            if (strlen($query) < 2) {
                return response()->json([
                    'success' => true,
                    'data'    => ['produits' => [], 'categories' => [], 'suggestions' => []],
                ]);
            }

            $results = $this->homeService->quickSearch($query);
            return response()->json(['success' => true, 'data' => $results]);

        } catch (\Exception $e) {
            Log::error('Erreur recherche rapide', [
                'error' => $e->getMessage(),
                'query' => $request->get('q'),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur lors de la recherche'], 500);
        }
    }
}
