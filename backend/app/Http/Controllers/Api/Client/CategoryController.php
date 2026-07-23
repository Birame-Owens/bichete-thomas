<?php
namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Client\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    // Retourne TOUTES les catégories actives (parents + enfants) avec parent_id
    public function index(): JsonResponse
    {
        try {
            $categories = Category::where('est_active', true)
                ->withCount('produits')
                ->orderBy('parent_id')
                ->orderBy('ordre_affichage')
                ->get();

            // Calcul des produits des sous-catégories pour chaque parent
            $subProductCounts = [];
            foreach ($categories as $cat) {
                if ($cat->parent_id) {
                    $subProductCounts[$cat->parent_id] = ($subProductCounts[$cat->parent_id] ?? 0) + $cat->produits_count;
                }
            }

            $result = $categories->map(function ($category) use ($subProductCounts) {
                    return [
                        'id'             => $category->id,
                        'parent_id'      => $category->parent_id,
                        'nom'            => $category->nom,
                        'slug'           => $category->slug,
                        'description'    => $category->description,
                        'image'          => $category->image ? asset('storage/' . $category->image) : null,
                        'couleur_theme'  => $category->couleur_theme ?? null,
                        'produits_count' => $category->produits_count + ($subProductCounts[$category->id] ?? 0),
                        'est_populaire'  => $category->est_populaire ?? false,
                        'url'            => "/categories/{$category->slug}",
                    ];
                });

            return response()->json(['success' => true, 'data' => $result]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur lors du chargement des catégories'], 500);
        }
    }

    public function show(string $slug): JsonResponse
    {
        try {
            $category = Category::where('slug', $slug)->where('est_active', true)->first();

            if (!$category) {
                return response()->json(['success' => false, 'message' => 'Catégorie non trouvée'], 404);
            }

            $produitsCount = \App\Models\Produit::where('est_visible', true)
                ->where('categorie_id', $category->id)
                ->count();

            // Sous-catégories actives
            $subcategories = Category::where('parent_id', $category->id)
                ->where('est_active', true)
                ->withCount('produits')
                ->orderBy('ordre_affichage')
                ->get()
                ->map(fn($sc) => [
                    'id'             => $sc->id,
                    'parent_id'      => $sc->parent_id,
                    'nom'            => $sc->nom,
                    'slug'           => $sc->slug,
                    'description'    => $sc->description,
                    'image'          => $sc->image ? asset('storage/' . $sc->image) : null,
                    'produits_count' => $sc->produits_count,
                    'url'            => "/categories/{$sc->slug}",
                ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id'             => $category->id,
                    'parent_id'      => $category->parent_id,
                    'nom'            => $category->nom,
                    'slug'           => $category->slug,
                    'description'    => $category->description,
                    'image'          => $category->image ? asset('storage/' . $category->image) : null,
                    'couleur_theme'  => $category->couleur_theme ?? null,
                    'produits_count' => $produitsCount,
                    'subcategories'  => $subcategories,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur lors du chargement de la catégorie'], 500);
        }
    }

    public function getProducts(string $slug, Request $request): JsonResponse
    {
        try {
            $category = Category::where('slug', $slug)->where('est_active', true)->first();

            if (!$category) {
                return response()->json(['success' => false, 'message' => 'Catégorie non trouvée'], 404);
            }

            $filters = $request->only(['search', 'min_price', 'max_price', 'on_sale', 'sort', 'direction', 'per_page', 'est_nouveaute', 'est_populaire', 'page']);
            $filters['category'] = $category->id;

            $result = $this->productService->getProducts($filters);

            return response()->json(['success' => true, 'data' => $result]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur lors du chargement des produits'], 500);
        }
    }
}
