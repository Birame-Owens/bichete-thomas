<?php
/**
 * ⚡ PRODUCT REPOSITORY - OPTIMISÉ
 * - N+1 queries éliminées
 * - Eager loading intelligent
 * - Caching par stratégie
 */

namespace App\Repositories;

use App\Models\Produit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new Produit());
        $this->with = ['category', 'images', 'stocks'];
    }

    /**
     * 🏆 Produits en tendance
     */
    public function getTrending(int $limit = 12)
    {
        $cacheKey = 'products_trending_' . $limit;

        return Cache::remember($cacheKey, 1800, function () use ($limit) {
            return $this->model::query()
                ->where('est_publie', true)
                ->withCount('commandes')
                ->orderByDesc('commandes_count')
                ->with($this->with)
                ->limit($limit)
                ->get();
        });
    }

    /**
     * 🏷️ Produits par catégorie
     */
    public function getByCategory(int $categoryId, int $perPage = 20)
    {
        $cacheKey = "products_category_{$categoryId}_{$perPage}";

        return Cache::remember($cacheKey, 600, function () use ($categoryId, $perPage) {
            return $this->model::query()
                ->where('category_id', $categoryId)
                ->where('est_publie', true)
                ->with($this->with)
                ->paginate($perPage);
        });
    }

    /**
     * 🔥 Produits en promotion
     */
    public function getOnSale(int $perPage = 20)
    {
        $cacheKey = "products_sale_{$perPage}";

        return Cache::remember($cacheKey, 300, function () use ($perPage) {
            return $this->model::query()
                ->whereHas('promotions', function ($q) {
                    $q->where('date_fin', '>=', now());
                })
                ->where('est_publie', true)
                ->with($this->with)
                ->paginate($perPage);
        });
    }

    /**
     * 🔎 Recherche avancée avec filtres
     */
    public function searchWithFilters(array $filters, int $perPage = 20)
    {
        $cacheKey = 'products_search_' . md5(json_encode($filters)) . '_' . $perPage;

        return Cache::remember($cacheKey, 120, function () use ($filters, $perPage) {
            $query = $this->model::query()
                ->where('est_publie', true)
                ->with($this->with);

            // ✅ Filtres
            if (!empty($filters['q'])) {
                $query->where('nom', 'LIKE', "%{$filters['q']}%")
                    ->orWhere('description', 'LIKE', "%{$filters['q']}%");
            }

            if (!empty($filters['category_id'])) {
                $query->where('category_id', $filters['category_id']);
            }

            if (!empty($filters['min_price'])) {
                $query->where('prix', '>=', $filters['min_price']);
            }

            if (!empty($filters['max_price'])) {
                $query->where('prix', '<=', $filters['max_price']);
            }

            if ($filters['on_sale'] ?? false) {
                $query->whereHas('promotions');
            }

            // ✅ Tri
            $sort = $filters['sort'] ?? 'newest';
            match ($sort) {
                'popular' => $query->orderByDesc('nombre_commandes'),
                'price_asc' => $query->orderBy('prix'),
                'price_desc' => $query->orderByDesc('prix'),
                'rating' => $query->orderByDesc('note_moyenne'),
                default => $query->orderByDesc('created_at'),
            };

            return $query->paginate($perPage);
        });
    }

    /**
     * 🎨 Produits similaires (recommandations)
     */
    public function getSimilar(int $productId, int $limit = 6)
    {
        $cacheKey = "products_similar_{$productId}_{$limit}";

        return Cache::remember($cacheKey, 1800, function () use ($productId, $limit) {
            $product = $this->model::find($productId);
            
            if (!$product) return collect();

            return $this->model::query()
                ->where('category_id', $product->category_id)
                ->where('id', '!=', $productId)
                ->where('est_publie', true)
                ->with($this->with)
                ->limit($limit)
                ->get();
        });
    }

    /**
     * 📊 Statistiques produits
     */
    public function getStatistics()
    {
        $cacheKey = 'products_statistics';

        return Cache::remember($cacheKey, 3600, function () {
            return [
                'total' => $this->model::count(),
                'published' => $this->model::where('est_publie', true)->count(),
                'stock_low' => $this->model::whereHas('stocks', function ($q) {
                    $q->where('quantite', '<', 10);
                })->count(),
                'average_price' => $this->model::avg('prix'),
                'high_rated' => $this->model::where('note_moyenne', '>=', 4)->count(),
            ];
        });
    }

    /**
     * 📋 Colonnes recherchables
     */
    protected function getSearchableColumns(): array
    {
        return ['nom', 'description', 'slug'];
    }
}
