<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $clients = Client::query()
            ->with(['preferences', 'blacklistActive'])
            ->when($request->has('blackliste'), fn ($query) => $query->where('est_blackliste', $request->boolean('blackliste')))
            ->when($request->filled('source'), fn ($query) => $query->where('source', $request->string('source')->toString()))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->where('nom', 'ilike', "%{$search}%")
                        ->orWhere('prenom', 'ilike', "%{$search}%")
                        ->orWhere('telephone', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15);

        return response()->json(['data' => $clients]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'telephone' => ['required', 'string', 'max:50', 'unique:clients,telephone'],
            'email' => ['nullable', 'email', 'max:255'],
            'source' => ['sometimes', Rule::in(['en_ligne', 'physique'])],
        ]);

        $client = Client::query()->create($data);
        $client->preferences()->create([]);

        return response()->json([
            'message' => 'Client cree.',
            'data' => $client->load(['preferences', 'blacklistActive']),
        ], 201);
    }

    public function show(Client $client): JsonResponse
    {
        return response()->json([
            'data' => $client->load(['user.role', 'preferences', 'listeNoire']),
        ]);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:255'],
            'prenom' => ['sometimes', 'string', 'max:255'],
            'telephone' => ['sometimes', 'string', 'max:50', Rule::unique('clients', 'telephone')->ignore($client->id)],
            'email' => ['nullable', 'email', 'max:255'],
            'source' => ['sometimes', Rule::in(['en_ligne', 'physique'])],
            'nombre_reservations_terminees' => ['sometimes', 'integer', 'min:0'],
            'fidelite_disponible' => ['sometimes', 'boolean'],
        ]);

        $client->update($data);

        return response()->json([
            'message' => 'Client mis a jour.',
            'data' => $client->load(['preferences', 'blacklistActive']),
        ]);
    }

    public function destroy(Client $client): JsonResponse
    {
        $client->delete();

        return response()->json(['message' => 'Client supprime.']);
    }

    public function blacklist(Request $request, Client $client): JsonResponse
    {
        $data = $request->validate([
            'raison' => ['nullable', 'string'],
        ]);

        $client->listeNoire()->where('actif', true)->update([
            'actif' => false,
            'retire_at' => now(),
        ]);

        $client->listeNoire()->create([
            'raison' => $data['raison'] ?? null,
            'actif' => true,
            'blackliste_at' => now(),
        ]);

        $client->update(['est_blackliste' => true]);

        return response()->json([
            'message' => 'Client ajoute a la liste noire.',
            'data' => $client->load(['preferences', 'blacklistActive']),
        ]);
    }

    public function unblacklist(Client $client): JsonResponse
    {
        $client->listeNoire()->where('actif', true)->update([
            'actif' => false,
            'retire_at' => now(),
        ]);

        $client->update(['est_blackliste' => false]);

        return response()->json([
            'message' => 'Client retire de la liste noire.',
            'data' => $client->load(['preferences', 'listeNoire']),
        ]);
    }
}
