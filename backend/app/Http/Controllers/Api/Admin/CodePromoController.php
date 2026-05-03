<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CodePromo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CodePromoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min($request->integer('per_page', 15), 100));

        $codes = CodePromo::query()
            ->when($request->has('actif'), fn ($query) => $query->where('actif', $request->boolean('actif')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim($request->string('search')->toString());

                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('code', 'ilike', "%{$search}%")
                        ->orWhere('nom', 'ilike', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage);

        return response()->json(['data' => $codes]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedCodePromoData($request);

        $codePromo = CodePromo::query()->create($data);

        return response()->json([
            'message' => 'Code promo cree.',
            'data' => $codePromo,
        ], 201);
    }

    public function show(CodePromo $codePromo): JsonResponse
    {
        return response()->json(['data' => $codePromo]);
    }

    public function update(Request $request, CodePromo $codePromo): JsonResponse
    {
        $data = $this->validatedCodePromoData($request, $codePromo);

        $codePromo->update($data);

        return response()->json([
            'message' => 'Code promo mis a jour.',
            'data' => $codePromo,
        ]);
    }

    public function destroy(CodePromo $codePromo): JsonResponse
    {
        $codePromo->delete();

        return response()->json(['message' => 'Code promo supprime.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedCodePromoData(Request $request, ?CodePromo $codePromo = null): array
    {
        if ($request->has('code')) {
            $request->merge([
                'code' => $this->normalizeCode($request->string('code')->toString()),
            ]);
        }

        $codeRule = Rule::unique('codes_promo', 'code');

        if ($codePromo) {
            $codeRule->ignore($codePromo->id);
        }

        $data = $request->validate([
            'code' => [
                $codePromo ? 'sometimes' : 'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9_-]+$/',
                $codeRule,
            ],
            'nom' => ['nullable', 'string', 'max:255'],
            'type_reduction' => [$codePromo ? 'sometimes' : 'required', Rule::in(['pourcentage', 'montant'])],
            'valeur' => [$codePromo ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
            'limite_utilisation' => ['nullable', 'integer', 'min:1'],
            'nombre_utilisations' => ['sometimes', 'integer', 'min:0'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $typeReduction = $data['type_reduction'] ?? $codePromo?->type_reduction;
        $valeur = array_key_exists('valeur', $data) ? (float) $data['valeur'] : (float) ($codePromo?->valeur ?? 0);

        if ($typeReduction === 'pourcentage' && $valeur > 100) {
            throw ValidationException::withMessages([
                'valeur' => 'Une reduction en pourcentage ne peut pas depasser 100%.',
            ]);
        }

        $dateDebut = array_key_exists('date_debut', $data) ? $data['date_debut'] : $codePromo?->date_debut;
        $dateFin = array_key_exists('date_fin', $data) ? $data['date_fin'] : $codePromo?->date_fin;

        if ($dateDebut && $dateFin && strtotime((string) $dateFin) < strtotime((string) $dateDebut)) {
            throw ValidationException::withMessages([
                'date_fin' => 'La date de fin doit etre apres la date de debut.',
            ]);
        }

        $limite = array_key_exists('limite_utilisation', $data)
            ? $data['limite_utilisation']
            : $codePromo?->limite_utilisation;
        $utilisations = array_key_exists('nombre_utilisations', $data)
            ? $data['nombre_utilisations']
            : $codePromo?->nombre_utilisations;

        if ($limite !== null && $utilisations !== null && (int) $utilisations > (int) $limite) {
            throw ValidationException::withMessages([
                'nombre_utilisations' => 'Le nombre d utilisations ne peut pas depasser la limite.',
            ]);
        }

        return $data;
    }

    private function normalizeCode(string $code): string
    {
        return (string) preg_replace('/\s+/', '', Str::upper(trim($code)));
    }
}
