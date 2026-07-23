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
 * Class Category
 * 
 * @property int $id
 * @property string $nom
 * @property string $slug
 * @property string|null $description
 * @property string|null $image
 * @property int|null $parent_id
 * @property int $ordre_affichage
 * @property bool $est_active
 * @property bool $est_populaire
 * @property string|null $couleur_theme
 * @property string|null $meta_donnees
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Category|null $category
 * @property Collection|Category[] $categories
 * @property Collection|Produit[] $produits
 *
 * @package App\Models
 */
class Category extends Model
{
	use SoftDeletes;
	protected $table = 'categories';

	protected $casts = [
		'parent_id' => 'int',
		'ordre_affichage' => 'int',
		'est_active' => 'bool',
		'est_populaire' => 'bool'
	];

	protected $fillable = [
		'nom',
		'slug',
		'description',
		'image',
		'parent_id',
		'ordre_affichage',
		'est_active',
		'est_populaire',
		'couleur_theme',
		'meta_donnees'
	];

	public function category()
	{
		return $this->belongsTo(Category::class, 'parent_id');
	}

	public function categories()
	{
		return $this->hasMany(Category::class, 'parent_id');
	}

	public function produits()
	{
		return $this->hasMany(Produit::class, 'categorie_id');
	}
}
