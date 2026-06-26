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
 * Class Commande
 * 
 * @property int $id
 * @property string $numero_commande
 * @property int $client_id
 * @property float $sous_total
 * @property float $frais_livraison
 * @property float $remise
 * @property float $montant_tva
 * @property float $montant_total
 * @property string $statut
 * @property Carbon|null $date_confirmation
 * @property Carbon|null $date_debut_production
 * @property Carbon|null $date_fin_production
 * @property Carbon|null $date_livraison_prevue
 * @property Carbon|null $date_livraison_reelle
 * @property string $adresse_livraison
 * @property string $telephone_livraison
 * @property string $nom_destinataire
 * @property string|null $instructions_livraison
 * @property string $mode_livraison
 * @property string|null $notes_client
 * @property string|null $notes_admin
 * @property string|null $notes_production
 * @property string $source
 * @property string|null $code_promo
 * @property string $priorite
 * @property bool $est_cadeau
 * @property string|null $message_cadeau
 * @property int|null $note_satisfaction
 * @property string|null $commentaire_satisfaction
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Client $client
 * @property Collection|ArticlesCommande[] $articles_commandes
 * @property Collection|Panier[] $paniers
 * @property Collection|Paiement[] $paiements
 * @property Collection|Facture[] $factures
 * @property Collection|Stock[] $stocks
 * @property Collection|Production[] $productions
 * @property Collection|MessagesWhatsapp[] $messages_whatsapps
 * @property Collection|AvisClient[] $avis_clients
 *
 * @package App\Models
 */
class Commande extends Model
{
	use SoftDeletes;
	protected $table = 'commandes';

	protected $casts = [
		'client_id' => 'int',
		'sous_total' => 'float',
		'frais_livraison' => 'float',
		'remise' => 'float',
		'montant_tva' => 'float',
		'montant_total' => 'float',
		'date_confirmation' => 'datetime',
		'stock_decremented_at' => 'datetime',
		'date_debut_production' => 'datetime',
		'date_fin_production' => 'datetime',
		'date_livraison_prevue' => 'datetime',
		'date_livraison_reelle' => 'datetime',
		'est_cadeau' => 'bool',
		'note_satisfaction' => 'int'
	];

	protected $fillable = [
		'numero_commande',
		'idempotency_key',
		'client_id',
		'sous_total',
		'frais_livraison',
		'remise',
		'montant_tva',
		'montant_total',
		'statut',
		'date_confirmation',
		'stock_decremented_at',
		'date_debut_production',
		'date_fin_production',
		'date_livraison_prevue',
		'date_livraison_reelle',
		'adresse_livraison',
		'telephone_livraison',
		'nom_destinataire',
		'instructions_livraison',
		'mode_livraison',
		'notes_client',
		'notes_admin',
		'notes_production',
		'source',
		'code_promo',
		'priorite',
		'est_cadeau',
		'message_cadeau',
		'note_satisfaction',
		'commentaire_satisfaction',
		'delivery_zone_id',
		'zone_livraison_nom',
	];

	public function client()
	{
		return $this->belongsTo(Client::class);
	}

	public function deliveryZone()
	{
		return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id');
	}

	public function articles_commandes()
	{
		return $this->hasMany(ArticlesCommande::class);
	}

	// Alias pour articles
	public function articles()
	{
		return $this->articles_commandes();
	}

	public function paniers()
	{
		return $this->hasMany(Panier::class);
	}

	public function paiements()
	{
		return $this->hasMany(Paiement::class);
	}

	public function factures()
	{
		return $this->hasMany(Facture::class);
	}

	public function stocks()
	{
		return $this->hasMany(Stock::class);
	}

	public function productions()
	{
		return $this->hasMany(Production::class);
	}

	public function messages_whatsapps()
	{
		return $this->hasMany(MessagesWhatsapp::class);
	}

	public function avis_clients()
	{
		return $this->hasMany(AvisClient::class);
	}
}
