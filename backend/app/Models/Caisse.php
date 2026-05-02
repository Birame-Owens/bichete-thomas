<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['date', 'solde_ouverture', 'solde_fermeture', 'statut', 'ouverte_at', 'fermee_at', 'note'])]
class Caisse extends Model
{
    use HasFactory;

    /**
     * @return HasMany<MouvementCaisse, $this>
     */
    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementCaisse::class);
    }

    public function totalEntrees(): float
    {
        return (float) $this->mouvements()->where('type', 'entree')->sum('montant');
    }

    public function totalSorties(): float
    {
        return (float) $this->mouvements()->where('type', 'sortie')->sum('montant');
    }

    public function soldeTheorique(): float
    {
        return (float) $this->solde_ouverture + $this->totalEntrees() - $this->totalSorties();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'solde_ouverture' => 'decimal:2',
            'solde_fermeture' => 'decimal:2',
            'ouverte_at' => 'datetime',
            'fermee_at' => 'datetime',
        ];
    }
}
