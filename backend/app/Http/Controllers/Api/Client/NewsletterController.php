<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Services\Client\HomeService;
use App\Http\Requests\Client\NewsletterRequest;
use Illuminate\Http\JsonResponse;

class NewsletterController extends Controller
{
    protected HomeService $homeService;

    public function __construct(HomeService $homeService)
    {
        $this->homeService = $homeService;
    }

    public function subscribe(NewsletterRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $result = $this->homeService->subscribeToNewsletter($validated);

            if ($result['success']) {
                return response()->json($result);
            }

            return response()->json($result, 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription à la newsletter'
            ], 500);
        }
    }

       

    public function clear(): JsonResponse
    {
        try {
            $result = $this->cartService->clearCart();

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du panier'
            ], 500);
        }
    }

    public function getCount(): JsonResponse
    {
        try {
            $cart = $this->cartService->getCart();

            return response()->json([
                'success' => true,
                'data' => ['count' => $cart['count']]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du comptage'
            ], 500);
        }
    }

    public function getTotal(): JsonResponse
    {
        try {
            $cart = $this->cartService->getCart();

            return response()->json([
                'success' => true,
                'data' => [
                    'subtotal' => $cart['subtotal'],
                    'discount' => $cart['discount'],
                    'shipping_fee' => $cart['shipping_fee'],
                    'total' => $cart['total']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul'
            ], 500);
        }
    }

    public function applyCoupon(CartRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $result = $this->cartService->applyCoupon($validated['code']);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'application du code'
            ], 500);
        }
    }

    public function removeCoupon(): JsonResponse
    {
        try {
            $result = $this->cartService->removeCoupon();

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du code'
            ], 500);
        }
    }

    public function generateWhatsAppMessage(): JsonResponse
    {
        try {
            $result = $this->cartService->generateWhatsAppMessage();

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du message'
            ], 500);
        }
    }
}