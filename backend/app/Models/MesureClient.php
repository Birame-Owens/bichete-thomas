<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MesureClient extends Model
{
    use SoftDeletes;
    
    protected $table = 'mesures_clients';

    protected $fillable = [
        'client_id',
        'epaule',
        'poitrine',
        'taille',
        'longueur_robe',
        'tour_bras',
        'tour_cuisses',
        'longueur_jupe',
        'ceinture',
        'tour_fesses',
        'buste',
        'longueur_manches_longues',
        'longueur_manches_courtes',
        'longueur_short',
        'cou',
        'longueur_taille_basse',
        'notes_mesures',
        'date_prise_mesures',
        'mesures_valides'
    ];

    protected $casts = [
        'epaule' => 'decimal:2',
        'poitrine' => 'decimal:2',
        'taille' => 'decimal:2',
        'longueur_robe' => 'decimal:2',
        'tour_bras' => 'decimal:2',
        'tour_cuisses' => 'decimal:2',
        'longueur_jupe' => 'decimal:2',
        'ceinture' => 'decimal:2',
        'tour_fesses' => 'decimal:2',
        'buste' => 'decimal:2',
        'longueur_manches_longues' => 'decimal:2',
        'longueur_manches_courtes' => 'decimal:2',
        'longueur_short' => 'decimal:2',
        'cou' => 'decimal:2',
        'longueur_taille_basse' => 'decimal:2',
        'date_prise_mesures' => 'date',
        'mesures_valides' => 'boolean'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Obtenir toutes les mesures non nulles
     */
    public function getMesuresRemplies(): array
    {
        $mesures = [];
        $champs = [
            'epaule', 'poitrine', 'taille', 'longueur_robe', 'tour_bras',
            'tour_cuisses', 'longueur_jupe', 'ceinture', 'tour_fesses',
            'buste', 'longueur_manches_longues', 'longueur_manches_courtes',
            'longueur_short', 'cou', 'longueur_taille_basse'
        ];

        foreach ($champs as $champ) {
            if ($this->$champ !== null) {
                $mesures[$champ] = $this->$champ;
            }
        }

        return $mesures;
    }
}