<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EvenementAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom_evenement' => ['required', 'string', 'max:255'],
            'page_slug' => ['nullable', 'string', 'max:255'],
            'page_url' => ['nullable', 'string'],
            'referrer' => ['nullable', 'string'],
            'visitor_id' => ['nullable', 'string', 'max:255'],
            'session_id' => ['nullable', 'string', 'max:255'],
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'utm_term' => ['nullable', 'string', 'max:255'],
            'utm_content' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $event = EvenementAnalytics::query()->create([
            ...$data,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);

        return response()->json([
            'message' => 'Evenement analytics enregistre.',
            'data' => $event,
        ], 201);
    }
}
