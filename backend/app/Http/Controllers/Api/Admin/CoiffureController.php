<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coiffure;
use App\Models\ImageCoiffure;
use App\Models\VarianteCoiffure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CoiffureController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min($request->integer('per_page', 15), 100));

        return response()->json([
            'data' => Coiffure::query()
                ->with(['categorie', 'variantes', 'options', 'images'])
                ->when($request->integer('categorie_coiffure_id'), function ($query, int $categorieId): void {
                    $query->where('categorie_coiffure_id', $categorieId);
                })
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
                ->paginate($perPage),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categorie_coiffure_id' => ['required', 'exists:categories_coiffures,id'],
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:4096'],
            'images' => ['sometimes', 'array', 'max:4'],
            'images.*' => ['image', 'max:4096'],
            'actif' => ['sometimes', 'boolean'],
            'variantes' => ['sometimes', 'array'],
            'variantes.*.nom' => ['required_with:variantes', 'string', 'max:255'],
            'variantes.*.prix' => ['required_with:variantes', 'numeric', 'min:0'],
            'variantes.*.duree_minutes' => ['required_with:variantes', 'integer', 'min:1'],
            'variantes.*.actif' => ['sometimes', 'boolean'],
            'option_ids' => ['sometimes', 'array'],
            'option_ids.*' => ['integer', 'exists:options_coiffures,id'],
        ]);

        $optionIds = $data['option_ids'] ?? [];
        $variantes = $data['variantes'] ?? [];
        unset($data['option_ids']);
        unset($data['variantes']);
        unset($data['images']);

        if ($request->hasFile('image')) {
            $data['image'] = Storage::url($request->file('image')->store('catalogue/coiffures', 'public'));
        }

        $coiffure = Coiffure::query()->create($data);
        $coiffure->options()->sync($optionIds);
        $this->syncVariantes($coiffure, $variantes);
        $this->syncImages($coiffure, $request);

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
            'image' => ['nullable', 'image', 'max:4096'],
            'images' => ['sometimes', 'array', 'max:4'],
            'images.*' => ['image', 'max:4096'],
            'actif' => ['sometimes', 'boolean'],
            'variantes' => ['sometimes', 'array'],
            'variantes.*.nom' => ['required_with:variantes', 'string', 'max:255'],
            'variantes.*.prix' => ['required_with:variantes', 'numeric', 'min:0'],
            'variantes.*.duree_minutes' => ['required_with:variantes', 'integer', 'min:1'],
            'variantes.*.actif' => ['sometimes', 'boolean'],
            'option_ids' => ['sometimes', 'array'],
            'option_ids.*' => ['integer', 'exists:options_coiffures,id'],
        ]);

        $optionIds = $data['option_ids'] ?? null;
        $variantes = $data['variantes'] ?? null;
        unset($data['option_ids']);
        unset($data['variantes']);
        unset($data['images']);

        if ($request->hasFile('image')) {
            $data['image'] = Storage::url($request->file('image')->store('catalogue/coiffures', 'public'));
        }

        $coiffure->update($data);

        if ($optionIds !== null) {
            $coiffure->options()->sync($optionIds);
        }

        if ($variantes !== null) {
            $this->syncVariantes($coiffure, $variantes);
        }

        $this->syncImages($coiffure, $request, replace: true);

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

    /**
     * @param array<int, array<string, mixed>> $variantes
     */
    private function syncVariantes(Coiffure $coiffure, array $variantes): void
    {
        if ($variantes === []) {
            return;
        }

        $coiffure->variantes()->delete();

        foreach ($variantes as $variante) {
            VarianteCoiffure::query()->create([
                'coiffure_id' => $coiffure->id,
                'nom' => $variante['nom'],
                'prix' => $variante['prix'],
                'duree_minutes' => $variante['duree_minutes'],
                'actif' => $variante['actif'] ?? true,
            ]);
        }
    }

    private function syncImages(Coiffure $coiffure, Request $request, bool $replace = false): void
    {
        if (!$request->hasFile('images')) {
            return;
        }

        if ($replace) {
            $coiffure->images()->delete();
        }

        foreach ($request->file('images') as $index => $image) {
            $url = Storage::url($image->store('catalogue/coiffures', 'public'));

            ImageCoiffure::query()->create([
                'coiffure_id' => $coiffure->id,
                'url' => $url,
                'alt' => $coiffure->nom,
                'ordre' => $index + 1,
                'principale' => $index === 0,
            ]);

            if ($index === 0) {
                $coiffure->update(['image' => $url]);
            }
        }
    }
}
