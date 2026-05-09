<?php

namespace App\Support;

use App\Models\ParametreSysteme;
use Illuminate\Support\Facades\Cache;

/**
 * Acces centralise et cache aux parametres systeme (I7).
 *
 * Avant : chaque controller/service avait sa propre methode settingValue() qui
 * faisait un SELECT par appel. ReservationAvailabilityController appelait par
 * exemple settingValue() 5 fois -> 5 SELECT par requete de disponibilite.
 *
 * Maintenant : un seul SELECT au cold-start qui charge tous les parametres,
 * mis en cache 1h. Les ecritures (admin qui modifie un parametre) flushent
 * automatiquement le cache via les model events de ParametreSysteme.
 *
 * En prod, le store cache pointe sur Redis (cf .env CACHE_STORE) -> 0 SQL
 * par lecture apres le 1er hit.
 */
class SystemSettings
{
    /** Cle unique pour stocker l ensemble des parametres. */
    private const CACHE_KEY = 'system_settings.all';

    /** TTL d 1 heure : protection contre un oubli de flush. Le flush via model
     *  events est l autorite principale ; ce TTL n est qu un filet de secours. */
    private const TTL_SECONDS = 3600;

    /**
     * Recupere la valeur scalaire d un parametre (deja unwrap-ee de la
     * structure JSON ['value' => ...] utilisee en base).
     *
     * @param string $key La cle, ex: "heure_ouverture", "limite_reservations_par_jour"
     * @param mixed $default Valeur de repli si la cle n existe pas
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $entry = self::all()[$key] ?? null;

        if (! is_array($entry)) {
            return $default;
        }

        return array_key_exists('value', $entry) ? $entry['value'] : $default;
    }

    /**
     * Charge tous les parametres en une seule requete.
     *
     * @return array<string, array<string, mixed>> Map cle => valeur (objet JSON brut)
     */
    public static function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::TTL_SECONDS, function (): array {
            return ParametreSysteme::query()
                ->get(['cle', 'valeur'])
                ->mapWithKeys(fn (ParametreSysteme $p): array => [
                    $p->cle => is_array($p->valeur) ? $p->valeur : [],
                ])
                ->all();
        });
    }

    /**
     * Invalide le cache. Appele automatiquement par les model events de
     * ParametreSysteme (saved/deleted), donc inutile en temps normal.
     * Disponible aussi pour les commandes artisan ou les seeders.
     */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
