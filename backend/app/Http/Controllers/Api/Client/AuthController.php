<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Services\Client\AuthService;
use App\Http\Requests\Client\AuthRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function register(AuthRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            \Log::info('📥 Register - Données validées', $validated);
            
            $result = $this->authService->register($validated);
            
            \Log::info('✅ Register - Résultat', ['success' => $result['success']]);

            if ($result['success']) {
                return response()->json($result, 201);
            }

            return response()->json($result, 400);

        } catch (\Exception $e) {
            \Log::error('❌ Register - Erreur', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription'
            ], 500);
        }
    }

    public function login(AuthRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $result = $this->authService->login($validated['email'], $validated['password']);

            if ($result['success']) {
                return response()->json($result);
            }

            return response()->json($result, 401);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la connexion'
            ], 500);
        }
    }

    public function guestCheckout(AuthRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $result = $this->authService->guestCheckout($validated);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement'
            ], 500);
        }
    }

    public function logout(): JsonResponse
    {
        try {
            $result = $this->authService->logout();

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion'
            ], 500);
        }
    }

    public function profile(): JsonResponse
    {
        try {
            $result = $this->authService->getProfile();

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du profil'
            ], 500);
        }
    }

    public function updateProfile(AuthRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $result = $this->authService->updateProfile($validated);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }

    public function saveMeasurements(AuthRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $result = $this->authService->saveMeasurements($validated);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement des mesures'
            ], 500);
        }
    }

    public function getMeasurements(): JsonResponse
    {
        try {
            $profile = $this->authService->getProfile();
            
            return response()->json([
                'success' => true,
                'data' => $profile['data']['mesures'] ?? null
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des mesures'
            ], 500);
        }
    }
}