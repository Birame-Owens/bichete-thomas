<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['caisse_id', 'type', 'montant', 'description', 'source', 'reference', 'date_mouvement'])]
class MouvementCaisse extends Model
{
    use HasFactory;

    protected $table = 'mouvements_caisses';

    /**
     * @return BelongsTo<Caisse, $this>
     */
    public function caisse(): BelongsTo
    {
        return $this->belongsTo(Caisse::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date_mouvement' => 'datetime',
        ];
    }
}
