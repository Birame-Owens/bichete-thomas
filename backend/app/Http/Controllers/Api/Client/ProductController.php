<?php
// ================================================================
// 📝 FICHIER: app/Http/Controllers/Api/Client/ProductController.php
// ================================================================

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Services\Client\ProductService;
use App\Http\Requests\Client\ProductRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'category', 'search', 'min_price', 'max_price', 
                'on_sale', 'sort', 'direction', 'per_page'
            ]);

            $result = $this->productService->getProducts($filters);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des produits'
            ], 500);
        }
    }

    public function show(string $slug): JsonResponse
    {
        try {
            $product = $this->productService->getProductBySlug($slug);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produit non trouvé'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $product
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du produit'
            ], 500);
        }
    }

    /**
     * Avis publics (approuvés et visibles) d'un produit.
     */
    public function reviews(string $slug): JsonResponse
    {
        try {
            $product = \App\Models\Produit::where('slug', $slug)->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produit non trouvé'
                ], 404);
            }

            $base = \App\Models\AvisClient::where('produit_id', $product->id)
                ->where('statut', 'approuve')
                ->where('est_visible', true);

            $avis = (clone $base)
                ->with('client')
                ->orderByDesc('est_mis_en_avant')
                ->orderBy('created_at', 'desc')
                ->paginate((int) request('per_page', 10));

            $reviews = collect($avis->items())->map(function ($a) {
                return [
                    'id' => $a->id,
                    'nom_client' => $a->nom_affiche ?: ($a->client ? $a->client->prenom : 'Client'),
                    'note' => $a->note_globale,
                    'titre' => $a->titre,
                    'commentaire' => $a->commentaire,
                    'date' => $a->created_at->format('d/m/Y'),
                    'avis_verifie' => $a->avis_verifie,
                    'recommande_produit' => $a->recommande_produit,
                    'photos' => collect($a->photos_avis ?? [])
                        ->map(fn ($p) => \Illuminate\Support\Facades\Storage::disk('public')->url($p))
                        ->all(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'reviews'      => $reviews,
                    'note_moyenne' => round((float) (clone $base)->avg('note_globale'), 1),
                    'total'        => $avis->total(),
                    'current_page' => $avis->currentPage(),
                    'last_page'    => $avis->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des avis'
            ], 500);
        }
    }

    public function getImages(int $id): JsonResponse
    {
        try {
            $images = $this->productService->getProductImages($id);

            return response()->json([
                'success' => true,
                'data' => $images
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des images'
            ], 500);
        }
    }

    public function getRelated(int $id): JsonResponse
    {
        try {
            $products = $this->productService->getRelatedProducts($id);

            return response()->json([
                'success' => true,
                'data' => $products
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des produits similaires'
            ], 500);
        }
    }

    public function incrementViews(int $id): JsonResponse
    {
        try {
            // Les vues sont déjà incrémentées dans getProductBySlug
            return response()->json([
                'success' => true,
                'message' => 'Vue enregistrée'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement'
            ], 500);
        }
    }

    public function getWhatsAppData(int $id): JsonResponse
    {
        try {
            $result = $this->productService->getWhatsAppData($id);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération des données WhatsApp'
            ], 500);
        }
    }

    public function trending(): JsonResponse
    {
        try {
            $filters = ['sort' => 'popular', 'per_page' => 12];
            $result = $this->productService->getProducts($filters);

            return response()->json([
                'success' => true,
                'data' => $result['products']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des tendances'
            ], 500);
        }
    }

    public function newArrivals(): JsonResponse
    {
        try {
            $filters = ['sort' => 'created_at', 'direction' => 'desc', 'per_page' => 12];
            $result = $this->productService->getProducts($filters);

            return response()->json([
                'success' => true,
                'data' => $result['products']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des nouveautés'
            ], 500);
        }
    }

    public function onSale(): JsonResponse
    {
        try {
            $filters = ['on_sale' => true, 'sort' => 'created_at', 'per_page' => 12];
            $result = $this->productService->getProducts($filters);

            return response()->json([
                'success' => true,
                'data' => $result['products']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des promotions'
            ], 500);
        }
    }

    public function getPageData(string $slug): JsonResponse
{
    try {
        $result = $this->productService->getProductDetailPageData($slug);
        return response()->json($result);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors du chargement'
        ], 500);
    }
}
}
