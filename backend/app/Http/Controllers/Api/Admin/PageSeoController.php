<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageSeo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PageSeoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pages = PageSeo::query()
            ->when($request->filled('type_page'), fn ($query) => $query->where('type_page', $request->string('type_page')->toString()))
            ->when($request->filled('actif'), fn ($query) => $query->where('actif', $request->boolean('actif')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->where('slug', 'ilike', "%{$search}%")
                        ->orWhere('titre', 'ilike', "%{$search}%")
                        ->orWhere('meta_title', 'ilike', "%{$search}%")
                        ->orWhere('meta_description', 'ilike', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20);

        return response()->json(['data' => $pages]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);

        $page = PageSeo::query()->create($data);

        return response()->json([
            'message' => 'Page SEO creee.',
            'data' => $page,
        ], 201);
    }

    public function show(PageSeo $pageSeo): JsonResponse
    {
        return response()->json(['data' => $pageSeo]);
    }

    public function update(Request $request, PageSeo $pageSeo): JsonResponse
    {
        $data = $this->validatedData($request, $pageSeo);

        $pageSeo->update($data);

        return response()->json([
            'message' => 'Page SEO mise a jour.',
            'data' => $pageSeo,
        ]);
    }

    public function destroy(PageSeo $pageSeo): JsonResponse
    {
        $pageSeo->delete();

        return response()->json(['message' => 'Page SEO supprimee.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?PageSeo $pageSeo = null): array
    {
        return $request->validate([
            'slug' => ['required', 'string', 'max:255', Rule::unique('pages_seo', 'slug')->ignore($pageSeo?->id)],
            'titre' => ['required', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string', 'max:100'],
            'canonical_url' => ['nullable', 'url', 'max:255'],
            'image_og' => ['nullable', 'string', 'max:255'],
            'robots' => ['sometimes', 'string', 'max:100'],
            'type_page' => ['nullable', 'string', 'max:100'],
            'cible_type' => ['nullable', 'string', 'max:255'],
            'cible_id' => ['nullable', 'integer', 'min:1'],
            'schema_json' => ['nullable', 'array'],
            'actif' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);
    }
}
