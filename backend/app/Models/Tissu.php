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
 * Class Tissu
 * 
 * @property int $id
 * @property string $nom
 * @property string $reference
 * @property string|null $description
 * @property string $couleur_principale
 * @property string|null $couleurs_secondaires
 * @property string $type_tissu
 * @property float $largeur_metres
 * @property string|null $motif
 * @property string $qualite
 * @property float $quantite_metres
 * @property float $prix_achat_metre
 * @property float $prix_vente_metre
 * @property float $marge_beneficiaire
 * @property int $seuil_alerte_metres
 * @property int $stock_minimum
 * @property int $stock_maximum
 * @property string|null $fournisseur
 * @property string|null $telephone_fournisseur
 * @property string|null $adresse_fournisseur
 * @property int $delai_livraison_jours
 * @property float|null $quantite_commande_optimale
 * @property int $nombre_utilisations
 * @property float $metres_vendus_total
 * @property bool $est_populaire
 * @property bool $est_nouveaute
 * @property string|null $image
 * @property bool $est_disponible
 * @property bool $est_visible_client
 * @property int $ordre_affichage
 * @property string|null $saisons_recommandees
 * @property string|null $occasions_recommandees
 * @property string|null $notes_admin
 * @property string $evaluation_qualite
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Collection|Stock[] $stocks
 *
 * @package App\Models
 */
class Tissu extends Model
{
	use SoftDeletes;
	protected $table = 'tissus';

	protected $casts = [
		'largeur_metres' => 'float',
		'quantite_metres' => 'float',
		'prix_achat_metre' => 'float',
		'prix_vente_metre' => 'float',
		'marge_beneficiaire' => 'float',
		'seuil_alerte_metres' => 'int',
		'stock_minimum' => 'int',
		'stock_maximum' => 'int',
		'delai_livraison_jours' => 'int',
		'quantite_commande_optimale' => 'float',
		'nombre_utilisations' => 'int',
		'metres_vendus_total' => 'float',
		'est_populaire' => 'bool',
		'est_nouveaute' => 'bool',
		'est_disponible' => 'bool',
		'est_visible_client' => 'bool',
		'ordre_affichage' => 'int'
	];

	protected $fillable = [
		'nom',
		'reference',
		'description',
		'couleur_principale',
		'couleurs_secondaires',
		'type_tissu',
		'largeur_metres',
		'motif',
		'qualite',
		'quantite_metres',
		'prix_achat_metre',
		'prix_vente_metre',
		'marge_beneficiaire',
		'seuil_alerte_metres',
		'stock_minimum',
		'stock_maximum',
		'fournisseur',
		'telephone_fournisseur',
		'adresse_fournisseur',
		'delai_livraison_jours',
		'quantite_commande_optimale',
		'nombre_utilisations',
		'metres_vendus_total',
		'est_populaire',
		'est_nouveaute',
		'image',
		'est_disponible',
		'est_visible_client',
		'ordre_affichage',
		'saisons_recommandees',
		'occasions_recommandees',
		'notes_admin',
		'evaluation_qualite'
	];

	public function stocks()
	{
		return $this->hasMany(Stock::class);
	}
}
