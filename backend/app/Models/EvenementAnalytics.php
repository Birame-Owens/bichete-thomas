<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'nom_evenement',
    'page_slug',
    'page_url',
    'referrer',
    'visitor_id',
    'session_id',
    'utm_source',
    'utm_medium',
    'utm_campaign',
    'utm_term',
    'utm_content',
    'metadata',
    'ip_address',
    'user_agent',
    'occurred_at',
])]
class EvenementAnalytics extends Model
{
    use HasFactory;

    protected $table = 'evenements_analytics';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
