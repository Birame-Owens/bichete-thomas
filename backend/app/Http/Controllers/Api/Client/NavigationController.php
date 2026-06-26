<?php
// ================================================================
// 📝 FICHIER: app/Http/Controllers/Api/Client/NavigationController.php
// ================================================================

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Services\Client\NavigationService;
use Illuminate\Http\JsonResponse;

class NavigationController extends Controller
{
    protected NavigationService $navigationService;

    public function __construct(NavigationService $navigationService)
    {
        $this->navigationService = $navigationService;
    }

    public function getMainMenu(): JsonResponse
    {
        try {
            $menu = $this->navigationService->getMainMenu();

            return response()->json([
                'success' => true,
                'data' => $menu
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du menu'
            ], 500);
        }
    }

    public function getCategoryPreview(string $slug): JsonResponse
    {
        try {
            $preview = $this->navigationService->getCategoryPreview($slug);

            return response()->json([
                'success' => true,
                'data' => $preview
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement de l\'aperçu'
            ], 500);
        }
    }
}