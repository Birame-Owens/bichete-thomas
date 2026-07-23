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
 * Class Tailleur
 * 
 * @property int $id
 * @property string $nom
 * @property string $prenom
 * @property string $telephone
 * @property string|null $email
 * @property string|null $adresse
 * @property string $specialites
 * @property string $niveau_competence
 * @property string|null $description_competences
 * @property float $tarif_journalier
 * @property float|null $tarif_piece
 * @property string $mode_paiement
 * @property string $jours_travail
 * @property time without time zone $heure_debut
 * @property time without time zone $heure_fin
 * @property bool $est_disponible
 * @property Carbon $date_embauche
 * @property Carbon|null $date_fin_contrat
 * @property string $statut_emploi
 * @property int $pieces_completees
 * @property int $commandes_en_cours
 * @property float $temps_moyen_piece
 * @property float $evaluation_moyenne
 * @property int $nombre_evaluations
 * @property string|null $notes_admin
 * @property string $performance
 * @property bool $peut_formation
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property User|null $user
 * @property Collection|ArticlesCommande[] $articles_commandes
 * @property Collection|Production[] $productions
 *
 * @package App\Models
 */
class Tailleur extends Model
{
	use SoftDeletes;
	protected $table = 'tailleurs';

	protected $casts = [
		'tarif_journalier' => 'float',
		'tarif_piece' => 'float',
		'heure_debut' => 'time without time zone',
		'heure_fin' => 'time without time zone',
		'est_disponible' => 'bool',
		'date_embauche' => 'datetime',
		'date_fin_contrat' => 'datetime',
		'pieces_completees' => 'int',
		'commandes_en_cours' => 'int',
		'temps_moyen_piece' => 'float',
		'evaluation_moyenne' => 'float',
		'nombre_evaluations' => 'int',
		'peut_formation' => 'bool',
		'user_id' => 'int'
	];

	protected $fillable = [
		'nom',
		'prenom',
		'telephone',
		'email',
		'adresse',
		'specialites',
		'niveau_competence',
		'description_competences',
		'tarif_journalier',
		'tarif_piece',
		'mode_paiement',
		'jours_travail',
		'heure_debut',
		'heure_fin',
		'est_disponible',
		'date_embauche',
		'date_fin_contrat',
		'statut_emploi',
		'pieces_completees',
		'commandes_en_cours',
		'temps_moyen_piece',
		'evaluation_moyenne',
		'nombre_evaluations',
		'notes_admin',
		'performance',
		'peut_formation',
		'user_id'
	];

	public function user()
	{
		return $this->belongsTo(User::class);
	}

	public function articles_commandes()
	{
		return $this->hasMany(ArticlesCommande::class);
	}

	public function productions()
	{
		return $this->hasMany(Production::class);
	}
}
