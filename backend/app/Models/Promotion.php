<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Promotion
 * 
 * @property int $id
 * @property string $nom
 * @property string|null $code
 * @property string $description
 * @property string|null $image
 * @property string $type_promotion
 * @property float $valeur
 * @property float|null $montant_minimum
 * @property float|null $reduction_maximum
 * @property Carbon $date_debut
 * @property Carbon $date_fin
 * @property bool $est_active
 * @property int|null $utilisation_maximum
 * @property int $utilisation_par_client
 * @property int $nombre_utilisations
 * @property string $cible_client
 * @property string|null $categories_eligibles
 * @property string|null $produits_eligibles
 * @property bool $cumul_avec_autres
 * @property bool $premiere_commande_seulement
 * @property string|null $jours_semaine_valides
 * @property bool $afficher_site
 * @property bool $envoyer_whatsapp
 * @property bool $envoyer_email
 * @property string|null $couleur_affichage
 * @property float $chiffre_affaires_genere
 * @property int $nombre_commandes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 *
 * @package App\Models
 */
class Promotion extends Model
{
	use SoftDeletes;
	protected $table = 'promotions';

	protected $casts = [
		'valeur' => 'float',
		'montant_minimum' => 'float',
		'reduction_maximum' => 'float',
		'date_debut' => 'datetime',
		'date_fin' => 'datetime',
		'est_active' => 'bool',
		'utilisation_maximum' => 'int',
		'utilisation_par_client' => 'int',
		'nombre_utilisations' => 'int',
		'cumul_avec_autres' => 'bool',
		'premiere_commande_seulement' => 'bool',
		'afficher_site' => 'bool',
		'envoyer_whatsapp' => 'bool',
		'envoyer_email' => 'bool',
		'chiffre_affaires_genere' => 'float',
		'nombre_commandes' => 'int'
	];

	protected $fillable = [
		'nom',
		'code',
		'description',
		'image',
		'type_promotion',
		'valeur',
		'montant_minimum',
		'reduction_maximum',
		'date_debut',
		'date_fin',
		'est_active',
		'utilisation_maximum',
		'utilisation_par_client',
		'nombre_utilisations',
		'cible_client',
		'categories_eligibles',
		'produits_eligibles',
		'cumul_avec_autres',
		'premiere_commande_seulement',
		'jours_semaine_valides',
		'afficher_site',
		'envoyer_whatsapp',
		'envoyer_email',
		'couleur_affichage',
		'chiffre_affaires_genere',
		'nombre_commandes'
	];
}
