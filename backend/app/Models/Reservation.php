<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'client_id',
    'coiffeuse_id',
    'code_promo_id',
    'regle_fidelite_id',
    'date_reservation',
    'heure_debut',
    'heure_fin',
    'duree_totale_minutes',
    'statut',
    'source',
    'montant_total',
    'montant_reduction',
    'montant_acompte',
    'montant_restant',
    'devise',
    'fidelite_appliquee',
    'notes',
    'annulee_at',
    'terminee_at',
])]
class Reservation extends Model
{
    use HasFactory;

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Coiffeuse, $this>
     */
    public function coiffeuse(): BelongsTo
    {
        return $this->belongsTo(Coiffeuse::class);
    }

    /**
     * @return BelongsTo<CodePromo, $this>
     */
    public function codePromo(): BelongsTo
    {
        return $this->belongsTo(CodePromo::class);
    }

    /**
     * @return BelongsTo<RegleFidelite, $this>
     */
    public function regleFidelite(): BelongsTo
    {
        return $this->belongsTo(RegleFidelite::class);
    }

    /**
     * @return HasMany<DetailReservation, $this>
     */
    public function details(): HasMany
    {
        return $this->hasMany(DetailReservation::class);
    }

    /**
     * @return HasMany<Paiement, $this>
     */
    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_reservation' => 'date',
            'duree_totale_minutes' => 'integer',
            'montant_total' => 'decimal:2',
            'montant_reduction' => 'decimal:2',
            'montant_acompte' => 'decimal:2',
            'montant_restant' => 'decimal:2',
            'fidelite_appliquee' => 'boolean',
            'annulee_at' => 'datetime',
            'terminee_at' => 'datetime',
        ];
    }
}
