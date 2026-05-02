<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Depense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $depenses = Depense::query()
            ->with('categorie')
            ->when($request->filled('categorie_depense_id'), fn ($query) => $query->where('categorie_depense_id', $request->integer('categorie_depense_id')))
            ->when($request->filled('date_debut'), fn ($query) => $query->whereDate('date_depense', '>=', $request->date('date_debut')))
            ->when($request->filled('date_fin'), fn ($query) => $query->whereDate('date_depense', '<=', $request->date('date_fin')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->where('titre', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%")
                        ->orWhere('reference', 'ilike', "%{$search}%");
                });
            })
            ->latest('date_depense')
            ->paginate(15);

        return response()->json([
            'data' => $depenses,
            'meta' => [
                'total_montant' => (float) Depense::query()->sum('montant'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categorie_depense_id' => ['nullable', 'exists:categories_depenses,id'],
            'titre' => ['required', 'string', 'max:255'],
            'montant' => ['required', 'numeric', 'min:0'],
            'date_depense' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'mode_paiement' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $depense = Depense::query()->create($data);

        return response()->json([
            'message' => 'Depense creee.',
            'data' => $depense->load('categorie'),
        ], 201);
    }

    public function show(Depense $depense): JsonResponse
    {
        return response()->json([
            'data' => $depense->load('categorie'),
        ]);
    }

    public function update(Request $request, Depense $depense): JsonResponse
    {
        $data = $request->validate([
            'categorie_depense_id' => ['nullable', 'exists:categories_depenses,id'],
            'titre' => ['sometimes', 'string', 'max:255'],
            'montant' => ['sometimes', 'numeric', 'min:0'],
            'date_depense' => ['sometimes', 'date'],
            'description' => ['nullable', 'string'],
            'mode_paiement' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $depense->update($data);

        return response()->json([
            'message' => 'Depense mise a jour.',
            'data' => $depense->load('categorie'),
        ]);
    }

    public function destroy(Depense $depense): JsonResponse
    {
        $depense->delete();

        return response()->json(['message' => 'Depense supprimee.']);
    }
}
