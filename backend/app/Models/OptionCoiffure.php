<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['nom', 'prix', 'actif'])]
class OptionCoiffure extends Model
{
    use HasFactory;

    protected $table = 'options_coiffures';

    /**
     * @return BelongsToMany<Coiffure, $this>
     */
    public function coiffures(): BelongsToMany
    {
        return $this->belongsToMany(Coiffure::class, 'coiffure_option')->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prix' => 'decimal:2',
            'actif' => 'boolean',
        ];
    }
}
