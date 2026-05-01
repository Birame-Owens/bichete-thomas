<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nom', 'description', 'actif'])]
class CategorieCoiffure extends Model
{
    use HasFactory;

    protected $table = 'categories_coiffures';

    /**
     * @return HasMany<Coiffure, $this>
     */
    public function coiffures(): HasMany
    {
        return $this->hasMany(Coiffure::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }
}
