<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RegleFidelite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegleFideliteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min($request->integer('per_page', 15), 100));

        $regles = RegleFidelite::query()
            ->when($request->has('actif'), fn ($query) => $query->where('actif', $request->boolean('actif')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim($request->string('search')->toString());

                $query->where('nom', 'ilike', "%{$search}%");
            })
            ->latest()
            ->paginate($perPage);

        return response()->json(['data' => $regles]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedRegleData($request);

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
        $data = $this->validatedRegleData($request, $regleFidelite);

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

    /**
     * @return array<string, mixed>
     */
    private function validatedRegleData(Request $request, ?RegleFidelite $regleFidelite = null): array
    {
        $data = $request->validate([
            'nom' => [$regleFidelite ? 'sometimes' : 'required', 'string', 'max:255'],
            'nombre_reservations_requis' => [$regleFidelite ? 'sometimes' : 'required', 'integer', 'min:1', 'max:1000'],
            'type_recompense' => [$regleFidelite ? 'sometimes' : 'required', Rule::in(['pourcentage', 'montant'])],
            'valeur_recompense' => [$regleFidelite ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $typeRecompense = $data['type_recompense'] ?? $regleFidelite?->type_recompense;
        $valeur = array_key_exists('valeur_recompense', $data)
            ? (float) $data['valeur_recompense']
            : (float) ($regleFidelite?->valeur_recompense ?? 0);

        if ($typeRecompense === 'pourcentage' && $valeur > 100) {
            throw ValidationException::withMessages([
                'valeur_recompense' => 'Une recompense en pourcentage ne peut pas depasser 100%.',
            ]);
        }

        return $data;
    }
}
