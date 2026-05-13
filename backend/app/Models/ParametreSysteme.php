<?php

namespace App\Models;

use App\Support\SystemSettings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['cle', 'valeur', 'type', 'description', 'modifiable'])]
class ParametreSysteme extends Model
{
    use HasFactory;

    protected $table = 'parametres_systeme';

    /**
     * Model events (I7) : tout changement (creation / mise a jour / suppression)
     * d un parametre invalide le cache de SystemSettings, pour que la prochaine
     * lecture reflete immediatement la nouvelle valeur. Plus besoin d attendre
     * l expiration du TTL.
     */
    protected static function booted(): void
    {
        static::saved(static function (): void {
            SystemSettings::flush();
        });
        static::deleted(static function (): void {
            SystemSettings::flush();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valeur' => 'array',
            'modifiable' => 'boolean',
        ];
    }
}
