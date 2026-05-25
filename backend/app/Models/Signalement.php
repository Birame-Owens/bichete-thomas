<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['gerante_id', 'type', 'titre', 'description', 'urgence', 'lu_par_admin', 'lu_at', 'traite', 'traite_at', 'note_admin'])]
class Signalement extends Model
{
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function gerante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gerante_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lu_par_admin' => 'boolean',
            'lu_at'        => 'datetime',
            'traite'       => 'boolean',
            'traite_at'    => 'datetime',
        ];
    }
}
