<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Caisse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CaisseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $caisses = Caisse::query()
            ->withCount('mouvements')
            ->when($request->filled('statut'), fn ($query) => $query->where('statut', $request->string('statut')->toString()))
            ->when($request->filled('date_debut'), fn ($query) => $query->whereDate('date', '>=', $request->date('date_debut')))
            ->when($request->filled('date_fin'), fn ($query) => $query->whereDate('date', '<=', $request->date('date_fin')))
            ->latest('date')
            ->paginate(15);

        return response()->json(['data' => $caisses]);
    }

    public function today(): JsonResponse
    {
        $caisse = Caisse::query()
            ->with('mouvements')
            ->whereDate('date', now()->toDateString())
            ->first();

        return response()->json([
            'data' => $caisse,
            'resume' => $caisse ? $this->resume($caisse) : null,
            'message' => $caisse ? null : 'Aucune caisse ouverte pour aujourd hui.',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'unique:caisses,date'],
            'solde_ouverture' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        $caisse = Caisse::query()->create([
            ...$data,
            'statut' => 'ouverte',
            'ouverte_at' => now(),
        ]);

        return response()->json([
            'message' => 'Caisse ouverte.',
            'data' => $caisse,
            'resume' => $this->resume($caisse),
        ], 201);
    }

    public function openToday(Request $request): JsonResponse
    {
        $data = $request->validate([
            'solde_ouverture' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        $caisse = Caisse::query()->firstOrCreate(
            ['date' => now()->toDateString()],
            [
                'solde_ouverture' => $data['solde_ouverture'],
                'statut' => 'ouverte',
                'ouverte_at' => now(),
                'note' => $data['note'] ?? null,
            ]
        );

        return response()->json([
            'message' => $caisse->wasRecentlyCreated ? 'Caisse du jour ouverte.' : 'La caisse du jour existe deja.',
            'data' => $caisse,
            'resume' => $this->resume($caisse),
        ], $caisse->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Caisse $caisse): JsonResponse
    {
        return response()->json([
            'data' => $caisse->load('mouvements'),
            'resume' => $this->resume($caisse),
        ]);
    }

    public function update(Request $request, Caisse $caisse): JsonResponse
    {
        $data = $request->validate([
            'date' => ['sometimes', 'date', Rule::unique('caisses', 'date')->ignore($caisse->id)],
            'solde_ouverture' => ['sometimes', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        if ($caisse->statut === 'fermee') {
            return response()->json([
                'message' => 'Impossible de modifier une caisse fermee.',
            ], 422);
        }

        $caisse->update($data);

        return response()->json([
            'message' => 'Caisse mise a jour.',
            'data' => $caisse,
            'resume' => $this->resume($caisse),
        ]);
    }

    public function close(Request $request, Caisse $caisse): JsonResponse
    {
        $data = $request->validate([
            'solde_fermeture' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        if ($caisse->statut === 'fermee') {
            return response()->json([
                'message' => 'Cette caisse est deja fermee.',
                'data' => $caisse,
                'resume' => $this->resume($caisse),
            ]);
        }

        $soldeTheorique = $caisse->soldeTheorique();

        $caisse->update([
            'solde_fermeture' => $data['solde_fermeture'] ?? $soldeTheorique,
            'statut' => 'fermee',
            'fermee_at' => now(),
            'note' => $data['note'] ?? $caisse->note,
        ]);

        return response()->json([
            'message' => 'Caisse fermee.',
            'data' => $caisse,
            'resume' => $this->resume($caisse),
        ]);
    }

    public function destroy(Caisse $caisse): JsonResponse
    {
        if ($caisse->statut === 'fermee') {
            return response()->json([
                'message' => 'Impossible de supprimer une caisse fermee.',
            ], 422);
        }

        $caisse->delete();

        return response()->json(['message' => 'Caisse supprimee.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function resume(Caisse $caisse): array
    {
        $entrees = $caisse->totalEntrees();
        $sorties = $caisse->totalSorties();
        $soldeTheorique = (float) $caisse->solde_ouverture + $entrees - $sorties;
        $ecart = $caisse->solde_fermeture === null ? null : (float) $caisse->solde_fermeture - $soldeTheorique;

        return [
            'total_entrees' => $entrees,
            'total_sorties' => $sorties,
            'solde_theorique' => $soldeTheorique,
            'solde_fermeture' => $caisse->solde_fermeture === null ? null : (float) $caisse->solde_fermeture,
            'ecart' => $ecart,
        ];
    }
}
