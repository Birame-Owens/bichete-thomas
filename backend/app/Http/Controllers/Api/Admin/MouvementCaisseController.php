<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Caisse;
use App\Models\MouvementCaisse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MouvementCaisseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $mouvements = MouvementCaisse::query()
            ->with('caisse')
            ->when($request->filled('caisse_id'), fn ($query) => $query->where('caisse_id', $request->integer('caisse_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->when($request->filled('date_debut'), fn ($query) => $query->whereDate('date_mouvement', '>=', $request->date('date_debut')))
            ->when($request->filled('date_fin'), fn ($query) => $query->whereDate('date_mouvement', '<=', $request->date('date_fin')))
            ->latest('date_mouvement')
            ->paginate(20);

        return response()->json(['data' => $mouvements]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'caisse_id' => ['required', 'exists:caisses,id'],
            'type' => ['required', Rule::in(['entree', 'sortie'])],
            'montant' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'date_mouvement' => ['nullable', 'date'],
        ]);

        $caisse = Caisse::query()->findOrFail($data['caisse_id']);

        if ($caisse->statut === 'fermee') {
            return response()->json([
                'message' => 'Impossible d ajouter un mouvement sur une caisse fermee.',
            ], 422);
        }

        $mouvement = MouvementCaisse::query()->create($data);

        return response()->json([
            'message' => 'Mouvement de caisse cree.',
            'data' => $mouvement->load('caisse'),
        ], 201);
    }

    public function show(MouvementCaisse $mouvementCaisse): JsonResponse
    {
        return response()->json([
            'data' => $mouvementCaisse->load('caisse'),
        ]);
    }

    public function update(Request $request, MouvementCaisse $mouvementCaisse): JsonResponse
    {
        if ($mouvementCaisse->caisse->statut === 'fermee') {
            return response()->json([
                'message' => 'Impossible de modifier un mouvement d une caisse fermee.',
            ], 422);
        }

        $data = $request->validate([
            'type' => ['sometimes', Rule::in(['entree', 'sortie'])],
            'montant' => ['sometimes', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'date_mouvement' => ['nullable', 'date'],
        ]);

        $mouvementCaisse->update($data);

        return response()->json([
            'message' => 'Mouvement de caisse mis a jour.',
            'data' => $mouvementCaisse->load('caisse'),
        ]);
    }

    public function destroy(MouvementCaisse $mouvementCaisse): JsonResponse
    {
        if ($mouvementCaisse->caisse->statut === 'fermee') {
            return response()->json([
                'message' => 'Impossible de supprimer un mouvement d une caisse fermee.',
            ], 422);
        }

        $mouvementCaisse->delete();

        return response()->json(['message' => 'Mouvement de caisse supprime.']);
    }
}
