<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ArticlesCommande
 * 
 * @property int $id
 * @property int $commande_id
 * @property int $produit_id
 * @property string $nom_produit
 * @property string|null $description_produit
 * @property float $prix_unitaire
 * @property int $quantite
 * @property float $prix_total_article
 * @property string|null $taille_choisie
 * @property string|null $couleur_choisie
 * @property string|null $options_supplementaires
 * @property string|null $demandes_personnalisation
 * @property string|null $mesures_client
 * @property string|null $instructions_tailleur
 * @property int|null $tailleur_id
 * @property string $statut_production
 * @property Carbon|null $date_affectation
 * @property Carbon|null $date_debut_production
 * @property Carbon|null $date_fin_prevue
 * @property Carbon|null $date_fin_reelle
 * @property string|null $tissus_utilises
 * @property float|null $cout_materiaux
 * @property float|null $temps_production_heures
 * @property bool $controle_qualite_ok
 * @property string|null $notes_qualite
 * @property int|null $note_client_article
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Commande $commande
 * @property Produit $produit
 * @property Tailleur|null $tailleur
 * @property Collection|Production[] $productions
 *
 * @package App\Models
 */
class ArticlesCommande extends Model
{
	protected $table = 'articles_commande';

	protected $casts = [
		'commande_id' => 'int',
		'produit_id' => 'int',
		'prix_unitaire' => 'float',
		'quantite' => 'int',
		'prix_total_article' => 'float',
		'tailleur_id' => 'int',
		'date_affectation' => 'datetime',
		'date_debut_production' => 'datetime',
		'date_fin_prevue' => 'datetime',
		'date_fin_reelle' => 'datetime',
		'cout_materiaux' => 'float',
		'temps_production_heures' => 'float',
		'controle_qualite_ok' => 'bool',
		'note_client_article' => 'int'
	];

	protected $fillable = [
		'commande_id',
		'produit_id',
		'nom_produit',
		'description_produit',
		'prix_unitaire',
		'quantite',
		'prix_total_article',
		'taille_choisie',
		'couleur_choisie',
		'options_supplementaires',
		'demandes_personnalisation',
		'mesures_client',
		'instructions_tailleur',
		'tailleur_id',
		'statut_production',
		'date_affectation',
		'date_debut_production',
		'date_fin_prevue',
		'date_fin_reelle',
		'tissus_utilises',
		'cout_materiaux',
		'temps_production_heures',
		'controle_qualite_ok',
		'notes_qualite',
		'note_client_article'
	];

	public function commande()
	{
		return $this->belongsTo(Commande::class);
	}

	public function produit()
	{
		return $this->belongsTo(Produit::class);
	}

	public function tailleur()
	{
		return $this->belongsTo(Tailleur::class);
	}

	public function productions()
	{
		return $this->hasMany(Production::class, 'article_commande_id');
	}
}
