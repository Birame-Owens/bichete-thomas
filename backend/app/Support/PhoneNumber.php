<?php

namespace App\Support;

use libphonenumber\NumberParseException as LibNumberParseException;
use Propaganistas\LaravelPhone\Exceptions\NumberParseException as LaravelPhoneNumberParseException;
use Propaganistas\LaravelPhone\PhoneNumber as LibPhone;
use Throwable;

/**
 * Normalisation et validation des telephones (Phase 5 etape 1 - lookup international).
 *
 * Wrapper centralise autour de propaganistas/laravel-phone (lui-meme un wrapper
 * Laravel autour de libphonenumber, port officiel Google). Avant : aucune
 * normalisation, des "+221 77 ..." en base coexistaient avec des "+221771..." -
 * impossible de retrouver un client de maniere deterministe par son tel.
 * Maintenant : tout passe par normalize() qui sort du E.164 strict, le seul
 * format qui permet un match exact et une comparaison fiable inter-pays.
 *
 * Pourquoi un wrapper plutot que d appeler LibPhone directement aux call-sites :
 * - centraliser le default country (SN) une seule fois ;
 * - swallow les exceptions de parsing (les call-sites n ont jamais a try/catch) ;
 * - rendre le swap d implementation possible (cache LRU, telemetrie) sans diff
 *   sur les controllers/services.
 */
class PhoneNumber
{
    /** Pays par defaut quand l input ne contient pas de prefixe international (+221, +33, ...). */
    public const DEFAULT_COUNTRY = 'SN';

    /**
     * Normalise un telephone en E.164 (ex: "+221771234567"). Retourne null si
     * non parsable ou invalide. Volontairement non-throwing : chaque call-site
     * decide quoi faire du null (lookup => found:false, validation => fail,
     * migration => ligne CSV de conflit).
     *
     * Accepte aussi les numeros internationaux meme si differents du default :
     * un touriste avec "+33612345678" passe sans probleme.
     *
     * @param  string|null  $input  Tel brut saisi par l utilisateur ou stocke en base.
     * @param  string  $defaultCountry  Code ISO 2-lettres pour les inputs sans prefixe.
     */
    public static function normalize(?string $input, string $defaultCountry = self::DEFAULT_COUNTRY): ?string
    {
        if ($input === null) {
            return null;
        }

        $trimmed = trim($input);
        if ($trimmed === '') {
            return null;
        }

        try {
            $phone = new LibPhone($trimmed, [$defaultCountry]);

            if (! $phone->isValid()) {
                return null;
            }

            return $phone->formatE164();
        } catch (LaravelPhoneNumberParseException|LibNumberParseException $e) {
            return null;
        } catch (Throwable $e) {
            // Filet de securite : tout autre throw libphonenumber (NumberFormatException
            // sur input absurde, etc.) est traite comme "non parsable" plutot que
            // de leak une 500 sur un endpoint public comme /client/lookup.
            return null;
        }
    }

    /**
     * True si le tel peut etre normalise en E.164 valide pour le pays donne
     * (ou tout pays via le fallback international du default country).
     */
    public static function isValid(?string $input, string $defaultCountry = self::DEFAULT_COUNTRY): bool
    {
        return self::normalize($input, $defaultCountry) !== null;
    }
}
