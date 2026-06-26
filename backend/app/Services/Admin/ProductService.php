<?php

namespace App\Services\Admin;

use App\Models\Produit;
use App\Models\ImagesProduit;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

class ProductService
{
    /**
     * Créer un nouveau produit
     */
    public function createProduct(array $data, ?UploadedFile $mainImage = null, array $additionalImages = []): Produit
    {
        DB::beginTransaction();

        try {
            // Génération automatique du slug
            $data['slug'] = $this->generateUniqueSlug($data['nom']);
            
            // Gestion de l'image principale
            if ($mainImage) {
                $data['image_principale'] = $this->handleImageUpload($mainImage, 'produits');
            }

            // Traitement des données JSON
            $data = $this->processJsonFields($data);

            // Valeurs par défaut
            $data['ordre_affichage'] = $data['ordre_affichage'] ?? $this->getNextOrderValue();
            
            $produit = Produit::create($data);

            // Gestion des images multiples
            if (!empty($additionalImages)) {
                $this->handleMultipleImages($produit, $additionalImages);
            }

            DB::commit();

            Log::info('Produit créé', [
                'produit_id' => $produit->id,
                'nom' => $produit->nom,
                'user_id' => auth()->id()
            ]);

            return $produit->load(['category', 'images_produits']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Mettre à jour un produit
     */
    public function updateProduct(Produit $produit, array $data, ?UploadedFile $mainImage = null, array $additionalImages = []): Produit
    {
        DB::beginTransaction();

        try {
            // Mise à jour du slug si le nom change
            if (isset($data['nom']) && $data['nom'] !== $produit->nom) {
                $data['slug'] = $this->generateUniqueSlug($data['nom'], $produit->id);
            }

            // Gestion de l'image principale
            if ($mainImage) {
                if ($produit->image_principale) {
                    $this->deleteImage($produit->image_principale);
                }
                $data['image_principale'] = $this->handleImageUpload($mainImage, 'produits');
            }

            // Traitement des données JSON
            $data = $this->processJsonFields($data);

            $produit->update($data);

            // Gestion des nouvelles images
            if (!empty($additionalImages)) {
                $this->handleMultipleImages($produit, $additionalImages);
            }

            DB::commit();

            Log::info('Produit mis à jour', [
                'produit_id' => $produit->id,
                'nom' => $produit->nom,
                'user_id' => auth()->id()
            ]);

            return $produit->fresh(['category', 'images_produits']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Supprimer un produit
     */
    public function deleteProduct(Produit $produit): bool
    {
        DB::beginTransaction();

        try {
            // Vérifier s'il y a des commandes associées
            $commandesCount = $produit->articles_commandes()->count();
            
            if ($commandesCount > 0) {
                throw new \Exception("Impossible de supprimer ce produit car il est associé à {$commandesCount} commande(s).");
            }

            // Supprimer toutes les images
            $this->deleteAllProductImages($produit);

            $produitNom = $produit->nom;
            $deleted = $produit->delete();

            if ($deleted) {
                Log::info('Produit supprimé', [
                    'produit_nom' => $produitNom,
                    'user_id' => auth()->id()
                ]);
            }

            DB::commit();
            return $deleted;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Dupliquer un produit
     */
    public function duplicateProduct(Produit $produit): Produit
    {
        DB::beginTransaction();

        try {
            $newProduit = $produit->replicate();
            $newProduit->nom = $produit->nom . ' (Copie)';
            $newProduit->slug = $this->generateUniqueSlug($newProduit->nom);
            $newProduit->est_visible = false; // Créer en mode brouillon
            $newProduit->save();

            // Dupliquer les images
            foreach ($produit->images_produits as $image) {
                $this->duplicateProductImage($image, $newProduit);
            }

            DB::commit();

            Log::info('Produit dupliqué', [
                'produit_original_id' => $produit->id,
                'nouveau_produit_id' => $newProduit->id,
                'user_id' => auth()->id()
            ]);

            return $newProduit->load(['category', 'images_produits']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Basculer le statut d'un produit
     */
    public function toggleProductStatus(Produit $produit): Produit
    {
        $produit->update(['est_visible' => !$produit->est_visible]);
        
        $status = $produit->est_visible ? 'activé' : 'désactivé';
        
        Log::info("Produit {$status}", [
            'produit_id' => $produit->id,
            'nom' => $produit->nom,
            'user_id' => auth()->id()
        ]);

        return $produit;
    }

    /**
     * Supprimer une image de produit
     */
    public function deleteProductImage(Produit $produit, ImagesProduit $image): bool
    {
        if ($image->produit_id !== $produit->id) {
            throw new \Exception('Image non trouvée pour ce produit');
        }

        // Supprimer les fichiers physiques
        $this->deleteImageFiles($image);

        $deleted = $image->delete();

        if ($deleted) {
            Log::info('Image de produit supprimée', [
                'produit_id' => $produit->id,
                'image_id' => $image->id,
                'user_id' => auth()->id()
            ]);
        }

        return $deleted;
    }

    /**
     * Mettre à jour l'ordre des images
     */
    public function updateImagesOrder(Produit $produit, array $imageOrders): bool
    {
        DB::beginTransaction();

        try {
            foreach ($imageOrders as $imageData) {
                ImagesProduit::where('id', $imageData['id'])
                    ->where('produit_id', $produit->id)
                    ->update([
                        'ordre_affichage' => $imageData['ordre_affichage'],
                        'est_principale' => $imageData['est_principale'] ?? false
                    ]);
            }

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Obtenir les statistiques d'un produit
     */
    public function getProductStats(Produit $produit): array
    {
        return [
            'total_ventes' => $produit->articles_commandes()->sum('quantite'),
            'chiffre_affaires' => $produit->articles_commandes()->sum('prix_total_article'),
            'nombre_commandes' => $produit->articles_commandes()->distinct('commande_id')->count(),
            'note_moyenne' => $produit->note_moyenne,
            'nombre_avis' => $produit->nombre_avis,
            'derniere_vente' => $produit->articles_commandes()->latest()->first()?->created_at,
            'stock_status' => $this->getStockStatus($produit),
        ];
    }

    /**
     * Obtenir le statut du stock
     */
    public function getStockStatus(Produit $produit): array
    {
        if (!$produit->gestion_stock) {
            return [
                'status' => 'unlimited',
                'label' => 'Stock illimité',
                'color' => 'blue'
            ];
        }

        if ($produit->stock_disponible <= 0) {
            return [
                'status' => 'out_of_stock',
                'label' => 'Rupture de stock',
                'color' => 'red'
            ];
        }

        if ($produit->stock_disponible <= $produit->seuil_alerte) {
            return [
                'status' => 'low_stock',
                'label' => 'Stock faible',
                'color' => 'orange'
            ];
        }

        return [
            'status' => 'in_stock',
            'label' => 'En stock',
            'color' => 'green'
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
        $query = Produit::where('slug', $slug);
        
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
        return Produit::max('ordre_affichage') + 1;
    }

    /**
     * Traiter les champs JSON
     */
    private function processJsonFields(array $data): array
    {
        $jsonFields = ['tailles_disponibles', 'couleurs_disponibles', 'materiaux_necessaires'];
        
        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode(array_filter($data[$field])); // Supprimer les valeurs vides
            }
        }

        return $data;
    }

    /**
     * Gérer l'upload d'une image
     */
    private function handleImageUpload(UploadedFile $image, string $folder = 'produits'): string
    {
        $filename = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
        return $image->storeAs($folder, $filename, 'public');
    }

    /**
     * Gérer les images multiples
     */
    private function handleMultipleImages(Produit $produit, array $images): void
    {
        foreach ($images as $index => $imageFile) {
            $imagePath = $this->handleImageUpload($imageFile, 'produits');
            
            // Obtenir les dimensions de l'image
            $imageDimensions = getimagesize(Storage::disk('public')->path($imagePath));
            
            ImagesProduit::create([
                'produit_id' => $produit->id,
                'nom_fichier' => $imageFile->getClientOriginalName(),
                'chemin_original' => $imagePath,
                'alt_text' => $produit->nom,
                'ordre_affichage' => $index + 1,
                'est_principale' => $index === 0 && !$produit->image_principale,
                'est_visible' => true,
                'format' => $imageFile->getClientOriginalExtension(),
                'taille_octets' => $imageFile->getSize(),
                'largeur' => $imageDimensions[0] ?? null,
                'hauteur' => $imageDimensions[1] ?? null,
            ]);
        }
    }

    /**
     * Dupliquer une image de produit
     */
    private function duplicateProductImage(ImagesProduit $originalImage, Produit $newProduit): void
    {
        $newImage = $originalImage->replicate();
        $newImage->produit_id = $newProduit->id;
        
        // Copier le fichier physique
        if (Storage::disk('public')->exists($originalImage->chemin_original)) {
            $extension = pathinfo($originalImage->chemin_original, PATHINFO_EXTENSION);
            $newPath = 'produits/' . time() . '_' . Str::random(10) . '.' . $extension;
            
            Storage::disk('public')->copy($originalImage->chemin_original, $newPath);
            $newImage->chemin_original = $newPath;
        }
        
        $newImage->save();
    }

    /**
     * Supprimer toutes les images d'un produit
     */
    private function deleteAllProductImages(Produit $produit): void
    {
        // Supprimer l'image principale
        if ($produit->image_principale) {
            $this->deleteImage($produit->image_principale);
        }

        // Supprimer toutes les images associées
        foreach ($produit->images_produits as $image) {
            $this->deleteImageFiles($image);
        }
    }

    /**
     * Supprimer les fichiers d'une image
     */
    private function deleteImageFiles(ImagesProduit $image): void
    {
        $paths = [$image->chemin_original, $image->chemin_miniature, $image->chemin_moyen];
        
        foreach ($paths as $path) {
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
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