<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\PreferenceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreferenceClientController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => PreferenceClient::query()->with('client')->latest()->paginate(15),
        ]);
    }

    public function show(PreferenceClient $preferenceClient): JsonResponse
    {
        return response()->json([
            'data' => $preferenceClient->load('client'),
        ]);
    }

    public function update(Request $request, PreferenceClient $preferenceClient): JsonResponse
    {
        $data = $request->validate([
            'coiffures_preferees' => ['nullable', 'array'],
            'options_preferees' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
            'notifications_whatsapp' => ['sometimes', 'boolean'],
            'notifications_promos' => ['sometimes', 'boolean'],
        ]);

        $preferenceClient->update($data);

        return response()->json([
            'message' => 'Preferences client mises a jour.',
            'data' => $preferenceClient->load('client'),
        ]);
    }

    public function updateForClient(Request $request, Client $client): JsonResponse
    {
        $data = $request->validate([
            'coiffures_preferees' => ['nullable', 'array'],
            'options_preferees' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
            'notifications_whatsapp' => ['sometimes', 'boolean'],
            'notifications_promos' => ['sometimes', 'boolean'],
        ]);

        $preferences = $client->preferences()->updateOrCreate(
            ['client_id' => $client->id],
            $data
        );

        return response()->json([
            'message' => 'Preferences client mises a jour.',
            'data' => $preferences->load('client'),
        ]);
    }
}
