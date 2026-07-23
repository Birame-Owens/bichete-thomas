<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'default_cost',
        'free_threshold',
        'is_enabled',
    ];

    protected $casts = [
        'default_cost' => 'decimal:2',
        'free_threshold' => 'decimal:2',
        'is_enabled' => 'boolean',
    ];

    /**
     * Récupérer les paramètres de livraison (singleton)
     */
    public static function getSettings()
    {
        $settings = self::first();
        
        // Si aucun paramètre n'existe, créer avec valeurs par défaut
        if (!$settings) {
            $settings = self::create([
                'default_cost' => 2500,
                'free_threshold' => 50000,
                'is_enabled' => true,
            ]);
        }
        
        return $settings;
    }
}
