<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CategorieCoiffure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategorieCoiffureController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => CategorieCoiffure::query()
                ->withCount('coiffures')
                ->latest()
                ->paginate(15),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255', 'unique:categories_coiffures,nom'],
            'description' => ['nullable', 'string'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $categorie = CategorieCoiffure::query()->create($data);

        return response()->json([
            'message' => 'Categorie creee.',
            'data' => $categorie,
        ], 201);
    }

    public function show(CategorieCoiffure $categorieCoiffure): JsonResponse
    {
        return response()->json([
            'data' => $categorieCoiffure->load('coiffures'),
        ]);
    }

    public function update(Request $request, CategorieCoiffure $categorieCoiffure): JsonResponse
    {
        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:255', Rule::unique('categories_coiffures', 'nom')->ignore($categorieCoiffure->id)],
            'description' => ['nullable', 'string'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $categorieCoiffure->update($data);

        return response()->json([
            'message' => 'Categorie mise a jour.',
            'data' => $categorieCoiffure,
        ]);
    }

    public function destroy(CategorieCoiffure $categorieCoiffure): JsonResponse
    {
        $categorieCoiffure->delete();

        return response()->json([
            'message' => 'Categorie supprimee.',
        ]);
    }
}
