<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class AvisClient
 * 
 * @property int $id
 * @property int $client_id
 * @property int $produit_id
 * @property int|null $commande_id
 * @property string|null $titre
 * @property string $commentaire
 * @property int $note_globale
 * @property int|null $note_qualite
 * @property int|null $note_taille
 * @property int|null $note_couleur
 * @property int|null $note_livraison
 * @property int|null $note_service
 * @property string|null $nom_affiche
 * @property bool $recommande_produit
 * @property bool $recommande_boutique
 * @property string $statut
 * @property string|null $raison_rejet
 * @property Carbon|null $date_moderation
 * @property string|null $modere_par
 * @property bool $est_visible
 * @property bool $est_mis_en_avant
 * @property int $ordre_affichage
 * @property int $nombre_likes
 * @property int $nombre_dislikes
 * @property bool $avis_verifie
 * @property string|null $adresse_ip
 * @property string|null $user_agent
 * @property string|null $photos_avis
 * @property string|null $reponse_boutique
 * @property Carbon|null $date_reponse
 * @property string|null $repondu_par
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Client $client
 * @property Produit $produit
 * @property Commande|null $commande
 *
 * @package App\Models
 */
class AvisClient extends Model
{
	use SoftDeletes;
	protected $table = 'avis_clients';

	protected $casts = [
		'client_id' => 'int',
		'produit_id' => 'int',
		'commande_id' => 'int',
		'note_globale' => 'int',
		'note_qualite' => 'int',
		'note_taille' => 'int',
		'note_couleur' => 'int',
		'note_livraison' => 'int',
		'note_service' => 'int',
		'recommande_produit' => 'bool',
		'recommande_boutique' => 'bool',
		'date_moderation' => 'datetime',
		'est_visible' => 'bool',
		'est_mis_en_avant' => 'bool',
		'ordre_affichage' => 'int',
		'nombre_likes' => 'int',
		'nombre_dislikes' => 'int',
		'avis_verifie' => 'bool',
		'date_reponse' => 'datetime',
		'photos_avis' => 'array'
	];

	protected $fillable = [
		'client_id',
		'produit_id',
		'commande_id',
		'titre',
		'commentaire',
		'note_globale',
		'note_qualite',
		'note_taille',
		'note_couleur',
		'note_livraison',
		'note_service',
		'nom_affiche',
		'recommande_produit',
		'recommande_boutique',
		'statut',
		'raison_rejet',
		'date_moderation',
		'modere_par',
		'est_visible',
		'est_mis_en_avant',
		'ordre_affichage',
		'nombre_likes',
		'nombre_dislikes',
		'avis_verifie',
		'adresse_ip',
		'user_agent',
		'photos_avis',
		'reponse_boutique',
		'date_reponse',
		'repondu_par'
	];

	public function client()
	{
		return $this->belongsTo(Client::class);
	}

	public function produit()
	{
		return $this->belongsTo(Produit::class);
	}

	public function commande()
	{
		return $this->belongsTo(Commande::class);
	}
}
