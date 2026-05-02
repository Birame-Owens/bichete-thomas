<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EvenementAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvenementAnalyticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = EvenementAnalytics::query()
            ->when($request->filled('nom_evenement'), fn ($query) => $query->where('nom_evenement', $request->string('nom_evenement')->toString()))
            ->when($request->filled('page_slug'), fn ($query) => $query->where('page_slug', $request->string('page_slug')->toString()))
            ->when($request->filled('utm_source'), fn ($query) => $query->where('utm_source', $request->string('utm_source')->toString()))
            ->when($request->filled('date_debut'), fn ($query) => $query->whereDate('occurred_at', '>=', $request->date('date_debut')))
            ->when($request->filled('date_fin'), fn ($query) => $query->whereDate('occurred_at', '<=', $request->date('date_fin')));

        $resume = [
            'total_evenements' => (clone $query)->count(),
            'visiteurs_uniques' => (clone $query)->whereNotNull('visitor_id')->distinct('visitor_id')->count('visitor_id'),
            'pages_vues' => (clone $query)->where('nom_evenement', 'page_view')->count(),
        ];

        return response()->json([
            'data' => $query->latest('occurred_at')->paginate(30),
            'resume' => $resume,
        ]);
    }

    public function show(EvenementAnalytics $evenementAnalytics): JsonResponse
    {
        return response()->json(['data' => $evenementAnalytics]);
    }
}
