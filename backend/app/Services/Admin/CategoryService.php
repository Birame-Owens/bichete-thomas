<?php

namespace App\Services\Admin;

use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

class CategoryService
{
    /**
     * Créer une nouvelle catégorie
     */
    public function createCategory(array $data, ?UploadedFile $image = null): Category
    {
        // Génération automatique du slug
        $data['slug'] = $this->generateUniqueSlug($data['nom']);
        
        // Gestion de l'image
        if ($image) {
            $data['image'] = $this->handleImageUpload($image);
        }

        // Valeurs par défaut
        $data['ordre_affichage'] = $data['ordre_affichage'] ?? $this->getNextOrderValue();
        
        $category = Category::create($data);
        
        Log::info('Catégorie créée', [
            'category_id' => $category->id,
            'nom' => $category->nom,
            'user_id' => auth()->id()
        ]);

        return $category;
    }

    /**
     * Mettre à jour une catégorie
     */
    public function updateCategory(Category $category, array $data, ?UploadedFile $image = null): Category
    {
        // Mise à jour du slug si le nom change
        if (isset($data['nom']) && $data['nom'] !== $category->nom) {
            $data['slug'] = $this->generateUniqueSlug($data['nom'], $category->id);
        }

        // Gestion de l'image
        if ($image) {
            // Supprimer l'ancienne image
            if ($category->image) {
                $this->deleteImage($category->image);
            }
            $data['image'] = $this->handleImageUpload($image);
        }

        $category->update($data);

        Log::info('Catégorie mise à jour', [
            'category_id' => $category->id,
            'nom' => $category->nom,
            'user_id' => auth()->id()
        ]);

        return $category->fresh();
    }

    /**
     * Supprimer une catégorie
     */
    public function deleteCategory(Category $category): bool
    {
        // Vérifier s'il y a des produits associés
        $produitsCount = $category->produits()->count();
        
        if ($produitsCount > 0) {
            throw new \Exception("Impossible de supprimer cette catégorie car elle contient {$produitsCount} produit(s).");
        }

        // Supprimer l'image
        if ($category->image) {
            $this->deleteImage($category->image);
        }

        $categoryName = $category->nom;
        $deleted = $category->delete();

        if ($deleted) {
            Log::info('Catégorie supprimée', [
                'category_name' => $categoryName,
                'user_id' => auth()->id()
            ]);
        }

        return $deleted;
    }

    /**
     * Basculer le statut d'une catégorie
     */
    public function toggleCategoryStatus(Category $category): Category
    {
        $category->update(['est_active' => !$category->est_active]);
        
        $status = $category->est_active ? 'activée' : 'désactivée';
        
        Log::info("Catégorie {$status}", [
            'category_id' => $category->id,
            'nom' => $category->nom,
            'user_id' => auth()->id()
        ]);

        return $category;
    }

    /**
     * Obtenir les statistiques d'une catégorie
     */
    public function getCategoryStats(Category $category): array
    {
        return [
            'total_produits' => $category->produits()->count(),
            'produits_visibles' => $category->produits()->where('est_visible', true)->count(),
            'produits_en_stock' => $category->produits()->where('stock_disponible', '>', 0)->count(),
            'valeur_stock_total' => $category->produits()->sum(\DB::raw('stock_disponible * prix')),
            'produits_populaires' => $category->produits()->where('est_populaire', true)->count(),
        ];
    }

    // ========== MÉTHODES PRIVÉES ==========

    /**
     * Générer un slug unique
     */
    private function generateUniqueSlug(string $nom, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($nom);
        $slug = $baseSlug;
        $counter = 1;

        while ($this->slugExists($slug, $excludeId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Vérifier si un slug existe
     */
    private function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = Category::where('slug', $slug);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Obtenir la prochaine valeur d'ordre
     */
    private function getNextOrderValue(): int
    {
        return Category::max('ordre_affichage') + 1;
    }

    /**
     * Gérer l'upload d'image
     */
    private function handleImageUpload(UploadedFile $image): string
    {
        // Générer un nom unique
        $filename = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
        
        // Stocker l'image
        $path = $image->storeAs('categories', $filename, 'public');
        
        return $path;
    }

    /**
     * Supprimer une image
     */
    private function deleteImage(string $imagePath): bool
    {
        if (Storage::disk('public')->exists($imagePath)) {
            return Storage::disk('public')->delete($imagePath);
        }
        
        return false;
    }
}