<?php
// ================================================================
// 📝 FICHIER: app/Services/Client/NavigationService.php
// ================================================================

namespace App\Services\Client;

use App\Models\Category;
use App\Models\Produit;
use Illuminate\Support\Facades\Cache;

class NavigationService
{
    public function getMainMenu(): array
    {
        return Cache::remember('client_main_menu', 1800, function () {
            $categories = Category::where('est_active', true)
                ->whereNull('parent_id')
                ->with(['categories' => function($query) {
                    $query->where('est_active', true)->limit(5);
                }])
                ->withCount('produits')
                ->orderBy('ordre_affichage')
                ->get();

            return $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'nom' => $category->nom,
                    'slug' => $category->slug,
                    'image' => $category->image ? asset('storage/' . $category->image) : null,
                    'couleur_theme' => $category->couleur_theme,
                    'produits_count' => $category->produits_count,
                    'sous_categories' => $category->categories->map(function ($subCat) {
                        return [
                            'id' => $subCat->id,
                            'nom' => $subCat->nom,
                            'slug' => $subCat->slug,
                            'url' => "/categories/{$subCat->slug}"
                        ];
                    })->toArray(),
                    'url' => "/categories/{$category->slug}"
                ];
            })->toArray();
        });
    }

    public function getCategoryPreview(string $slug): array
    {
        $category = Category::where('slug', $slug)
            ->where('est_active', true)
            ->first();

        if (!$category) {
            return ['products' => [], 'category' => null];
        }

        $products = Produit::where('categorie_id', $category->id)
            ->where('est_visible', true)
            ->with(['images_produits' => function($q) {
                $q->where('est_principale', true);
            }])
            ->limit(6)
            ->get()
            ->map(function ($product) {
                return $this->formatProductForPreview($product);
            });

        return [
            'category' => [
                'id' => $category->id,
                'nom' => $category->nom,
                'description' => $category->description,
                'image' => $category->image ? asset('storage/' . $category->image) : null
            ],
            'products' => $products->toArray()
        ];
    }

    private function formatProductForPreview(Produit $product): array
    {
        $image = $product->images_produits->first();
        
        return [
            'id' => $product->id,
            'nom' => $product->nom,
            'slug' => $product->slug,
            'prix' => $product->prix,
            'prix_promo' => $product->prix_promo,
            'prix_affiche' => $product->prix_promo ?: $product->prix,
            'image' => $image ? asset('storage/' . ($image->chemin_moyen ?: $image->chemin_miniature ?: $image->chemin_original)) : '/images/placeholder-product.jpg',
            'url' => "/produits/{$product->slug}"
        ];
    }
}
