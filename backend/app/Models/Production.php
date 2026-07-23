<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Production
 * 
 * @property int $id
 * @property int $commande_id
 * @property int $article_commande_id
 * @property int $tailleur_id
 * @property string $numero_production
 * @property string|null $instructions
 * @property string|null $mesures_client
 * @property string $statut
 * @property Carbon $date_debut_prevue
 * @property Carbon $date_fin_prevue
 * @property Carbon|null $date_debut_reelle
 * @property Carbon|null $date_fin_reelle
 * @property int $duree_prevue_heures
 * @property int|null $duree_reelle_heures
 * @property string $tissus_utilises
 * @property string|null $accessoires_utilises
 * @property float $cout_materiaux
 * @property float $cout_main_oeuvre
 * @property float $cout_total
 * @property float $prix_vente_final
 * @property float $marge_beneficiaire
 * @property string $niveau_difficulte
 * @property bool $controle_qualite_ok
 * @property string|null $notes_qualite
 * @property int|null $note_qualite
 * @property bool $retouches_necessaires
 * @property string|null $details_retouches
 * @property int $temps_retouches_heures
 * @property string|null $notes_tailleur
 * @property string|null $notes_admin
 * @property string|null $problemes_rencontres
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Commande $commande
 * @property ArticlesCommande $articles_commande
 * @property Tailleur $tailleur
 * @property Collection|Stock[] $stocks
 *
 * @package App\Models
 */
class Production extends Model
{
	protected $table = 'productions';

	protected $casts = [
		'commande_id' => 'int',
		'article_commande_id' => 'int',
		'tailleur_id' => 'int',
		'date_debut_prevue' => 'datetime',
		'date_fin_prevue' => 'datetime',
		'date_debut_reelle' => 'datetime',
		'date_fin_reelle' => 'datetime',
		'duree_prevue_heures' => 'int',
		'duree_reelle_heures' => 'int',
		'cout_materiaux' => 'float',
		'cout_main_oeuvre' => 'float',
		'cout_total' => 'float',
		'prix_vente_final' => 'float',
		'marge_beneficiaire' => 'float',
		'controle_qualite_ok' => 'bool',
		'note_qualite' => 'int',
		'retouches_necessaires' => 'bool',
		'temps_retouches_heures' => 'int'
	];

	protected $fillable = [
		'commande_id',
		'article_commande_id',
		'tailleur_id',
		'numero_production',
		'instructions',
		'mesures_client',
		'statut',
		'date_debut_prevue',
		'date_fin_prevue',
		'date_debut_reelle',
		'date_fin_reelle',
		'duree_prevue_heures',
		'duree_reelle_heures',
		'tissus_utilises',
		'accessoires_utilises',
		'cout_materiaux',
		'cout_main_oeuvre',
		'cout_total',
		'prix_vente_final',
		'marge_beneficiaire',
		'niveau_difficulte',
		'controle_qualite_ok',
		'notes_qualite',
		'note_qualite',
		'retouches_necessaires',
		'details_retouches',
		'temps_retouches_heures',
		'notes_tailleur',
		'notes_admin',
		'problemes_rencontres'
	];

	public function commande()
	{
		return $this->belongsTo(Commande::class);
	}

	public function articles_commande()
	{
		return $this->belongsTo(ArticlesCommande::class, 'article_commande_id');
	}

	public function tailleur()
	{
		return $this->belongsTo(Tailleur::class);
	}

	public function stocks()
	{
		return $this->hasMany(Stock::class);
	}
}
