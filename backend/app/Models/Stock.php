<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Stock
 * 
 * @property int $id
 * @property int|null $produit_id
 * @property int|null $tissu_id
 * @property string $type_mouvement
 * @property float $quantite
 * @property string $unite
 * @property float $quantite_avant
 * @property float $quantite_apres
 * @property float|null $prix_unitaire
 * @property float|null $valeur_totale
 * @property string $devise
 * @property string $reference_mouvement
 * @property int|null $commande_id
 * @property int|null $production_id
 * @property string|null $numero_facture_fournisseur
 * @property string|null $bon_livraison
 * @property string|null $fournisseur_nom
 * @property string|null $fournisseur_telephone
 * @property string|null $fournisseur_adresse
 * @property string|null $emplacement_stockage
 * @property string|null $lot_numero
 * @property Carbon|null $date_peremption
 * @property Carbon|null $date_achat
 * @property string $motif
 * @property string|null $description_detaillee
 * @property string|null $notes_admin
 * @property int|null $user_id
 * @property string $effectue_par_nom
 * @property string $methode_saisie
 * @property bool $mouvement_valide
 * @property bool $necessite_validation
 * @property int|null $valide_par_user_id
 * @property Carbon|null $date_validation
 * @property bool $est_reservation
 * @property Carbon|null $date_expiration_reservation
 * @property int|null $panier_id
 * @property string|null $statut_reservation
 * @property string $etat_produit
 * @property string|null $notes_qualite
 * @property string|null $defauts_constates
 * @property bool $controle_qualite_ok
 * @property string|null $operateur_controle
 * @property string|null $rapport_controle
 * @property float|null $largeur_metres
 * @property string|null $coloris
 * @property string|null $pattern
 * @property string|null $composition
 * @property float $cout_stockage
 * @property float $cout_transport
 * @property float $autres_couts
 * @property float|null $cout_total_unitaire
 * @property bool $genere_alerte
 * @property string|null $type_alerte
 * @property bool $alerte_envoyee
 * @property string|null $donnees_integration
 * @property bool $synchronise_comptabilite
 * @property Carbon|null $date_synchronisation
 * @property string|null $photos_mouvement
 * @property string|null $documents_joints
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Produit|null $produit
 * @property Tissu|null $tissu
 * @property Commande|null $commande
 * @property Production|null $production
 * @property User|null $user
 * @property Panier|null $panier
 *
 * @package App\Models
 */
class Stock extends Model
{
	use SoftDeletes;
	protected $table = 'stocks';

	protected $casts = [
		'produit_id' => 'int',
		'tissu_id' => 'int',
		'quantite' => 'float',
		'quantite_avant' => 'float',
		'quantite_apres' => 'float',
		'prix_unitaire' => 'float',
		'valeur_totale' => 'float',
		'commande_id' => 'int',
		'production_id' => 'int',
		'date_peremption' => 'datetime',
		'date_achat' => 'datetime',
		'user_id' => 'int',
		'mouvement_valide' => 'bool',
		'necessite_validation' => 'bool',
		'valide_par_user_id' => 'int',
		'date_validation' => 'datetime',
		'est_reservation' => 'bool',
		'date_expiration_reservation' => 'datetime',
		'panier_id' => 'int',
		'controle_qualite_ok' => 'bool',
		'largeur_metres' => 'float',
		'cout_stockage' => 'float',
		'cout_transport' => 'float',
		'autres_couts' => 'float',
		'cout_total_unitaire' => 'float',
		'genere_alerte' => 'bool',
		'alerte_envoyee' => 'bool',
		'synchronise_comptabilite' => 'bool',
		'date_synchronisation' => 'datetime'
	];

	protected $fillable = [
		'produit_id',
		'tissu_id',
		'type_mouvement',
		'quantite',
		'unite',
		'quantite_avant',
		'quantite_apres',
		'prix_unitaire',
		'valeur_totale',
		'devise',
		'reference_mouvement',
		'commande_id',
		'production_id',
		'numero_facture_fournisseur',
		'bon_livraison',
		'fournisseur_nom',
		'fournisseur_telephone',
		'fournisseur_adresse',
		'emplacement_stockage',
		'lot_numero',
		'date_peremption',
		'date_achat',
		'motif',
		'description_detaillee',
		'notes_admin',
		'user_id',
		'effectue_par_nom',
		'methode_saisie',
		'mouvement_valide',
		'necessite_validation',
		'valide_par_user_id',
		'date_validation',
		'est_reservation',
		'date_expiration_reservation',
		'panier_id',
		'statut_reservation',
		'etat_produit',
		'notes_qualite',
		'defauts_constates',
		'controle_qualite_ok',
		'operateur_controle',
		'rapport_controle',
		'largeur_metres',
		'coloris',
		'pattern',
		'composition',
		'cout_stockage',
		'cout_transport',
		'autres_couts',
		'cout_total_unitaire',
		'genere_alerte',
		'type_alerte',
		'alerte_envoyee',
		'donnees_integration',
		'synchronise_comptabilite',
		'date_synchronisation',
		'photos_mouvement',
		'documents_joints'
	];

	public function produit()
	{
		return $this->belongsTo(Produit::class);
	}

	public function tissu()
	{
		return $this->belongsTo(Tissu::class);
	}

	public function commande()
	{
		return $this->belongsTo(Commande::class);
	}

	public function production()
	{
		return $this->belongsTo(Production::class);
	}

	public function user()
	{
		return $this->belongsTo(User::class, 'valide_par_user_id');
	}

	public function panier()
	{
		return $this->belongsTo(Panier::class);
	}
}
