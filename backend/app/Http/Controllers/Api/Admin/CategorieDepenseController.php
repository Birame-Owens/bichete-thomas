<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CategorieDepense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategorieDepenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = CategorieDepense::query()
            ->withCount('depenses')
            ->when($request->has('actif'), fn ($query) => $query->where('actif', $request->boolean('actif')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where('nom', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('nom')
            ->paginate(15);

        return response()->json(['data' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255', 'unique:categories_depenses,nom'],
            'description' => ['nullable', 'string'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $categorie = CategorieDepense::query()->create($data);

        return response()->json([
            'message' => 'Categorie de depense creee.',
            'data' => $categorie,
        ], 201);
    }

    public function show(CategorieDepense $categorieDepense): JsonResponse
    {
        return response()->json([
            'data' => $categorieDepense->load('depenses'),
        ]);
    }

    public function update(Request $request, CategorieDepense $categorieDepense): JsonResponse
    {
        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:255', Rule::unique('categories_depenses', 'nom')->ignore($categorieDepense->id)],
            'description' => ['nullable', 'string'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $categorieDepense->update($data);

        return response()->json([
            'message' => 'Categorie de depense mise a jour.',
            'data' => $categorieDepense,
        ]);
    }

    public function destroy(CategorieDepense $categorieDepense): JsonResponse
    {
        $categorieDepense->delete();

        return response()->json(['message' => 'Categorie de depense supprimee.']);
    }
}
