<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code',
    'nom',
    'type_reduction',
    'valeur',
    'date_debut',
    'date_fin',
    'limite_utilisation',
    'nombre_utilisations',
    'actif',
])]
class CodePromo extends Model
{
    use HasFactory;

    protected $table = 'codes_promo';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valeur' => 'decimal:2',
            'date_debut' => 'datetime',
            'date_fin' => 'datetime',
            'limite_utilisation' => 'integer',
            'nombre_utilisations' => 'integer',
            'actif' => 'boolean',
        ];
    }
}
