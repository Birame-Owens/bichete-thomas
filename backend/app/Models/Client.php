<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
     * @return HasOne<PreferenceClient, $this>
     */
    public function preferences(): HasOne
    {
        return $this->hasOne(PreferenceClient::class);
    }

    /**
     * @return HasMany<ListeNoireClient, $this>
     */
    public function listeNoire(): HasMany
    {
        return $this->hasMany(ListeNoireClient::class);
    }

    /**
     * @return HasOne<ListeNoireClient, $this>
     */
    public function blacklistActive(): HasOne
    {
        return $this->hasOne(ListeNoireClient::class)->where('actif', true);
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
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
