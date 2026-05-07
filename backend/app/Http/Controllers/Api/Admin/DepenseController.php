<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Depense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class DepenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->filteredQuery($request);
        $summaryQuery = clone $query;
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        $depenses = $query
            ->with('categorie')
            ->latest('date_depense')
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $depenses,
            'meta' => [
                'total_montant' => (float) (clone $summaryQuery)->sum('montant'),
                'nombre_depenses' => (clone $summaryQuery)->count(),
                'total_mois_courant' => (float) Depense::query()
                    ->whereBetween('date_depense', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                    ->sum('montant'),
                'total_aujourdhui' => (float) Depense::query()
                    ->whereDate('date_depense', now()->toDateString())
                    ->sum('montant'),
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

    private function filteredQuery(Request $request): Builder
    {
        return Depense::query()
            ->when($request->filled('categorie_depense_id'), fn (Builder $query) => $query->where('categorie_depense_id', $request->integer('categorie_depense_id')))
            ->when($request->filled('mode_paiement'), fn (Builder $query) => $query->where('mode_paiement', $request->string('mode_paiement')->toString()))
            ->when($request->filled('date_debut'), fn (Builder $query) => $query->whereDate('date_depense', '>=', $request->date('date_debut')))
            ->when($request->filled('date_fin'), fn (Builder $query) => $query->whereDate('date_depense', '<=', $request->date('date_fin')))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function (Builder $query) use ($search): void {
                    $query->where('titre', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%")
                        ->orWhere('reference', 'ilike', "%{$search}%");
                });
            });
    }
}
