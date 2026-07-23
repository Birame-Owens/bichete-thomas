<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class MessagesWhatsapp
 * 
 * @property int $id
 * @property string $numero_destinataire
 * @property string|null $nom_destinataire
 * @property string $message
 * @property string $type_message
 * @property int|null $commande_id
 * @property int|null $client_id
 * @property string $statut
 * @property string|null $message_id_api
 * @property string|null $reponse_api
 * @property string|null $erreur_api
 * @property Carbon|null $date_programmee
 * @property Carbon|null $date_envoi_reelle
 * @property Carbon|null $date_livraison
 * @property Carbon|null $date_lecture
 * @property bool $est_automatique
 * @property string|null $envoye_par
 * @property string|null $notes_admin
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Commande|null $commande
 * @property Client|null $client
 *
 * @package App\Models
 */
class MessagesWhatsapp extends Model
{
	protected $table = 'messages_whatsapp';

	protected $casts = [
		'commande_id' => 'int',
		'client_id' => 'int',
		'date_programmee' => 'datetime',
		'date_envoi_reelle' => 'datetime',
		'date_livraison' => 'datetime',
		'date_lecture' => 'datetime',
		'est_automatique' => 'bool'
	];

	protected $fillable = [
		'numero_destinataire',
		'nom_destinataire',
		'message',
		'type_message',
		'commande_id',
		'client_id',
		'statut',
		'message_id_api',
		'reponse_api',
		'erreur_api',
		'date_programmee',
		'date_envoi_reelle',
		'date_livraison',
		'date_lecture',
		'est_automatique',
		'envoye_par',
		'notes_admin'
	];

	public function commande()
	{
		return $this->belongsTo(Commande::class);
	}

	public function client()
	{
		return $this->belongsTo(Client::class);
	}
}
