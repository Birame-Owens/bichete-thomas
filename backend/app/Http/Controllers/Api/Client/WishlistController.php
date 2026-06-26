<?php 

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Services\Client\WishlistService;
use App\Http\Requests\Client\WishlistRequest;
use Illuminate\Http\JsonResponse;

class WishlistController extends Controller
{
    protected WishlistService $wishlistService;

    public function __construct(WishlistService $wishlistService)
    {
        $this->wishlistService = $wishlistService;
    }

    public function index(): JsonResponse
    {
        try {
            $wishlist = $this->wishlistService->getWishlist();

            return response()->json([
                'success' => true,
                'data' => $wishlist
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des favoris'
            ], 500);
        }
    }

    public function add(WishlistRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            \Log::info('💚 Wishlist add - Validated data:', $validated);
            \Log::info('💚 Wishlist add - User:', ['user' => auth('sanctum')->user()?->id]);
            
            $result = $this->wishlistService->addToWishlist($validated['product_id']);

            return response()->json($result);

        } catch (\Exception $e) {
            \Log::error('❌ Wishlist add error:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout aux favoris: ' . $e->getMessage()
            ], 500);
        }
    }
    public function getCount(): JsonResponse
    {
        try {
            $count = $this->wishlistService->getCount();
            return response()->json([
                'success' => true,
                'data' => ['count' => $count]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur'], 500);
        }
    }

    public function clear(): JsonResponse
    {
        try {
            $result = $this->wishlistService->clearWishlist();
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur'], 500);
        }
    }

    public function moveToCart(int $productId): JsonResponse
    {
        try {
            $result = $this->wishlistService->moveToCart($productId);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur'], 500);
        }
    }

    public function checkProduct(int $productId): JsonResponse
    {
        try {
            $isInWishlist = $this->wishlistService->isInWishlist($productId);
            return response()->json([
                'success' => true,
                'data' => ['is_in_wishlist' => $isInWishlist]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur'], 500);
        }
    }


    public function remove(int $productId): JsonResponse
    {
        try {
            $result = $this->wishlistService->removeFromWishlist($productId);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);    
        }
    }
 }