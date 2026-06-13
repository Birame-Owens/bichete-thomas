<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['url', 'titre', 'sous_titre', 'ordre', 'actif'])]
class GaleriePhoto extends Model
{
    use HasFactory;

    protected $table = 'galerie_photos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordre' => 'integer',
            'actif' => 'boolean',
        ];
    }
}
