<?php
namespace App\Services\Client;

use App\Models\Produit;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SearchService
{
    public function search(string $query, array $filters = []): array
    {
        $query = trim($query);
        
        if (strlen($query) < 2) {
            return [
                'products' => [],
                'categories' => [],
                'suggestions' => [],
                'total' => 0
            ];
        }

        // Recherche produits
        $productsQuery = Produit::where('est_visible', true)
            ->where(function($q) use ($query) {
                $q->where('nom', 'ILIKE', "%{$query}%")
                  ->orWhere('description', 'ILIKE', "%{$query}%")
                  ->orWhereRaw('tags::text ILIKE ?', ["%{$query}%"]);
            })
            ->with(['category', 'images_produits' => function($q) {
                $q->where('est_principale', true);
            }]);

        // Appliquer les filtres
        if (isset($filters['category_id'])) {
            $productsQuery->where('categorie_id', $filters['category_id']);
        }

        if (isset($filters['min_price'])) {
            $productsQuery->where('prix', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $productsQuery->where('prix', '<=', $filters['max_price']);
        }

        if (isset($filters['on_sale']) && $filters['on_sale']) {
            $productsQuery->whereNotNull('prix_promo');
        }

        $perPage = $filters['per_page'] ?? 20;
        $products = $productsQuery->orderBy('nombre_vues', 'desc')
            ->paginate($perPage);

        // Recherche catégories
        $categories = Category::where('est_active', true)
            ->where('nom', 'ILIKE', "%{$query}%")
            ->limit(5)
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'nom' => $category->nom,
                    'slug' => $category->slug,
                    'url' => "/categories/{$category->slug}"
                ];
            });

        return [
            'products' => $products->items(),
            'categories' => $categories->toArray(),
            'suggestions' => $this->getSuggestions($query),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total()
            ]
        ];
    }

    public function getSuggestions(string $query): array
    {
        if (strlen($query) < 2) {
            return $this->getPopularSearches();
        }

        // Suggestions basées sur les tags produits
        $suggestions = DB::table('produits')
            ->whereNotNull('tags')
            ->where('est_visible', true)
            ->whereRaw('tags::text ILIKE ?', ["%{$query}%"])
            ->limit(20)
            ->pluck('tags')
            ->flatMap(function ($tagString) use ($query) {
                $tags = explode(',', $tagString);
                return array_filter($tags, function($tag) use ($query) {
                    return stripos(trim($tag), $query) !== false;
                });
            })
            ->unique()
            ->take(5)
            ->values()
            ->toArray();

        return array_map('trim', $suggestions);
    }

    public function getPopularSearches(): array
    {
        return Cache::remember('popular_searches', 3600, function () {
            return [
                'robe africaine',
                'boubou homme',
                'kaftan brodé',
                'ensemble wax',
                'tenue traditionnelle',
                'style moderne'
            ];
        });
    }

    public function saveSearch(string $query, ?int $userId = null): void
    {
        // Log des recherches pour analytics futures.
        // Best-effort : un échec d'analytics (ex: table search_logs absente — il
        // n'existe aucune migration) ne doit JAMAIS faire échouer la recherche.
        try {
            DB::table('search_logs')->insert([
                'query' => $query,
                'user_id' => $userId,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now()
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'saveSearch ignoré (analytics): ' . $e->getMessage()
            );
        }
    }
}
