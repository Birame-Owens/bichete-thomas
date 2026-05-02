<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CodePromo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CodePromoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $codes = CodePromo::query()
            ->when($request->has('actif'), fn ($query) => $query->where('actif', $request->boolean('actif')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where('code', 'like', "%{$search}%")
                    ->orWhere('nom', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(15);

        return response()->json(['data' => $codes]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:codes_promo,code'],
            'nom' => ['nullable', 'string', 'max:255'],
            'type_reduction' => ['required', Rule::in(['pourcentage', 'montant'])],
            'valeur' => ['required', 'numeric', 'min:0'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'limite_utilisation' => ['nullable', 'integer', 'min:1'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $data['code'] = Str::upper($data['code']);

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
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('codes_promo', 'code')->ignore($codePromo->id)],
            'nom' => ['nullable', 'string', 'max:255'],
            'type_reduction' => ['sometimes', Rule::in(['pourcentage', 'montant'])],
            'valeur' => ['sometimes', 'numeric', 'min:0'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'limite_utilisation' => ['nullable', 'integer', 'min:1'],
            'nombre_utilisations' => ['sometimes', 'integer', 'min:0'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['code'])) {
            $data['code'] = Str::upper($data['code']);
        }

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
}
