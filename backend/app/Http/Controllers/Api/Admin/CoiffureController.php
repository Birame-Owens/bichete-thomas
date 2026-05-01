<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coiffure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CoiffureController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Coiffure::query()
                ->with(['categorie', 'variantes', 'options', 'images'])
                ->latest()
                ->paginate(15),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categorie_coiffure_id' => ['required', 'exists:categories_coiffures,id'],
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'actif' => ['sometimes', 'boolean'],
            'option_ids' => ['sometimes', 'array'],
            'option_ids.*' => ['integer', 'exists:options_coiffures,id'],
        ]);

        $optionIds = $data['option_ids'] ?? [];
        unset($data['option_ids']);

        $coiffure = Coiffure::query()->create($data);
        $coiffure->options()->sync($optionIds);

        return response()->json([
            'message' => 'Coiffure creee.',
            'data' => $coiffure->load(['categorie', 'variantes', 'options', 'images']),
        ], 201);
    }

    public function show(Coiffure $coiffure): JsonResponse
    {
        return response()->json([
            'data' => $coiffure->load(['categorie', 'variantes', 'options', 'images']),
        ]);
    }

    public function update(Request $request, Coiffure $coiffure): JsonResponse
    {
        $data = $request->validate([
            'categorie_coiffure_id' => ['sometimes', 'exists:categories_coiffures,id'],
            'nom' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'actif' => ['sometimes', 'boolean'],
            'option_ids' => ['sometimes', 'array'],
            'option_ids.*' => ['integer', 'exists:options_coiffures,id'],
        ]);

        $optionIds = $data['option_ids'] ?? null;
        unset($data['option_ids']);

        $coiffure->update($data);

        if ($optionIds !== null) {
            $coiffure->options()->sync($optionIds);
        }

        return response()->json([
            'message' => 'Coiffure mise a jour.',
            'data' => $coiffure->load(['categorie', 'variantes', 'options', 'images']),
        ]);
    }

    public function destroy(Coiffure $coiffure): JsonResponse
    {
        $coiffure->delete();

        return response()->json([
            'message' => 'Coiffure supprimee.',
        ]);
    }
}
