<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nom', 'prenom', 'telephone', 'pourcentage_commission', 'actif'])]
class Coiffeuse extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pourcentage_commission' => 'decimal:2',
            'actif' => 'boolean',
        ];
    }
}
