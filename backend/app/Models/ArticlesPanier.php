<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ArticlesPanier
 * 
 * @property int $id
 * @property int $panier_id
 * @property int $produit_id
 * @property int $quantite
 * @property float $prix_unitaire
 * @property float $prix_total
 * @property string|null $taille_choisie
 * @property string|null $couleur_choisie
 * @property string|null $options_choisies
 * @property string|null $personnalisations
 * @property string|null $mesures_personnalisees
 * @property bool $est_reserve
 * @property Carbon|null $date_reservation
 * @property Carbon|null $date_expiration_reservation
 * @property Carbon $date_ajout
 * @property Carbon|null $derniere_modification
 * @property int $nombre_modifications
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Panier $panier
 * @property Produit $produit
 *
 * @package App\Models
 */
class ArticlesPanier extends Model
{
	protected $table = 'articles_panier';

	protected $casts = [
		'panier_id' => 'int',
		'produit_id' => 'int',
		'quantite' => 'int',
		'prix_unitaire' => 'float',
		'prix_total' => 'float',
		'est_reserve' => 'bool',
		'date_reservation' => 'datetime',
		'date_expiration_reservation' => 'datetime',
		'date_ajout' => 'datetime',
		'derniere_modification' => 'datetime',
		'nombre_modifications' => 'int'
	];

	protected $fillable = [
		'panier_id',
		'produit_id',
		'quantite',
		'prix_unitaire',
		'prix_total',
		'taille_choisie',
		'couleur_choisie',
		'options_choisies',
		'personnalisations',
		'mesures_personnalisees',
		'est_reserve',
		'date_reservation',
		'date_expiration_reservation',
		'date_ajout',
		'derniere_modification',
		'nombre_modifications'
	];

	public function panier()
	{
		return $this->belongsTo(Panier::class);
	}

	public function produit()
	{
		return $this->belongsTo(Produit::class);
	}
}
