<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ListeNoireClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListeNoireClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = ListeNoireClient::query()
            ->with('client')
            ->when($request->has('actif'), fn ($query) => $query->where('actif', $request->boolean('actif')))
            ->latest()
            ->paginate(15);

        return response()->json(['data' => $items]);
    }

    public function show(ListeNoireClient $listeNoireClient): JsonResponse
    {
        return response()->json([
            'data' => $listeNoireClient->load('client'),
        ]);
    }

    public function update(Request $request, ListeNoireClient $listeNoireClient): JsonResponse
    {
        $data = $request->validate([
            'raison' => ['nullable', 'string'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('actif', $data) && $data['actif'] === false) {
            $data['retire_at'] = now();
        }

        $listeNoireClient->update($data);

        $listeNoireClient->client->update([
            'est_blackliste' => $listeNoireClient->client->listeNoire()->where('actif', true)->exists(),
        ]);

        return response()->json([
            'message' => 'Entree liste noire mise a jour.',
            'data' => $listeNoireClient->load('client'),
        ]);
    }
}
