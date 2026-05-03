<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\PreferenceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreferenceClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min($request->integer('per_page', 15), 100));

        return response()->json([
            'data' => PreferenceClient::query()
                ->with('client')
                ->when($request->filled('search'), function ($query) use ($request): void {
                    $search = trim($request->string('search')->toString());

                    $query->whereHas('client', function ($clientQuery) use ($search): void {
                        $clientQuery->where('nom', 'ilike', "%{$search}%")
                            ->orWhere('prenom', 'ilike', "%{$search}%")
                            ->orWhere('telephone', 'ilike', "%{$search}%")
                            ->orWhere('email', 'ilike', "%{$search}%");
                    });
                })
                ->latest()
                ->paginate($perPage),
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
            'coiffures_preferees.*' => ['string', 'max:120'],
            'options_preferees' => ['nullable', 'array'],
            'options_preferees.*' => ['string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
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
            'coiffures_preferees.*' => ['string', 'max:120'],
            'options_preferees' => ['nullable', 'array'],
            'options_preferees.*' => ['string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
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
