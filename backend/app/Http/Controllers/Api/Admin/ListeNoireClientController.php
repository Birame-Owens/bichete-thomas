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
        $perPage = max(1, min($request->integer('per_page', 15), 100));

        $items = ListeNoireClient::query()
            ->with('client')
            ->when($request->has('actif'), fn ($query) => $query->where('actif', $request->boolean('actif')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim($request->string('search')->toString());

                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('raison', 'ilike', "%{$search}%")
                        ->orWhereHas('client', function ($clientQuery) use ($search): void {
                            $clientQuery->where('nom', 'ilike', "%{$search}%")
                                ->orWhere('prenom', 'ilike', "%{$search}%")
                                ->orWhere('telephone', 'ilike', "%{$search}%")
                                ->orWhere('email', 'ilike', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate($perPage);

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
            'raison' => ['nullable', 'string', 'max:2000'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('actif', $data) && $data['actif'] === true) {
            if (! $listeNoireClient->blackliste_at) {
                $data['blackliste_at'] = now();
            }
            $data['retire_at'] = null;
        }

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
