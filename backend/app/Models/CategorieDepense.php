<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nom', 'description', 'actif'])]
class CategorieDepense extends Model
{
    use HasFactory;

    protected $table = 'categories_depenses';

    /**
     * @return HasMany<Depense, $this>
     */
    public function depenses(): HasMany
    {
        return $this->hasMany(Depense::class);
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
