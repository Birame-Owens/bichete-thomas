<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Produit;
use App\Models\ImagesProduit;
use App\Models\Category;
use App\Models\ShopSetting;
use App\Http\Requests\Admin\ProduitRequest;
use App\Services\ImageOptimizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProduitController extends Controller
{
    public function __construct(private ImageOptimizationService $imageOptimizationService)
    {
    }

    /**
     * Liste tous les produits
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');
            $categoryId = $request->get('category_id');
            $status = $request->get('status'); // 'visible', 'hidden', 'all'
            $sort = $request->get('sort', 'created_at');
            $direction = $request->get('direction', 'desc');

            $query = Produit::with(['category', 'images_produits' => function ($q) {
                $q->where('est_principale', true)->orWhere('ordre_affichage', 1);
            }]);

            // Recherche
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nom', 'ILIKE', "%{$search}%")
                      ->orWhere('description', 'ILIKE', "%{$search}%")
                      ->orWhere('tags', 'ILIKE', "%{$search}%");
                });
            }

            // Filtrer par catégorie
            if ($categoryId) {
                $query->where('categorie_id', $categoryId);
            }

            // Filtrer par statut
            if ($status && $status !== 'all') {
                $query->where('est_visible', $status === 'visible');
            }

            // Tri
            $allowedSorts = ['nom', 'prix', 'stock_disponible', 'created_at', 'nombre_ventes', 'note_moyenne'];
            if (in_array($sort, $allowedSorts)) {
                $query->orderBy($sort, $direction);
            }

            $produits = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'produits' => $produits->map(fn ($produit) => $this->formatProduitResponse($produit)),
                    'pagination' => [
                        'current_page' => $produits->currentPage(),
                        'per_page' => $produits->perPage(),
                        'total' => $produits->total(),
                        'last_page' => $produits->lastPage(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des produits', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des produits'
            ], 500);
        }
    }

    /**
     * Créer un nouveau produit
     */
    public function store(ProduitRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Debug: Vérifier ce qui est reçu
            Log::info('📦 Création produit - Données reçues', [
                'has_file_image_principale' => $request->hasFile('image_principale'),
                'file_info' => $request->hasFile('image_principale') ? [
                    'name' => $request->file('image_principale')->getClientOriginalName(),
                    'mime' => $request->file('image_principale')->getMimeType(),
                    'size' => $request->file('image_principale')->getSize(),
                ] : 'Aucun fichier',
                'all_files' => $request->allFiles(),
                'nom_produit' => $request->input('nom')
            ]);

            $validatedData = $request->validated();
            
            // Génération du slug
            $validatedData['slug'] = Str::slug($validatedData['nom']);
            
            // Vérifier l'unicité du slug
            $originalSlug = $validatedData['slug'];
            $counter = 1;
            while (Produit::where('slug', $validatedData['slug'])->exists()) {
                $validatedData['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }

            // Gestion de l'image principale (avec valeur par défaut)
            if ($request->hasFile('image_principale')) {
                $optimizedImage = $this->imageOptimizationService->storeProductImage(
                    $request->file('image_principale'),
                    $validatedData['nom'] ?? 'product'
                );
                $validatedData['image_principale'] = $optimizedImage['chemin_moyen'] ?: $optimizedImage['chemin_original'];
            } else {
                // Image par défaut si aucune image n'est fournie
                $validatedData['image_principale'] = 'produits/default-product.jpg';
            }

            // Traiter les données JSON
            if (isset($validatedData['tailles_disponibles']) && is_array($validatedData['tailles_disponibles'])) {
                $validatedData['tailles_disponibles'] = json_encode($validatedData['tailles_disponibles']);
            }
            
            if (isset($validatedData['couleurs_disponibles']) && is_array($validatedData['couleurs_disponibles'])) {
                $validatedData['couleurs_disponibles'] = json_encode($validatedData['couleurs_disponibles']);
            }

            if (isset($validatedData['materiaux_necessaires']) && is_array($validatedData['materiaux_necessaires'])) {
                $validatedData['materiaux_necessaires'] = json_encode($validatedData['materiaux_necessaires']);
            }

            // Convertir tags CSV → tableau (colonne json en base)
            if (isset($validatedData['tags']) && is_string($validatedData['tags'])) {
                $validatedData['tags'] = array_values(array_filter(
                    array_map('trim', explode(',', $validatedData['tags']))
                ));
            }

            $validatedData = $this->applyDefaultSeo($validatedData);

            if (isset($validatedData['couleur_tailles']) && is_array($validatedData['couleur_tailles'])) {
                $ctArray = $validatedData['couleur_tailles'];
                $validatedData['couleur_tailles'] = json_encode($ctArray);
                if (!empty($ctArray)) {
                    $validatedData['couleurs_disponibles'] = json_encode(array_values(array_keys($ctArray)));
                    $allSizes = array_unique(array_merge(...array_values($ctArray)));
                    $validatedData['tailles_disponibles'] = json_encode(array_values($allSizes));
                }
            }

            if (isset($validatedData['couleur_tailles_stock']) && is_array($validatedData['couleur_tailles_stock'])) {
                $validatedData['couleur_tailles_stock'] = json_encode($validatedData['couleur_tailles_stock']);
            }

            if (isset($validatedData['couleur_tailles_seuil']) && is_array($validatedData['couleur_tailles_seuil'])) {
                $validatedData['couleur_tailles_seuil'] = json_encode($validatedData['couleur_tailles_seuil']);
            }

            $produit = Produit::create($validatedData);

            // Gestion des images multiples
            if ($request->hasFile('images')) {
                $colorMap = $request->input('image_couleurs', []);
                $this->handleProductImages($produit, $request->file('images'), $colorMap);
            }

            DB::commit();
            // Vider le cache des produits côté client
            $this->clearClientProductCaches();
            Log::info('Nouveau produit créé', [
                'produit_id' => $produit->id,
                'nom' => $produit->nom,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Produit créé avec succès',
                'data' => [
                    'produit' => $this->formatProduitResponse($produit->load(['category', 'images_produits']))
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Erreur lors de la création du produit', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du produit'
            ], 500);
        }
    }

    /**
     * Afficher un produit spécifique
     */
    public function show(Produit $produit): JsonResponse
    {
        try {
            $produit->load(['category', 'images_produits' => function ($q) {
                $q->orderBy('ordre_affichage');
            }, 'avis_clients' => function ($q) {
                $q->where('statut', 'approuve')->latest()->take(5);
            }]);

            return response()->json([
                'success' => true,
                'data' => [
                    'produit' => $this->formatProduitResponse($produit, true)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération du produit', [
                'produit_id' => $produit->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du produit'
            ], 500);
        }
    }

    /**
     * Mettre à jour un produit
     */
    public function update(ProduitRequest $request, Produit $produit): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();
            
            // Mise à jour du slug si le nom change
            if (isset($validatedData['nom']) && $validatedData['nom'] !== $produit->nom) {
                $newSlug = Str::slug($validatedData['nom']);
                
                $originalSlug = $newSlug;
                $counter = 1;
                while (Produit::where('slug', $newSlug)->where('id', '!=', $produit->id)->exists()) {
                    $newSlug = $originalSlug . '-' . $counter;
                    $counter++;
                }
                
                $validatedData['slug'] = $newSlug;
            }

            // Gestion de l'image principale
            if ($request->hasFile('image_principale')) {
                $this->imageOptimizationService->deleteProductImageFamily($produit->image_principale);
                
                $optimizedImage = $this->imageOptimizationService->storeProductImage(
                    $request->file('image_principale'),
                    $validatedData['nom'] ?? $produit->nom
                );
                $validatedData['image_principale'] = $optimizedImage['chemin_moyen'] ?: $optimizedImage['chemin_original'];
            }

            // Traiter les données JSON
            if (isset($validatedData['tailles_disponibles']) && is_array($validatedData['tailles_disponibles'])) {
                $validatedData['tailles_disponibles'] = json_encode($validatedData['tailles_disponibles']);
            }
            
            if (isset($validatedData['couleurs_disponibles']) && is_array($validatedData['couleurs_disponibles'])) {
                $validatedData['couleurs_disponibles'] = json_encode($validatedData['couleurs_disponibles']);
            }

            if (isset($validatedData['materiaux_necessaires']) && is_array($validatedData['materiaux_necessaires'])) {
                $validatedData['materiaux_necessaires'] = json_encode($validatedData['materiaux_necessaires']);
            }

            // Convertir tags CSV → tableau (colonne json en base)
            if (isset($validatedData['tags']) && is_string($validatedData['tags'])) {
                $validatedData['tags'] = array_values(array_filter(
                    array_map('trim', explode(',', $validatedData['tags']))
                ));
            }

            $validatedData = $this->applyDefaultSeo($validatedData);

            if (isset($validatedData['couleur_tailles']) && is_array($validatedData['couleur_tailles'])) {
                $ctArray = $validatedData['couleur_tailles'];
                $validatedData['couleur_tailles'] = json_encode($ctArray);
                if (!empty($ctArray)) {
                    $validatedData['couleurs_disponibles'] = json_encode(array_values(array_keys($ctArray)));
                    $allSizes = array_unique(array_merge(...array_values($ctArray)));
                    $validatedData['tailles_disponibles'] = json_encode(array_values($allSizes));
                }
            }

            if (isset($validatedData['couleur_tailles_stock']) && is_array($validatedData['couleur_tailles_stock'])) {
                $validatedData['couleur_tailles_stock'] = json_encode($validatedData['couleur_tailles_stock']);
            }

            if (isset($validatedData['couleur_tailles_seuil']) && is_array($validatedData['couleur_tailles_seuil'])) {
                $validatedData['couleur_tailles_seuil'] = json_encode($validatedData['couleur_tailles_seuil']);
            }

            $produit->update($validatedData);

            // Supprimer les images marquées pour suppression
            if ($request->has('images_to_delete')) {
                $imagesToDelete = $request->input('images_to_delete', []);
                if (is_array($imagesToDelete) && count($imagesToDelete) > 0) {
                    foreach ($imagesToDelete as $imageId) {
                        $image = ImagesProduit::find($imageId);
                        if ($image && $image->produit_id === $produit->id) {
                            // Supprimer les fichiers physiques
                            if ($image->chemin_original && Storage::disk('public')->exists($image->chemin_original)) {
                                Storage::disk('public')->delete($image->chemin_original);
                            }
                            if ($image->chemin_miniature && Storage::disk('public')->exists($image->chemin_miniature)) {
                                Storage::disk('public')->delete($image->chemin_miniature);
                            }
                            if ($image->chemin_moyen && Storage::disk('public')->exists($image->chemin_moyen)) {
                                Storage::disk('public')->delete($image->chemin_moyen);
                            }
                            
                            // Supprimer l'enregistrement
                            $image->delete();
                            
                            Log::info('Image supprimée', [
                                'image_id' => $imageId,
                                'produit_id' => $produit->id
                            ]);
                        }
                    }
                }
            }

            // Gestion des nouvelles images
            if ($request->hasFile('images')) {
                $colorMap = $request->input('image_couleurs', []);
                $this->handleProductImages($produit, $request->file('images'), $colorMap);
            }

            DB::commit();

            // Vider le cache des produits côté client
            $this->clearClientProductCaches($produit->slug);
            // Vider aussi le cache de la page d'accueil pour les badges Nouveau/Populaire

            Log::info('Produit mis à jour', [
                'produit_id' => $produit->id,
                'nom' => $produit->nom,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Produit mis à jour avec succès',
                'data' => [
                    'produit' => $this->formatProduitResponse($produit->load(['category', 'images_produits']))
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Erreur lors de la mise à jour du produit', [
                'produit_id' => $produit->id,
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du produit'
            ], 500);
        }
    }

    /**
     * Supprimer un produit
     */
    public function destroy(Produit $produit): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Vérifier s'il y a des commandes associées
            $commandesCount = $produit->articles_commandes()->count();
            
            if ($commandesCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Impossible de supprimer ce produit car il est associé à {$commandesCount} commande(s)."
                ], 400);
            }

            // Supprimer toutes les images
            $this->imageOptimizationService->deleteProductImageFamily($produit->image_principale);

            foreach ($produit->images_produits as $image) {
                if (Storage::disk('public')->exists($image->chemin_original)) {
                    Storage::disk('public')->delete($image->chemin_original);
                }
                if ($image->chemin_miniature && Storage::disk('public')->exists($image->chemin_miniature)) {
                    Storage::disk('public')->delete($image->chemin_miniature);
                }
                if ($image->chemin_moyen && Storage::disk('public')->exists($image->chemin_moyen)) {
                    Storage::disk('public')->delete($image->chemin_moyen);
                }
            }

            $produitNom = $produit->nom;
            $produitSlug = $produit->slug;
            $produit->delete();

            DB::commit();

            // Vider le cache des produits côté client
            $this->clearClientProductCaches($produitSlug);
            // Vider aussi le cache de la page d'accueil

            Log::info('Produit supprimé', [
                'produit_nom' => $produitNom,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Produit supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Erreur lors de la suppression du produit', [
                'produit_id' => $produit->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du produit'
            ], 500);
        }
    }

    /**
     * Activer/Désactiver un produit
     */
    public function toggleStatus(Produit $produit): JsonResponse
    {
        try {
            $produit->update(['est_visible' => !$produit->est_visible]);

            $status = $produit->est_visible ? 'activé' : 'désactivé';
            
            // Vérifier si la catégorie est visible côté client
            $categoryActive = $produit->category && $produit->category->est_active;
            $seraVisibleClient = $produit->est_visible && $categoryActive;

            $this->clearClientProductCaches($produit->slug);

            Log::info("Produit {$status}", [
                'produit_id' => $produit->id,
                'nom' => $produit->nom,
                'sera_visible_client' => $seraVisibleClient,
                'category_active' => $categoryActive,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Produit {$status} avec succès",
                'data' => [
                    'produit' => [
                        'id' => $produit->id,
                        'nom' => $produit->nom,
                        'est_visible' => $produit->est_visible,
                        'visibilite_client' => [
                            'sera_visible' => $seraVisibleClient,
                            'raison' => $this->getProductVisibilityReason($produit, $categoryActive)
                        ]
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors du changement de statut du produit', [
                'produit_id' => $produit->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de statut'
            ], 500);
        }
    }

    /**
     * Dupliquer un produit
     */
    public function duplicate(Produit $produit): JsonResponse
    {
        try {
            DB::beginTransaction();

            $newProduit = $produit->replicate();
            $newProduit->nom = $produit->nom . ' (Copie)';
            $newProduit->slug = Str::slug($newProduit->nom);
            
            // Vérifier l'unicité du slug
            $originalSlug = $newProduit->slug;
            $counter = 1;
            while (Produit::where('slug', $newProduit->slug)->exists()) {
                $newProduit->slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            $newProduit->est_visible = false; // Créer en mode brouillon
            $newProduit->save();

            // Dupliquer les images
            foreach ($produit->images_produits as $image) {
                $newImage = $image->replicate();
                $newImage->produit_id = $newProduit->id;
                
                // Copier les fichiers physiques
                if (Storage::disk('public')->exists($image->chemin_original)) {
                    $newPath = 'produits/' . $newProduit->id . '_' . basename($image->chemin_original);
                    Storage::disk('public')->copy($image->chemin_original, $newPath);
                    $newImage->chemin_original = $newPath;
                }
                
                $newImage->save();
            }

            DB::commit();

            Log::info('Produit dupliqué', [
                'produit_original_id' => $produit->id,
                'nouveau_produit_id' => $newProduit->id,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Produit dupliqué avec succès',
                'data' => [
                    'produit' => $this->formatProduitResponse($newProduit->load(['category', 'images_produits']))
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Erreur lors de la duplication du produit', [
                'produit_id' => $produit->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la duplication du produit'
            ], 500);
        }
    }

    /**
     * Supprimer une image de produit
     */
    public function deleteImage(Produit $produit, ImagesProduit $image): JsonResponse
    {
        try {
            // Vérifier que l'image appartient au produit
            if ($image->produit_id !== $produit->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Image non trouvée pour ce produit'
                ], 404);
            }

            // Supprimer les fichiers physiques
            if (Storage::disk('public')->exists($image->chemin_original)) {
                Storage::disk('public')->delete($image->chemin_original);
            }
            if ($image->chemin_miniature && Storage::disk('public')->exists($image->chemin_miniature)) {
                Storage::disk('public')->delete($image->chemin_miniature);
            }
            if ($image->chemin_moyen && Storage::disk('public')->exists($image->chemin_moyen)) {
                Storage::disk('public')->delete($image->chemin_moyen);
            }

            $image->delete();

            Log::info('Image de produit supprimée', [
                'produit_id' => $produit->id,
                'image_id' => $image->id,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Image supprimée avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression de l\'image', [
                'produit_id' => $produit->id,
                'image_id' => $image->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'image'
            ], 500);
        }
    }

    /**
     * Mettre à jour l'ordre des images (et la couleur associée)
     */
    public function updateImagesOrder(Produit $produit, Request $request): JsonResponse
    {
        try {
            $imageOrders = $request->validate([
                'images' => 'required|array',
                'images.*.id' => 'required|integer|exists:images_produits,id',
                'images.*.ordre_affichage' => 'required|integer|min:1',
                'images.*.est_principale' => 'boolean',
                'images.*.couleur_associee' => 'nullable|string|max:50',
            ]);

            DB::beginTransaction();

            foreach ($imageOrders['images'] as $imageData) {
                ImagesProduit::where('id', $imageData['id'])
                    ->where('produit_id', $produit->id)
                    ->update([
                        'ordre_affichage' => $imageData['ordre_affichage'],
                        'est_principale' => $imageData['est_principale'] ?? false,
                        'couleur_associee' => $imageData['couleur_associee'] ?? null,
                    ]);
            }

            DB::commit();

            $this->clearClientProductCaches($produit->slug);

            return response()->json([
                'success' => true,
                'message' => 'Ordre des images mis à jour avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Erreur lors de la mise à jour de l\'ordre des images', [
                'produit_id' => $produit->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'ordre des images'
            ], 500);
        }
    }

    // ========== MÉTHODES PRIVÉES ==========

    /**
     * Gérer les images multiples d'un produit
     * $colorMap = ['0' => 'Rouge', '1' => 'Rouge', '2' => 'Bleu', ...]
     */
    private function handleProductImages(Produit $produit, array $images, array $colorMap = []): void
    {
        // Déterminer l'offset pour l'ordre (après les images existantes)
        $existingCount = $produit->images_produits()->count();

        foreach ($images as $index => $imageFile) {
            $optimizedImage = $this->imageOptimizationService->storeProductImage(
                $imageFile,
                $produit->slug . '-' . ($existingCount + $index + 1)
            );

            ImagesProduit::create([
                'produit_id' => $produit->id,
                'nom_fichier' => $imageFile->getClientOriginalName(),
                'chemin_original' => $optimizedImage['chemin_original'],
                'chemin_miniature' => $optimizedImage['chemin_miniature'],
                'chemin_moyen' => $optimizedImage['chemin_moyen'],
                'alt_text' => $produit->nom,
                'ordre_affichage' => $existingCount + $index + 1,
                'est_principale' => $existingCount === 0 && $index === 0,
                'est_visible' => true,
                'format' => $optimizedImage['format'],
                'taille_octets' => $optimizedImage['taille_octets'],
                'largeur' => $optimizedImage['largeur'],
                'hauteur' => $optimizedImage['hauteur'],
                'couleur_associee' => $colorMap[$index] ?? $colorMap[(string) $index] ?? null,
            ]);
        }
    }

    /**
     * Formater la réponse d'un produit
     */
    private function formatProduitResponse(Produit $produit, bool $detailed = false): array
    {
        $data = [
            'id' => $produit->id,
            'nom' => $produit->nom,
            'slug' => $produit->slug,
            'description' => $produit->description,
            'description_courte' => $produit->description_courte,
            'prix' => $produit->prix,
            'prix_promo' => $produit->prix_promo,
            'prix_actuel' => $produit->prix_promo ?: $produit->prix,
            'en_promo' => $produit->prix_promo !== null,
            'debut_promo' => $produit->debut_promo?->format('Y-m-d H:i:s'),
            'fin_promo' => $produit->fin_promo?->format('Y-m-d H:i:s'),
            'image_principale' => $produit->image_principale ? asset('storage/' . $produit->image_principale) : null,
            'images' => $produit->images_produits->map(function ($image) {
                return [
                    'id' => $image->id,
                    'nom_fichier' => $image->nom_fichier,
                    'url_originale' => asset('storage/' . $image->chemin_original),
                    'url_miniature' => $image->chemin_miniature ? asset('storage/' . $image->chemin_miniature) : null,
                    'url_moyenne' => $image->chemin_moyen ? asset('storage/' . $image->chemin_moyen) : null,
                    'alt_text' => $image->alt_text,
                    'titre' => $image->titre,
                    'ordre_affichage' => $image->ordre_affichage,
                    'est_principale' => $image->est_principale,
                    'couleur_associee' => $image->couleur_associee,
                    'largeur' => $image->largeur,
                    'hauteur' => $image->hauteur,
                ];
            }),
            'categorie' => $produit->category ? [
                'id' => $produit->category->id,
                'nom' => $produit->category->nom,
                'slug' => $produit->category->slug
            ] : null,
            'stock_disponible' => $this->resolveStockTotal($produit),
            'seuil_alerte' => $produit->seuil_alerte,
            'stock_status' => $this->getStockStatus($produit),
            'gestion_stock' => $produit->gestion_stock,
            'fait_sur_mesure' => $produit->fait_sur_mesure,
            'delai_production_jours' => $produit->delai_production_jours,
            'cout_production' => $produit->cout_production,
            'type_variante' => $produit->type_variante ?? 'vetement',
            'tailles_disponibles' => $produit->tailles_disponibles ? json_decode($produit->tailles_disponibles) : [],
            'couleurs_disponibles' => $produit->couleurs_disponibles ? json_decode($produit->couleurs_disponibles) : [],
            'couleur_tailles' => $produit->couleur_tailles ? json_decode($produit->couleur_tailles, true) : null,
            'couleur_tailles_stock' => $produit->couleur_tailles_stock ? json_decode($produit->couleur_tailles_stock, true) : null,
            'couleur_tailles_seuil' => $produit->couleur_tailles_seuil ? json_decode($produit->couleur_tailles_seuil, true) : null,
            'materiaux_necessaires' => $produit->materiaux_necessaires ? json_decode($produit->materiaux_necessaires) : [],
            'est_visible' => $produit->est_visible,
            'est_populaire' => $produit->est_populaire,
            'est_nouveaute' => $produit->est_nouveaute,
            'ordre_affichage' => $produit->ordre_affichage,
            'nombre_vues' => $produit->nombre_vues,
            'nombre_ventes' => $produit->nombre_ventes,
            'note_moyenne' => $produit->note_moyenne,
            'nombre_avis' => $produit->nombre_avis,
            'meta_titre' => $produit->meta_titre,
            'meta_description' => $produit->meta_description,
            'tags' => is_array($produit->tags) ? implode(', ', $produit->tags) : ($produit->tags ?? ''),
            'created_at' => $produit->created_at->format('d/m/Y H:i'),
            'updated_at' => $produit->updated_at->format('d/m/Y H:i'),
        ];

        if ($detailed && isset($produit->avis_clients)) {
            $data['avis_clients'] = $produit->avis_clients->map(function ($avis) {
                return [
                    'id' => $avis->id,
                    'client_nom' => $avis->nom_affiche ?: 'Client anonyme',
                    'note_globale' => $avis->note_globale,
                    'commentaire' => $avis->commentaire,
                    'date' => $avis->created_at->format('d/m/Y'),
                ];
            });
        }

        return $data;
    }

    /**
     * Calcule le stock total réel (variant ou simple)
     */
    private function resolveStockTotal(Produit $produit): int
    {
        if ($produit->couleur_tailles_stock) {
            $stockData = json_decode($produit->couleur_tailles_stock, true) ?? [];
            $total = 0;
            foreach ($stockData as $tailles) {
                foreach ($tailles as $qty) {
                    $total += (int) $qty;
                }
            }
            return $total;
        }
        return (int) ($produit->stock_disponible ?? 0);
    }

    /**
     * Obtenir le statut du stock
     */
    private function getStockStatus(Produit $produit): array
    {
        if (!$produit->gestion_stock) {
            return [
                'status' => 'unlimited',
                'label' => 'Stock illimité',
                'color' => 'blue'
            ];
        }

        $stockTotal = $this->resolveStockTotal($produit);

        if ($stockTotal <= 0) {
            return [
                'status' => 'out_of_stock',
                'label' => 'Rupture de stock',
                'color' => 'red'
            ];
        }

        if ($stockTotal <= $produit->seuil_alerte) {
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

    /**
     * Retourne la raison pour laquelle un produit est/n'est pas visible côté client
     */
    private function getProductVisibilityReason(Produit $produit, bool $categoryActive): string
    {
        if (!$produit->est_visible) {
            return 'Produit désactivé';
        }
        
        if (!$categoryActive) {
            return 'Catégorie non active';
        }
        
        return 'Produit visible côté client';
    }
    private function applyDefaultSeo(array $data): array
    {
        $name = trim((string) ($data['nom'] ?? 'Produit'));
        $categoryName = null;

        if (!empty($data['categorie_id'])) {
            $categoryName = Category::whereKey($data['categorie_id'])->value('nom');
        }

        $shopName = (string) ShopSetting::getValue('boutique_nom', config('app.name', 'ND WORLD'));

        if (empty($data['meta_titre'])) {
            $titleParts = array_filter([$name, $categoryName]);
            $data['meta_titre'] = Str::limit(implode(' - ', $titleParts) . ' | ' . $shopName, 70, '');
        }

        if (empty($data['meta_description'])) {
            $source = $data['description_courte'] ?? $data['description'] ?? '';
            $source = preg_replace('/\s+/', ' ', trim(strip_tags((string) $source)));
            $description = "Achetez {$name}";

            if ($categoryName) {
                $description .= " dans la categorie {$categoryName}";
            }

            $description .= " chez {$shopName} au Senegal.";

            if ($source !== '') {
                $description .= ' ' . $source;
            }

            $data['meta_description'] = Str::limit($description, 160, '');
        }

        if (empty($data['tags'])) {
            $words = preg_split('/[\s,-]+/', Str::lower($name));
            $data['tags'] = array_values(array_unique(array_filter(array_merge(
                [$name, $categoryName, $shopName, 'boutique en ligne Senegal', 'Dakar', 'livraison Senegal'],
                $words ?: []
            ))));
        }

        return $data;
    }

    private function clearClientProductCaches(?string $slug = null): void
    {
        Cache::forget(\App\Http\Controllers\Api\Client\BoutiqueController::CACHE_KEY);
        Cache::forget('client_home_data');
        Cache::forget('home_page_data_' . now()->format('Y-m-d-H'));
        Cache::forget('products:list:' . md5(json_encode([])));

        if ($slug) {
            Cache::forget('product:detail:' . $slug);
            Cache::forget('product:page_data:' . $slug);
        }

        try {
            Cache::tags(['api_responses'])->flush();
        } catch (\Throwable $e) {
            Log::debug('API response cache tags not flushed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
