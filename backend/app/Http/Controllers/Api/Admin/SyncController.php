<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Admin\CategoryProductSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    protected CategoryProductSyncService $syncService;

    public function __construct(CategoryProductSyncService $syncService)
    {
        $this->syncService = $syncService;
        $this->middleware('auth:sanctum');
        $this->middleware('admin.auth');
    }

    /**
     * Synchronise les visibilités catégories-produits
     */
    public function sync(): JsonResponse
    {
        try {
            $report = $this->syncService->syncVisibility();

            return response()->json([
                'success' => true,
                'message' => 'Synchronisation effectuée avec succès',
                'data' => $report
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la synchronisation', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la synchronisation'
            ], 500);
        }
    }

    /**
     * Retourne le rapport complet de visibilité
     */
    public function report(): JsonResponse
    {
        try {
            $report = $this->syncService->generateFullReport();

            return response()->json([
                'success' => true,
                'data' => $report
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la génération du rapport', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du rapport'
            ], 500);
        }
    }

    /**
     * Obtient la visibilité côté client pour une catégorie
     */
    public function categoryVisibility(Category $category): JsonResponse
    {
        try {
            $visibility = $this->syncService->getCategoryClientVisibility($category);

            return response()->json([
                'success' => true,
                'data' => $visibility
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération de la visibilité', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la visibilité'
            ], 500);
        }
    }

    /**
     * Réinitialise la visibilité pour une catégorie
     */
    public function resetCategory(Category $category): JsonResponse
    {
        try {
            $result = $this->syncService->resetCategoryVisibility($category->id);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => [
                    'affected' => $result['affected']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la réinitialisation', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation'
            ], 500);
        }
    }
}
