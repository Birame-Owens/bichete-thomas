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
 * Class Facture
 * 
 * @property int $id
 * @property int $commande_id
 * @property int $client_id
 * @property string $numero_facture
 * @property string $numero_commande_ref
 * @property string $type_facture
 * @property string $client_nom
 * @property string $client_prenom
 * @property string $client_telephone
 * @property string|null $client_email
 * @property string|null $client_adresse_complete
 * @property string $client_ville
 * @property string $boutique_nom
 * @property string $boutique_slogan
 * @property string $boutique_adresse
 * @property string $boutique_telephone
 * @property string $boutique_email
 * @property string $boutique_site_web
 * @property string|null $boutique_ninea
 * @property string|null $boutique_rc
 * @property float $sous_total_ht
 * @property float $montant_remise
 * @property float $pourcentage_remise
 * @property float $frais_livraison
 * @property float $montant_tva
 * @property float $taux_tva
 * @property float $autres_frais
 * @property float $montant_total_ht
 * @property float $montant_total_ttc
 * @property string $articles_facture
 * @property Carbon $date_emission
 * @property Carbon|null $date_echeance
 * @property Carbon|null $date_livraison
 * @property Carbon|null $date_paiement_complet
 * @property Carbon|null $date_envoi_client
 * @property string $statut
 * @property float $montant_paye
 * @property float $montant_restant_du
 * @property string|null $historique_paiements
 * @property bool $envoyee_email
 * @property bool $envoyee_whatsapp
 * @property bool $envoyee_sms
 * @property bool $remise_en_main_propre
 * @property Carbon|null $derniere_relance
 * @property int $nombre_relances
 * @property string|null $chemin_pdf
 * @property string|null $nom_fichier_pdf
 * @property int|null $taille_fichier_octets
 * @property string|null $hash_contenu
 * @property bool $pdf_genere
 * @property string $template_utilise
 * @property string $langue_facture
 * @property string|null $options_affichage
 * @property string|null $message_client
 * @property string|null $conditions_paiement
 * @property string|null $mentions_legales
 * @property string|null $notes_internes
 * @property string|null $instructions_paiement
 * @property string|null $code_promo_utilise
 * @property string|null $promotions_appliquees
 * @property bool $est_avoir
 * @property int|null $facture_origine_id
 * @property float $montant_avoir_total
 * @property string|null $motif_avoir
 * @property Carbon|null $date_avoir
 * @property string|null $adresse_livraison_complete
 * @property string|null $transporteur
 * @property string|null $numero_suivi
 * @property float|null $poids_total_grammes
 * @property int $nombre_consultations
 * @property Carbon|null $derniere_consultation
 * @property string|null $consulte_depuis_ip
 * @property bool $validee_par_admin
 * @property string|null $validee_par_nom
 * @property Carbon|null $date_validation
 * @property string|null $commentaires_validation
 * @property string|null $numero_comptable
 * @property bool $exportee_comptabilite
 * @property Carbon|null $date_export_compta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Commande $commande
 * @property Client $client
 * @property Facture|null $facture
 * @property Collection|Facture[] $factures
 *
 * @package App\Models
 */
class Facture extends Model
{
	use SoftDeletes;
	protected $table = 'factures';

	protected $casts = [
		'commande_id' => 'int',
		'client_id' => 'int',
		'sous_total_ht' => 'float',
		'montant_remise' => 'float',
		'pourcentage_remise' => 'float',
		'frais_livraison' => 'float',
		'montant_tva' => 'float',
		'taux_tva' => 'float',
		'autres_frais' => 'float',
		'montant_total_ht' => 'float',
		'montant_total_ttc' => 'float',
		'date_emission' => 'datetime',
		'date_echeance' => 'datetime',
		'date_livraison' => 'datetime',
		'date_paiement_complet' => 'datetime',
		'date_envoi_client' => 'datetime',
		'montant_paye' => 'float',
		'montant_restant_du' => 'float',
		'envoyee_email' => 'bool',
		'envoyee_whatsapp' => 'bool',
		'envoyee_sms' => 'bool',
		'remise_en_main_propre' => 'bool',
		'derniere_relance' => 'datetime',
		'nombre_relances' => 'int',
		'taille_fichier_octets' => 'int',
		'pdf_genere' => 'bool',
		'est_avoir' => 'bool',
		'facture_origine_id' => 'int',
		'montant_avoir_total' => 'float',
		'date_avoir' => 'datetime',
		'poids_total_grammes' => 'float',
		'nombre_consultations' => 'int',
		'derniere_consultation' => 'datetime',
		'validee_par_admin' => 'bool',
		'date_validation' => 'datetime',
		'exportee_comptabilite' => 'bool',
		'date_export_compta' => 'datetime'
	];

	protected $fillable = [
		'commande_id',
		'client_id',
		'numero_facture',
		'numero_commande_ref',
		'type_facture',
		'client_nom',
		'client_prenom',
		'client_telephone',
		'client_email',
		'client_adresse_complete',
		'client_ville',
		'boutique_nom',
		'boutique_slogan',
		'boutique_adresse',
		'boutique_telephone',
		'boutique_email',
		'boutique_site_web',
		'boutique_ninea',
		'boutique_rc',
		'sous_total_ht',
		'montant_remise',
		'pourcentage_remise',
		'frais_livraison',
		'montant_tva',
		'taux_tva',
		'autres_frais',
		'montant_total_ht',
		'montant_total_ttc',
		'articles_facture',
		'date_emission',
		'date_echeance',
		'date_livraison',
		'date_paiement_complet',
		'date_envoi_client',
		'statut',
		'montant_paye',
		'montant_restant_du',
		'historique_paiements',
		'envoyee_email',
		'envoyee_whatsapp',
		'envoyee_sms',
		'remise_en_main_propre',
		'derniere_relance',
		'nombre_relances',
		'chemin_pdf',
		'nom_fichier_pdf',
		'taille_fichier_octets',
		'hash_contenu',
		'pdf_genere',
		'template_utilise',
		'langue_facture',
		'options_affichage',
		'message_client',
		'conditions_paiement',
		'mentions_legales',
		'notes_internes',
		'instructions_paiement',
		'code_promo_utilise',
		'promotions_appliquees',
		'est_avoir',
		'facture_origine_id',
		'montant_avoir_total',
		'motif_avoir',
		'date_avoir',
		'adresse_livraison_complete',
		'transporteur',
		'numero_suivi',
		'poids_total_grammes',
		'nombre_consultations',
		'derniere_consultation',
		'consulte_depuis_ip',
		'validee_par_admin',
		'validee_par_nom',
		'date_validation',
		'commentaires_validation',
		'numero_comptable',
		'exportee_comptabilite',
		'date_export_compta'
	];

	public function commande()
	{
		return $this->belongsTo(Commande::class);
	}

	public function client()
	{
		return $this->belongsTo(Client::class);
	}

	public function facture()
	{
		return $this->belongsTo(Facture::class, 'facture_origine_id');
	}

	public function factures()
	{
		return $this->hasMany(Facture::class, 'facture_origine_id');
	}
}
