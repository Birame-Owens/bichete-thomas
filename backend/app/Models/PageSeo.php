<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'slug',
    'titre',
    'meta_title',
    'meta_description',
    'keywords',
    'canonical_url',
    'image_og',
    'robots',
    'type_page',
    'cible_type',
    'cible_id',
    'schema_json',
    'actif',
    'published_at',
])]
class PageSeo extends Model
{
    use HasFactory;

    protected $table = 'pages_seo';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'schema_json' => 'array',
            'actif' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
