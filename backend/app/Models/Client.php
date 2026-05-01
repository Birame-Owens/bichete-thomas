<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'nom',
    'prenom',
    'telephone',
    'email',
    'source',
    'nombre_reservations_terminees',
    'fidelite_disponible',
    'est_blackliste',
])]
class Client extends Model
{
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nombre_reservations_terminees' => 'integer',
            'fidelite_disponible' => 'boolean',
            'est_blackliste' => 'boolean',
        ];
    }
}
