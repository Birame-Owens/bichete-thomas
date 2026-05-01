<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImageCoiffure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImageCoiffureController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ImageCoiffure::query()->with('coiffure')->orderBy('ordre')->paginate(15),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'coiffure_id' => ['required', 'exists:coiffures,id'],
            'url' => ['required', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:255'],
            'ordre' => ['sometimes', 'integer', 'min:0'],
            'principale' => ['sometimes', 'boolean'],
        ]);

        $image = ImageCoiffure::query()->create($data);

        return response()->json([
            'message' => 'Image creee.',
            'data' => $image->load('coiffure'),
        ], 201);
    }

    public function show(ImageCoiffure $imageCoiffure): JsonResponse
    {
        return response()->json([
            'data' => $imageCoiffure->load('coiffure'),
        ]);
    }

    public function update(Request $request, ImageCoiffure $imageCoiffure): JsonResponse
    {
        $data = $request->validate([
            'coiffure_id' => ['sometimes', 'exists:coiffures,id'],
            'url' => ['sometimes', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:255'],
            'ordre' => ['sometimes', 'integer', 'min:0'],
            'principale' => ['sometimes', 'boolean'],
        ]);

        $imageCoiffure->update($data);

        return response()->json([
            'message' => 'Image mise a jour.',
            'data' => $imageCoiffure->load('coiffure'),
        ]);
    }

    public function destroy(ImageCoiffure $imageCoiffure): JsonResponse
    {
        $imageCoiffure->delete();

        return response()->json([
            'message' => 'Image supprimee.',
        ]);
    }
}
