<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParametreSysteme;
use App\Support\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Gestion de l'image de garde (hero) de la page d'accueil.
 *
 * Stockee comme parametre systeme "image_accueil" (URL /storage/...), elle est
 * exposee dans /client/catalogue. Si vide, le front retombe sur le visuel par
 * defaut. L'admin peut donc changer l'image d'accueil a tout moment.
 */
class ImageAccueilController extends Controller
{
    private const KEY = 'image_accueil';

    public function show(): JsonResponse
    {
        return response()->json(['url' => SystemSettings::get(self::KEY)]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:8192'],
        ]);

        $this->deleteStoredImage(SystemSettings::get(self::KEY));
        $url = $this->processAndStore($request->file('image'), 'accueil', 1920, 1280);
        $this->persist($url);

        return response()->json([
            'message' => 'Image de garde mise a jour.',
            'url' => $url,
        ]);
    }

    public function destroy(): JsonResponse
    {
        $this->deleteStoredImage(SystemSettings::get(self::KEY));
        $this->persist(null);

        return response()->json([
            'message' => 'Image de garde reinitialisee.',
            'url' => null,
        ]);
    }

    private function persist(?string $url): void
    {
        ParametreSysteme::query()->updateOrCreate(
            ['cle' => self::KEY],
            [
                'valeur' => ['value' => $url],
                'type' => 'string',
                'description' => "Image de garde (hero) de la page d'accueil.",
                'modifiable' => false,
            ],
        );

        // La valeur est exposee dans le catalogue public (cache 5 min) : on
        // invalide pour que le changement soit visible immediatement.
        Cache::increment('catalogue:cache:version');
    }

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
