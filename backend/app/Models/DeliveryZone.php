<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    protected $fillable = ['nom', 'prix', 'est_active', 'ordre_affichage'];

    protected $casts = [
        'prix' => 'float',
        'est_active' => 'boolean',
        'ordre_affichage' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('est_active', true)->orderBy('ordre_affichage');
    }
}
