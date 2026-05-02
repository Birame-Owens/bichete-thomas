<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PageSeo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeoController extends Controller
{
    public function show(Request $request, ?string $slug = null): JsonResponse
    {
        $slug = $slug ?: $request->query('slug', 'accueil');

        $page = PageSeo::query()
            ->where('slug', $slug)
            ->where('actif', true)
            ->first();

        if (! $page) {
            return response()->json([
                'message' => 'Page SEO introuvable.',
            ], 404);
        }

        return response()->json(['data' => $page]);
    }
}
