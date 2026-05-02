<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CategorieCoiffure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CategorieCoiffureController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => CategorieCoiffure::query()
                ->withCount('coiffures')
                ->when($request->filled('search'), function ($query) use ($request): void {
                    $search = $request->string('search')->toString();
                    $query->where(function ($query) use ($search): void {
                        $query->where('nom', 'ilike', "%{$search}%")
                            ->orWhere('description', 'ilike', "%{$search}%");
                    });
                })
                ->when($request->filled('actif'), function ($query) use ($request): void {
                    $query->where('actif', $request->boolean('actif'));
                })
                ->latest()
                ->paginate(15),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255', 'unique:categories_coiffures,nom'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:4096'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = Storage::url($request->file('image')->store('catalogue/categories', 'public'));
        }

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
            'image' => ['nullable', 'image', 'max:4096'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = Storage::url($request->file('image')->store('catalogue/categories', 'public'));
        }

        $categorieCoiffure->update($data);

        return response()->json([
            'message' => 'Categorie mise a jour.',
            'data' => $categorieCoiffure,
        ]);
    }

    public function destroy(CategorieCoiffure $categorieCoiffure): JsonResponse
    {
        if ($categorieCoiffure->coiffures()->exists()) {
            return response()->json([
                'message' => 'Cette categorie contient des coiffures. Desactivez-la au lieu de la supprimer.',
            ], 422);
        }

        $categorieCoiffure->delete();

        return response()->json([
            'message' => 'Categorie supprimee.',
        ]);
    }
}
