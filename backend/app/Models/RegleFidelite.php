<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nom', 'nombre_reservations_requis', 'type_recompense', 'valeur_recompense', 'actif'])]
class RegleFidelite extends Model
{
    use HasFactory;

    protected $table = 'regles_fidelite';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nombre_reservations_requis' => 'integer',
            'valeur_recompense' => 'decimal:2',
            'actif' => 'boolean',
        ];
    }
}
