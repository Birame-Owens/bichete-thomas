<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\VarianteCoiffure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VarianteCoiffureController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => VarianteCoiffure::query()->with('coiffure')->latest()->paginate(15),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'coiffure_id' => ['required', 'exists:coiffures,id'],
            'nom' => ['required', 'string', 'max:255'],
            'prix' => ['required', 'numeric', 'min:0'],
            'duree_minutes' => ['required', 'integer', 'min:1'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $variante = VarianteCoiffure::query()->create($data);

        return response()->json([
            'message' => 'Variante creee.',
            'data' => $variante->load('coiffure'),
        ], 201);
    }

    public function show(VarianteCoiffure $varianteCoiffure): JsonResponse
    {
        return response()->json([
            'data' => $varianteCoiffure->load('coiffure'),
        ]);
    }

    public function update(Request $request, VarianteCoiffure $varianteCoiffure): JsonResponse
    {
        $data = $request->validate([
            'coiffure_id' => ['sometimes', 'exists:coiffures,id'],
            'nom' => ['sometimes', 'string', 'max:255'],
            'prix' => ['sometimes', 'numeric', 'min:0'],
            'duree_minutes' => ['sometimes', 'integer', 'min:1'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $varianteCoiffure->update($data);

        return response()->json([
            'message' => 'Variante mise a jour.',
            'data' => $varianteCoiffure->load('coiffure'),
        ]);
    }

    public function destroy(VarianteCoiffure $varianteCoiffure): JsonResponse
    {
        $varianteCoiffure->delete();

        return response()->json([
            'message' => 'Variante supprimee.',
        ]);
    }
}
