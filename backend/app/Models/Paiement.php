<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reservation_id',
    'client_id',
    'caisse_id',
    'mouvement_caisse_id',
    'numero_recu',
    'type',
    'mode_paiement',
    'montant',
    'devise',
    'statut',
    'date_paiement',
    'reference',
    'notes',
    'recu_envoye',
    'recu_envoye_at',
])]
class Paiement extends Model
{
    use HasFactory;

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Caisse, $this>
     */
    public function caisse(): BelongsTo
    {
        return $this->belongsTo(Caisse::class);
    }

    /**
     * @return BelongsTo<MouvementCaisse, $this>
     */
    public function mouvementCaisse(): BelongsTo
    {
        return $this->belongsTo(MouvementCaisse::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date_paiement' => 'datetime',
            'recu_envoye' => 'boolean',
            'recu_envoye_at' => 'datetime',
        ];
    }
}
