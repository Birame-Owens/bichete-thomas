<?php
namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Services\Client\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    protected SearchService $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function search(Request $request): JsonResponse
    {
        try {
            $query = $request->get('q', '');
            $filters = $request->only([
                'category_id', 'min_price', 'max_price', 'on_sale', 'per_page'
            ]);

            $result = $this->searchService->search($query, $filters);

            // Sauvegarder la recherche pour analytics
            // Guard sanctum (token) : pas de dépendance à la session web -> route cacheable.
            if (strlen($query) >= 2) {
                $userId = auth('sanctum')->id();
                $this->searchService->saveSearch($query, $userId);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
                'query' => $query
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche'
            ], 500);
        }
    }

    public function suggestions(Request $request): JsonResponse
    {
        try {
            $query = $request->get('q', '');
            $suggestions = $this->searchService->getSuggestions($query);

            return response()->json([
                'success' => true,
                'data' => $suggestions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des suggestions'
            ], 500);
        }
    }
}