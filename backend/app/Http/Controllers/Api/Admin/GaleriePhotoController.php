<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\GaleriePhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class GaleriePhotoController extends Controller
{
    /** Nombre maximum de photos dans la galerie d'accueil. */
    private const MAX_PHOTOS = 10;

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => GaleriePhoto::query()
                ->orderBy('ordre')
                ->orderBy('id')
                ->get(),
            'max' => self::MAX_PHOTOS,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // Refuse l'upload si la galerie est deja pleine (limite produit : 10).
        if (GaleriePhoto::query()->count() >= self::MAX_PHOTOS) {
            return response()->json([
                'message' => 'La galerie est limitee a ' . self::MAX_PHOTOS . ' photos. Supprimez-en une avant d en ajouter.',
            ], 422);
        }

        $data = $request->validate([
            'image' => ['required', 'image', 'max:4096'],
            'titre' => ['nullable', 'string', 'max:255'],
            'sous_titre' => ['nullable', 'string', 'max:255'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $url = $this->processAndStore($request->file('image'), 'galerie', 1200, 1600);
        $nextOrdre = (int) GaleriePhoto::query()->max('ordre') + 1;

        $photo = GaleriePhoto::query()->create([
            'url' => $url,
            'titre' => $data['titre'] ?? null,
            'sous_titre' => $data['sous_titre'] ?? null,
            'ordre' => $nextOrdre,
            'actif' => $data['actif'] ?? true,
        ]);

        return response()->json([
            'message' => 'Photo ajoutee a la galerie.',
            'data' => $photo,
        ], 201);
    }

    public function update(Request $request, GaleriePhoto $galeriePhoto): JsonResponse
    {
        $data = $request->validate([
            'image' => ['nullable', 'image', 'max:4096'],
            'titre' => ['nullable', 'string', 'max:255'],
            'sous_titre' => ['nullable', 'string', 'max:255'],
            'ordre' => ['sometimes', 'integer', 'min:0'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        if ($request->hasFile('image')) {
            $this->deleteStoredImage($galeriePhoto->url);
            $data['image'] = null; // ne pas persister la cle "image"
            $galeriePhoto->url = $this->processAndStore($request->file('image'), 'galerie', 1200, 1600);
        }
        unset($data['image']);

        $galeriePhoto->fill($data)->save();

        return response()->json([
            'message' => 'Photo mise a jour.',
            'data' => $galeriePhoto->refresh(),
        ]);
    }

    public function destroy(GaleriePhoto $galeriePhoto): JsonResponse
    {
        // Supprime aussi le fichier du disque pour ne pas accumuler d orphelins.
        $this->deleteStoredImage($galeriePhoto->url);
        $galeriePhoto->delete();

        return response()->json([
            'message' => 'Photo supprimee.',
        ]);
    }

    /**
     * Redimensionne, convertit en WebP et stocke l'image (disque public).
     * Retourne l'URL publique Laravel (/storage/...).
     */
    private function processAndStore(UploadedFile $file, string $directory, int $maxWidth, int $maxHeight): string
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->read($file->getRealPath());
        $image->scaleDown(width: $maxWidth, height: $maxHeight);

        $filename = Str::uuid() . '.webp';
        $path = $directory . '/' . $filename;

        Storage::disk('public')->put($path, $image->toWebp(quality: 85));

        return Storage::url($path);
    }

    /**
     * Supprime un fichier du disque public a partir de son URL /storage/...
     */
    private function deleteStoredImage(?string $url): void
    {
        if (! $url) {
            return;
        }

        $path = ltrim(str_replace('/storage/', '', parse_url($url, PHP_URL_PATH) ?? $url), '/');

        if ($path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
