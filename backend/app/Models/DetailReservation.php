<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reservation_id',
    'coiffure_id',
    'variante_coiffure_id',
    'coiffure_nom',
    'variante_nom',
    'prix_unitaire',
    'duree_minutes',
    'quantite',
    'option_ids',
    'options_snapshot',
    'montant_options',
    'montant_total',
    'ordre',
])]
class DetailReservation extends Model
{
    use HasFactory;

    protected $table = 'details_reservations';

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return BelongsTo<Coiffure, $this>
     */
    public function coiffure(): BelongsTo
    {
        return $this->belongsTo(Coiffure::class);
    }

    /**
     * @return BelongsTo<VarianteCoiffure, $this>
     */
    public function variante(): BelongsTo
    {
        return $this->belongsTo(VarianteCoiffure::class, 'variante_coiffure_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prix_unitaire' => 'decimal:2',
            'duree_minutes' => 'integer',
            'quantite' => 'integer',
            'option_ids' => 'array',
            'options_snapshot' => 'array',
            'montant_options' => 'decimal:2',
            'montant_total' => 'decimal:2',
            'ordre' => 'integer',
        ];
    }
}
