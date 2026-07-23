<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Class ImagesProduit
 * 
 * @property int $id
 * @property int $produit_id
 * @property string $nom_fichier
 * @property string $chemin_original
 * @property string|null $chemin_miniature
 * @property string|null $chemin_moyen
 * @property string|null $alt_text
 * @property string|null $titre
 * @property string|null $description
 * @property int $ordre_affichage
 * @property bool $est_principale
 * @property bool $est_visible
 * @property string|null $format
 * @property int|null $taille_octets
 * @property int|null $largeur
 * @property int|null $hauteur
 * @property string|null $couleur_dominante
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Produit $produit
 *
 * @package App\Models
 */
class ImagesProduit extends Model
{
	protected $table = 'images_produits';

	protected $casts = [
		'produit_id' => 'int',
		'ordre_affichage' => 'int',
		'est_principale' => 'bool',
		'est_visible' => 'bool',
		'taille_octets' => 'int',
		'largeur' => 'int',
		'hauteur' => 'int'
	];

	protected $fillable = [
		'produit_id',
		'nom_fichier',
		'chemin_original',
		'chemin_miniature',
		'chemin_moyen',
		'alt_text',
		'titre',
		'description',
		'ordre_affichage',
		'est_principale',
		'est_visible',
		'format',
		'taille_octets',
		'largeur',
		'hauteur',
		'couleur_dominante',
		'couleur_associee',
	];

	protected $appends = ['url'];

	public function getUrlAttribute()
	{
		$chemin = $this->firstExistingPath([
			$this->chemin_moyen,
			$this->chemin_original,
			$this->chemin_miniature,
		]);
		
		if (!$chemin) {
			return null;
		}
		
		// Si c'est déjà une URL complète
		if (str_starts_with($chemin, 'http')) {
			return $chemin;
		}
		
		// Sinon, construire le chemin avec storage
		return asset('storage/' . $chemin);
	}

	private function firstExistingPath(array $paths): ?string
	{
		foreach ($paths as $path) {
			if (!$path) {
				continue;
			}

			if (str_starts_with($path, 'http')) {
				return $path;
			}

			$normalized = ltrim(preg_replace('#^/?storage/#', '', $path), '/');
			if (Storage::disk('public')->exists($normalized)) {
				return $normalized;
			}
		}

		return null;
	}

	public function produit()
	{
		return $this->belongsTo(Produit::class);
	}
}
