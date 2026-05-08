<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'coiffure_id',
    'client_id',
    'reservation_id',
    'nom_client',
    'telephone',
    'email',
    'note',
    'commentaire',
    'photo_url',
    'statut',
    'verifie',
    'publie_at',
])]
class AvisCoiffure extends Model
{
    use HasFactory;

    protected $table = 'avis_coiffures';

    /**
     * @return BelongsTo<Coiffure, $this>
     */
    public function coiffure(): BelongsTo
    {
        return $this->belongsTo(Coiffure::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'note' => 'integer',
            'verifie' => 'boolean',
            'publie_at' => 'datetime',
        ];
    }
}
