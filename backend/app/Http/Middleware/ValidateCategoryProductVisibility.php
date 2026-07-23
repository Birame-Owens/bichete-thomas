<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Produit;

class ValidateCategoryProductVisibility
{
    /**
     * Middleware pour valider la visibilité catégorie-produits côté client
     * 
     * Assure que:
     * - Un produit visible ne doit être dans une catégorie active
     * - Une catégorie visible doit avoir au moins un produit visible
     */
    public function handle(Request $request, Closure $next)
    {
        // Ne valider que les requêtes vers l'API client pour éviter les surcharges
        if (str_contains($request->path(), 'api/client')) {
            $this->validateProductsInCategories();
            $this->validateCategoriesHaveVisibleProducts();
        }

        return $next($request);
    }

    /**
     * Assure que les produits visibles sont dans des catégories actives
     */
    private function validateProductsInCategories(): void
    {
        // Récupérer les produits visibles dont la catégorie est inactive
        $invalidProducts = Produit::where('est_visible', true)
            ->whereHas('category', function ($q) {
                $q->where('est_active', false);
            })
            ->pluck('id')
            ->toArray();

        if (!empty($invalidProducts)) {
            // Désactiver automatiquement les produits
            Produit::whereIn('id', $invalidProducts)
                ->update(['est_visible' => false]);

            \Log::warning('Produits désactivés car leur catégorie est inactive', [
                'product_ids' => $invalidProducts,
                'count' => count($invalidProducts)
            ]);
        }
    }

    /**
     * Assure que les catégories inactives mais listées ont du contenu
     */
    private function validateCategoriesHaveVisibleProducts(): void
    {
        // Récupérer les catégories actives sans produits visibles
        $categoriesWithoutProducts = Category::where('est_active', true)
            ->whereDoesntHave('produits', function ($q) {
                $q->where('est_visible', true);
            })
            ->count();

        if ($categoriesWithoutProducts > 0) {
            \Log::info('Catégories sans produits visibles détectées', [
                'count' => $categoriesWithoutProducts
            ]);
        }
    }
}
