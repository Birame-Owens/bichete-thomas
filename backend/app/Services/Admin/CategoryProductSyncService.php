<?php

namespace App\Services\Admin;

use App\Models\Category;
use App\Models\Produit;
use Illuminate\Support\Facades\Log;

class CategoryProductSyncService
{
    /**
     * Synchronise l'état de visibilité catégories-produits
     * Assure la cohérence: un produit visible doit être dans une catégorie active
     */
    public function syncVisibility(): array
    {
        $report = [
            'products_deactivated' => 0,
            'categories_checked' => 0,
            'categories_without_products' => 0,
            'errors' => [],
        ];

        try {
            // 1. Désactiver les produits dont la catégorie est inactive
            $report['products_deactivated'] = $this->deactivateProductsInInactiveCategories();

            // 2. Lister les catégories sans produits visibles
            $report['categories_without_products'] = $this->getCategoriesWithoutVisibleProducts();

            // 3. Vérifier l'intégrité générale
            $report['categories_checked'] = Category::count();

            Log::info('Synchronisation catégories-produits effectuée', $report);

        } catch (\Exception $e) {
            $report['errors'][] = $e->getMessage();
            Log::error('Erreur lors de la synchronisation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $report;
    }

    /**
     * Désactive les produits visibles dont la catégorie est inactive
     */
    private function deactivateProductsInInactiveCategories(): int
    {
        $affected = Produit::where('est_visible', true)
            ->whereHas('category', function ($q) {
                $q->where('est_active', false);
            })
            ->update(['est_visible' => false]);

        if ($affected > 0) {
            Log::warning("$affected produit(s) désactivé(s) - catégorie inactive", [
                'timestamp' => now()
            ]);
        }

        return $affected;
    }

    /**
     * Obtient les catégories sans produits visibles
     */
    private function getCategoriesWithoutVisibleProducts(): int
    {
        return Category::where('est_active', true)
            ->whereDoesntHave('produits', function ($q) {
                $q->where('est_visible', true);
            })
            ->count();
    }

    /**
     * Vérifie et affiche le statut de visibilité côté client pour une catégorie
     */
    public function getCategoryClientVisibility(Category $category): array
    {
        $visibleProducts = $category->produits()
            ->where('est_visible', true)
            ->count();

        $totalProducts = $category->produits()->count();

        return [
            'category_id' => $category->id,
            'category_name' => $category->nom,
            'category_active' => $category->est_active,
            'total_products' => $totalProducts,
            'visible_products' => $visibleProducts,
            'will_appear_on_client' => $category->est_active && $visibleProducts > 0,
            'reason' => $this->getVisibilityReason($category, $visibleProducts),
            'products_list' => $category->produits()
                ->select('id', 'nom', 'est_visible')
                ->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'nom' => $product->nom,
                        'est_visible' => $product->est_visible,
                    ];
                }),
        ];
    }

    /**
     * Retourne les raison de visibilité/invisibilité
     */
    private function getVisibilityReason(Category $category, int $visibleProducts): string
    {
        if (!$category->est_active) {
            return 'Catégorie désactivée dans l\'admin';
        }

        if ($visibleProducts === 0) {
            return 'Aucun produit visible dans cette catégorie';
        }

        return 'Catégorie et produits visibles côté client';
    }

    /**
     * Génère un rapport détaillé de toutes les catégories
     */
    public function generateFullReport(): array
    {
        $categories = Category::with('produits:id,categorie_id,nom,est_visible')
            ->orderBy('ordre_affichage')
            ->get();

        $report = [
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'total_categories' => $categories->count(),
            'total_products' => Produit::count(),
            'categories' => []
        ];

        foreach ($categories as $category) {
            $visibleProducts = $category->produits->where('est_visible', true)->count();
            $report['categories'][] = [
                'id' => $category->id,
                'nom' => $category->nom,
                'slug' => $category->slug,
                'est_active' => $category->est_active,
                'total_products' => $category->produits->count(),
                'visible_products' => $visibleProducts,
                'visible_on_client' => $category->est_active && $visibleProducts > 0,
                'reason' => $this->getVisibilityReason($category, $visibleProducts),
            ];
        }

        return $report;
    }

    /**
     * Réinitialise la visibilité par catégorie
     */
    public function resetCategoryVisibility(int $categoryId): array
    {
        $category = Category::findOrFail($categoryId);
        
        // Vérifier si la catégorie est active
        if (!$category->est_active) {
            // Désactiver tous les produits de cette catégorie
            $affected = $category->produits()
                ->where('est_visible', true)
                ->update(['est_visible' => false]);

            Log::info('Produits désactivés - catégorie inactive', [
                'category_id' => $categoryId,
                'affected' => $affected
            ]);

            return [
                'success' => true,
                'message' => "$affected produit(s) désactivé(s) car la catégorie est inactive",
                'affected' => $affected
            ];
        }

        return [
            'success' => true,
            'message' => 'Catégorie active - aucune action requise',
            'affected' => 0
        ];
    }
}
