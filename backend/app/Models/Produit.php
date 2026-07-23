<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Produit
 * 
 * @property int $id
 * @property string $nom
 * @property string $slug
 * @property string $description
 * @property string|null $description_courte
 * @property string $image_principale
 * @property float $prix
 * @property float|null $prix_promo
 * @property Carbon|null $debut_promo
 * @property Carbon|null $fin_promo
 * @property int $categorie_id
 * @property int $stock_disponible
 * @property int $seuil_alerte
 * @property bool $gestion_stock
 * @property bool $fait_sur_mesure
 * @property int|null $delai_production_jours
 * @property float|null $cout_production
 * @property string|null $tailles_disponibles
 * @property string|null $couleurs_disponibles
 * @property string|null $materiaux_necessaires
 * @property bool $est_visible
 * @property bool $est_populaire
 * @property bool $est_nouveaute
 * @property int $ordre_affichage
 * @property int $nombre_vues
 * @property int $nombre_ventes
 * @property float $note_moyenne
 * @property int $nombre_avis
 * @property string|null $meta_titre
 * @property string|null $meta_description
 * @property string|null $tags
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Category $category
 * @property Collection|ImagesProduit[] $images_produits
 * @property Collection|ArticlesCommande[] $articles_commandes
 * @property Collection|Stock[] $stocks
 * @property Collection|ArticlesPanier[] $articles_paniers
 * @property Collection|AvisClient[] $avis_clients
 *
 * @package App\Models
 */
class Produit extends Model
{
	use SoftDeletes;
	protected $table = 'produits';

	protected $casts = [
		'prix' => 'float',
		'prix_promo' => 'float',
		'debut_promo' => 'datetime',
		'fin_promo' => 'datetime',
		'categorie_id' => 'int',
		'stock_disponible' => 'int',
		'seuil_alerte' => 'int',
		'gestion_stock' => 'bool',
		'fait_sur_mesure' => 'bool',
		'delai_production_jours' => 'int',
		'cout_production' => 'float',
		'est_visible' => 'bool',
		'est_populaire' => 'bool',
		'est_nouveaute' => 'bool',
		'ordre_affichage' => 'int',
		'nombre_vues' => 'int',
		'nombre_ventes' => 'int',
		'note_moyenne' => 'float',
		'nombre_avis' => 'int',
		'tags' => 'array',
	];

	protected $fillable = [
		'nom',
		'slug',
		'description',
		'description_courte',
		'image_principale',
		'prix',
		'prix_promo',
		'debut_promo',
		'fin_promo',
		'categorie_id',
		'stock_disponible',
		'seuil_alerte',
		'gestion_stock',
		'fait_sur_mesure',
		'delai_production_jours',
		'cout_production',
		'tailles_disponibles',
		'couleurs_disponibles',
		'couleur_tailles',
		'couleur_tailles_stock',
		'couleur_tailles_seuil',
		'materiaux_necessaires',
		'est_visible',
		'est_populaire',
		'est_nouveaute',
		'ordre_affichage',
		'nombre_vues',
		'nombre_ventes',
		'note_moyenne',
		'nombre_avis',
		'meta_titre',
		'meta_description',
		'tags',
		'type_variante',
	];

	protected $appends = ['image', 'image_url'];

	public function getImageAttribute()
	{
		// Si la relation est déjà chargée, utiliser la collection
		if ($this->relationLoaded('images_produits')) {
			$premiereImage = $this->images_produits
				->where('est_visible', true)
				->sortBy('ordre_affichage')
				->first();
			
			if ($premiereImage) {
				return $premiereImage->url;
			}
		} else {
			// Sinon faire une requête
			$premiereImage = $this->images_produits()
				->where('est_visible', true)
				->orderBy('ordre_affichage')
				->first();
			
			if ($premiereImage) {
				return $premiereImage->url;
			}
		}
		
		// Fallback sur image_principale
		if ($this->image_principale) {
			if (str_starts_with($this->image_principale, 'http')) {
				return $this->image_principale;
			}

			$path = ltrim(preg_replace('#^/?storage/#', '', $this->image_principale), '/');
			return asset('storage/' . $path);
		}
		
		// Image par défaut si aucune image disponible
		return asset('images/placeholder-product.jpg');
	}

	public function getImageUrlAttribute()
	{
		return $this->image;
	}

	public function category()
	{
		return $this->belongsTo(Category::class, 'categorie_id');
	}

	public function images_produits()
	{
		return $this->hasMany(ImagesProduit::class);
	}

	public function articles_commandes()
	{
		return $this->hasMany(ArticlesCommande::class);
	}

	public function stocks()
	{
		return $this->hasMany(Stock::class);
	}

	public function articles_paniers()
	{
		return $this->hasMany(ArticlesPanier::class);
	}

	public function avis_clients()
	{
		return $this->hasMany(AvisClient::class);
	}
}
