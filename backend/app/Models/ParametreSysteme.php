<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['cle', 'valeur', 'type', 'description', 'modifiable'])]
class ParametreSysteme extends Model
{
    use HasFactory;

    protected $table = 'parametres_systeme';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valeur' => 'array',
            'modifiable' => 'boolean',
        ];
    }
}
