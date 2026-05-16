<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['categorie_coiffure_id', 'nom', 'description', 'image', 'actif', 'est_populaire', 'est_nouveaute'])]
class Coiffure extends Model
{
    use HasFactory;

    /**
     * @return BelongsTo<CategorieCoiffure, $this>
     */
    public function categorie(): BelongsTo
    {
        return $this->belongsTo(CategorieCoiffure::class, 'categorie_coiffure_id');
    }

    /**
     * @return HasMany<VarianteCoiffure, $this>
     */
    public function variantes(): HasMany
    {
        return $this->hasMany(VarianteCoiffure::class);
    }

    /**
     * @return BelongsToMany<OptionCoiffure, $this>
     */
    public function options(): BelongsToMany
    {
        return $this->belongsToMany(OptionCoiffure::class, 'coiffure_option')->withTimestamps();
    }

    /**
     * @return HasMany<ImageCoiffure, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ImageCoiffure::class);
    }

    /**
     * @return HasMany<AvisCoiffure, $this>
     */
    public function avis(): HasMany
    {
        return $this->hasMany(AvisCoiffure::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'est_populaire' => 'boolean',
            'est_nouveaute' => 'boolean',
        ];
    }
}
