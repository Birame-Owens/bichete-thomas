<?php

namespace Database\Seeders;

use App\Models\ImagesProduit;
use App\Models\Produit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;

class ImagesProduitSeeder extends Seeder
{
    /**
     * Ajouter les images aux produits
     */
    public function run(): void
    {
        // Créer le répertoire de stockage s'il n'existe pas
        $directory = storage_path('app/public/produits');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $produits = Produit::all();

        foreach ($produits as $produit) {
            // Créer 3 images par produit avec des couleurs différentes
            $images = [
                [
                    'nom' => $produit->nom . ' - Vue 1',
                    'couleur' => '#8B4513', // Marron
                    'ordre' => 1,
                    'principale' => true,
                ],
                [
                    'nom' => $produit->nom . ' - Vue 2',
                    'couleur' => '#FF69B4', // Rose
                    'ordre' => 2,
                    'principale' => false,
                ],
                [
                    'nom' => $produit->nom . ' - Vue 3',
                    'couleur' => '#FFD700', // Or
                    'ordre' => 3,
                    'principale' => false,
                ],
            ];

            foreach ($images as $imageData) {
                try {
                    // Générer une image colorée par produit avec texte
                    $imagePath = $this->genererImageProduit(
                        $directory,
                        $produit->id,
                        $imageData['ordre'],
                        $imageData['couleur'],
                        $imageData['nom']
                    );

                    if ($imagePath) {
                        // Créer l'enregistrement ImagesProduit
                        ImagesProduit::create([
                            'produit_id' => $produit->id,
                            'nom_fichier' => basename($imagePath),
                            'chemin_original' => 'produits/' . basename($imagePath),
                            'chemin_miniature' => 'produits/thumb_' . basename($imagePath),
                            'chemin_moyen' => 'produits/medium_' . basename($imagePath),
                            'alt_text' => $imageData['nom'],
                            'ordre_affichage' => $imageData['ordre'],
                            'est_principale' => $imageData['principale'],
                        ]);

                        $this->command->info("✓ Image créée: {$produit->nom} - Vue {$imageData['ordre']}");
                    }
                } catch (\Exception $e) {
                    $this->command->error("✗ Erreur pour {$produit->nom}: " . $e->getMessage());
                }
            }
        }

        $this->command->info('✅ Images des produits créées avec succès!');
        $this->command->info('📊 Total images: ' . ImagesProduit::count());
    }

    /**
     * Générer une image colorée pour un produit
     */
    private function genererImageProduit($directory, $produitId, $ordre, $couleur, $texte)
    {
        try {
            // Créer une image 500x500 avec la couleur donnée
            $image = Image::canvas(500, 500, $couleur);

            // Ajouter le texte du produit
            $image->text($texte, 250, 220, function ($font) {
                $font->size(24);
                $font->color('#FFFFFF');
                $font->align('center');
                $font->valign('center');
            });

            // Ajouter le numéro de vue
            $image->text("Vue $ordre", 250, 280, function ($font) {
                $font->size(16);
                $font->color('#FFFFFF');
                $font->align('center');
                $font->valign('center');
            });

            // Ajouter la date
            $image->text(now()->format('d/m/Y'), 250, 450, function ($font) {
                $font->size(12);
                $font->color('rgba(255,255,255,0.7)');
                $font->align('center');
            });

            // Sauvegarder l'image
            $filename = "product_{$produitId}_view_{$ordre}.jpg";
            $path = $directory . '/' . $filename;
            $image->save($path);

            return $path;
        } catch (\Exception $e) {
            return null;
        }
    }
}
