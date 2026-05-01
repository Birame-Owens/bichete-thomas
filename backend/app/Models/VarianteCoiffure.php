<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['coiffure_id', 'nom', 'prix', 'duree_minutes', 'actif'])]
class VarianteCoiffure extends Model
{
    use HasFactory;

    protected $table = 'variantes_coiffures';

    /**
     * @return BelongsTo<Coiffure, $this>
     */
    public function coiffure(): BelongsTo
    {
        return $this->belongsTo(Coiffure::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prix' => 'decimal:2',
            'duree_minutes' => 'integer',
            'actif' => 'boolean',
        ];
    }
}
