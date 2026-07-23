<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Panier
 * 
 * @property int $id
 * @property string|null $session_id
 * @property int|null $client_id
 * @property float $sous_total
 * @property int $nombre_articles
 * @property Carbon|null $date_reservation
 * @property Carbon|null $date_expiration
 * @property bool $est_reserve
 * @property string $statut
 * @property int|null $commande_id
 * @property Carbon|null $date_transformation
 * @property string|null $adresse_ip
 * @property string|null $user_agent
 * @property string|null $donnees_navigation
 * @property bool $email_abandon_envoye
 * @property bool $whatsapp_abandon_envoye
 * @property Carbon|null $derniere_activite
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Client|null $client
 * @property Commande|null $commande
 * @property Collection|Stock[] $stocks
 * @property Collection|ArticlesPanier[] $articles_paniers
 *
 * @package App\Models
 */
class Panier extends Model
{
	protected $table = 'paniers';

	protected $casts = [
		'client_id' => 'int',
		'sous_total' => 'float',
		'nombre_articles' => 'int',
		'date_reservation' => 'datetime',
		'date_expiration' => 'datetime',
		'est_reserve' => 'bool',
		'commande_id' => 'int',
		'date_transformation' => 'datetime',
		'email_abandon_envoye' => 'bool',
		'whatsapp_abandon_envoye' => 'bool',
		'derniere_activite' => 'datetime'
	];

	protected $fillable = [
		'identifiant',
		'session_id',
		'client_id',
		'sous_total',
		'nombre_articles',
		'date_reservation',
		'date_expiration',
		'est_reserve',
		'statut',
		'commande_id',
		'date_transformation',
		'adresse_ip',
		'user_agent',
		'donnees_navigation',
		'email_abandon_envoye',
		'whatsapp_abandon_envoye',
		'derniere_activite'
	];

	public function client()
	{
		return $this->belongsTo(Client::class);
	}

	public function commande()
	{
		return $this->belongsTo(Commande::class);
	}

	public function stocks()
	{
		return $this->hasMany(Stock::class);
	}

	public function articles_paniers()
	{
		return $this->hasMany(ArticlesPanier::class);
	}
}
