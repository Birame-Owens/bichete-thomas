<?php

namespace App\Services;

use App\Models\ImagesProduit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageOptimizationService
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = ImageManager::withDriver(new Driver());
    }

    public function storeProductImage(UploadedFile $file, string $basename = 'product'): array
    {
        $sourcePath = $file->getRealPath();
        $format = $this->preferredOutputFormat();

        if (!$sourcePath) {
            return $this->storeOriginalFallback($file);
        }

        try {
            $base = Str::slug(pathinfo($basename, PATHINFO_FILENAME)) ?: 'product';
            $name = $base . '-' . Str::random(10) . '.' . $format;

            $paths = [
                'original' => "produits/original/{$name}",
                'medium' => "produits/medium/{$name}",
                'thumbnail' => "produits/thumb/{$name}",
            ];

            $this->ensurePublicDirectories(['produits/original', 'produits/medium', 'produits/thumb']);

            $original = $this->manager->read($sourcePath)->scaleDown(width: 1600);
            $medium = $this->manager->read($sourcePath)->scaleDown(width: 800);
            $thumbnail = $this->manager->read($sourcePath)->coverDown(400, 400);

            $this->saveOptimized($original, $paths['original'], 82, $format);
            $this->saveOptimized($medium, $paths['medium'], 78, $format);
            $this->saveOptimized($thumbnail, $paths['thumbnail'], 74, $format);

            return [
                'chemin_original' => $paths['original'],
                'chemin_moyen' => $paths['medium'],
                'chemin_miniature' => $paths['thumbnail'],
                'format' => $format,
                'taille_octets' => Storage::disk('public')->size($paths['original']),
                'largeur' => $original->width(),
                'hauteur' => $original->height(),
            ];
        } catch (\Throwable $e) {
            Log::warning('Image optimization failed, storing original upload', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            return $this->storeOriginalFallback($file);
        }
    }

    public function optimizeExistingImages(): array
    {
        $result = ['total' => ImagesProduit::count(), 'success' => 0, 'errors' => 0, 'error_details' => []];

        ImagesProduit::query()->orderBy('id')->chunkById(50, function ($images) use (&$result) {
            foreach ($images as $image) {
                try {
                    $this->optimizeImageRecord($image);
                    $result['success']++;
                } catch (\Throwable $e) {
                    $result['errors']++;
                    if (count($result['error_details']) < 10) {
                        $result['error_details'][] = "Image {$image->id}: {$e->getMessage()}";
                    }
                    Log::warning('Existing image optimization failed', [
                        'image_id' => $image->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        return $result;
    }

    public function optimizeImageById(int|string $imageId): ImagesProduit
    {
        $image = ImagesProduit::findOrFail($imageId);

        return $this->optimizeImageRecord($image);
    }

    public function deleteProductImageFamily(?string $path): void
    {
        if (!$path || $path === 'produits/default-product.jpg') {
            return;
        }

        $basename = basename($path);
        $candidates = array_unique([
            $path,
            "produits/original/{$basename}",
            "produits/medium/{$basename}",
            "produits/thumb/{$basename}",
        ]);

        foreach ($candidates as $candidate) {
            if ($candidate && Storage::disk('public')->exists($candidate)) {
                Storage::disk('public')->delete($candidate);
            }
        }
    }

    private function optimizeImageRecord(ImagesProduit $image): ImagesProduit
    {
        if (!$image->chemin_original || !Storage::disk('public')->exists($image->chemin_original)) {
            throw new \RuntimeException('Original image not found.');
        }

        if ($image->chemin_moyen && $image->chemin_miniature
            && Storage::disk('public')->exists($image->chemin_moyen)
            && Storage::disk('public')->exists($image->chemin_miniature)) {
            return $image;
        }

        $sourcePath = Storage::disk('public')->path($image->chemin_original);
        $base = Str::slug(pathinfo($image->nom_fichier ?: basename($image->chemin_original), PATHINFO_FILENAME)) ?: 'product';
        $format = $this->preferredOutputFormat();
        $name = $base . '-' . $image->id . '.' . $format;

        $paths = [
            'medium' => "produits/medium/{$name}",
            'thumbnail' => "produits/thumb/{$name}",
        ];

        $this->ensurePublicDirectories(['produits/medium', 'produits/thumb']);

        $medium = $this->manager->read($sourcePath)->scaleDown(width: 800);
        $thumbnail = $this->manager->read($sourcePath)->coverDown(400, 400);

        $this->saveOptimized($medium, $paths['medium'], 78, $format);
        $this->saveOptimized($thumbnail, $paths['thumbnail'], 74, $format);

        $image->update([
            'chemin_moyen' => $paths['medium'],
            'chemin_miniature' => $paths['thumbnail'],
            'format' => $image->format ?: $format,
            'taille_octets' => Storage::disk('public')->size($image->chemin_original),
            'largeur' => $image->largeur ?: $medium->width(),
            'hauteur' => $image->hauteur ?: $medium->height(),
        ]);

        return $image->refresh();
    }

    private function saveOptimized($image, string $path, int $quality, string $format): void
    {
        $encoded = $format === 'webp'
            ? $image->toWebp(quality: $quality)
            : $image->toJpeg(quality: $quality);

        $encoded->save(Storage::disk('public')->path($path));
    }

    private function preferredOutputFormat(): string
    {
        return function_exists('imagewebp') ? 'webp' : 'jpg';
    }

    private function ensurePublicDirectories(array $directories): void
    {
        foreach ($directories as $directory) {
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }
        }
    }

    private function storeOriginalFallback(UploadedFile $file): array
    {
        $path = $file->store('produits', 'public');
        $dimensions = @getimagesize(Storage::disk('public')->path($path)) ?: [null, null];

        return [
            'chemin_original' => $path,
            'chemin_moyen' => null,
            'chemin_miniature' => null,
            'format' => $file->getClientOriginalExtension(),
            'taille_octets' => $file->getSize(),
            'largeur' => $dimensions[0] ?? null,
            'hauteur' => $dimensions[1] ?? null,
        ];
    }
}
