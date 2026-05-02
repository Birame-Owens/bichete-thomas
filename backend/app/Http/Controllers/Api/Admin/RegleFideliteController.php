<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RegleFidelite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RegleFideliteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $regles = RegleFidelite::query()
            ->when($request->has('actif'), fn ($query) => $query->where('actif', $request->boolean('actif')))
            ->latest()
            ->paginate(15);

        return response()->json(['data' => $regles]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'nombre_reservations_requis' => ['required', 'integer', 'min:1'],
            'type_recompense' => ['required', Rule::in(['pourcentage', 'montant'])],
            'valeur_recompense' => ['required', 'numeric', 'min:0'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $regle = RegleFidelite::query()->create($data);

        return response()->json([
            'message' => 'Regle de fidelite creee.',
            'data' => $regle,
        ], 201);
    }

    public function show(RegleFidelite $regleFidelite): JsonResponse
    {
        return response()->json(['data' => $regleFidelite]);
    }

    public function update(Request $request, RegleFidelite $regleFidelite): JsonResponse
    {
        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:255'],
            'nombre_reservations_requis' => ['sometimes', 'integer', 'min:1'],
            'type_recompense' => ['sometimes', Rule::in(['pourcentage', 'montant'])],
            'valeur_recompense' => ['sometimes', 'numeric', 'min:0'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $regleFidelite->update($data);

        return response()->json([
            'message' => 'Regle de fidelite mise a jour.',
            'data' => $regleFidelite,
        ]);
    }

    public function destroy(RegleFidelite $regleFidelite): JsonResponse
    {
        $regleFidelite->delete();

        return response()->json(['message' => 'Regle de fidelite supprimee.']);
    }
}
