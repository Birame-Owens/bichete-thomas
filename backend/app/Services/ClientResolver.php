<?php

namespace App\Services;

use App\Models\Client;
use App\Support\PhoneNumber;
use Illuminate\Validation\ValidationException;

/**
 * Resolution unifiee d un client par telephone (Phase 5 etape 1).
 *
 * Avant : 3 controllers (Client/Reservation, Admin/Reservation, Admin/Paiement)
 * dupliquaient leur propre findOrCreateClient avec des regles legerement
 * divergentes (Client matchait nom+prenom+tel, Admin/Paiement matchait tel
 * seul). Resultat : un client qui retape son nom differemment creait un doublon
 * - violation de la contrainte UNIQUE sur clients.telephone.
 *
 * Maintenant : un point unique qui :
 *   1) normalise le tel en E.164 (sinon ValidationException) ;
 *   2) match strictement par tel normalise (pas de nom/prenom dans la cle) ;
 *   3) check blacklist ;
 *   4) cree avec tel normalise + preferences par defaut.
 *
 * Le matching tel-only est conforme a l intention metier : la contrainte UNIQUE
 * en base garantit deja qu un seul client peut porter un tel donne. Le rajout
 * de nom/prenom dans la query etait une fausse securite qui creait des doublons.
 */
class ClientResolver
{
    /** Message blacklist par defaut, surchargeable par les call-sites. */
    public const DEFAULT_BLACKLIST_MESSAGE = 'Ce telephone appartient a un client dans la liste noire.';

    /**
     * Trouve un client par telephone E.164, ou en cree un. Leve ValidationException
     * si le tel est invalide ou si le client est blackliste.
     *
     * @param  array<string, mixed>  $data  Doit contenir telephone, nom, prenom (string).
     *                                      email et source optionnels.
     * @param  string  $defaultSource  Source quand non fournie dans $data ('en_ligne' / 'physique').
     * @param  string  $blacklistMessage  Message de l erreur blacklist (variant client/admin).
     * @param  bool  $createPreferences  True pour creer la ligne preferences_clients par defaut.
     *
     * @throws ValidationException
     */
    public function findOrCreate(
        array $data,
        string $defaultSource = 'physique',
        string $blacklistMessage = self::DEFAULT_BLACKLIST_MESSAGE,
        bool $createPreferences = true,
    ): Client {
        $rawTelephone = (string) ($data['telephone'] ?? '');
        $telephone = PhoneNumber::normalize($rawTelephone);

        if ($telephone === null) {
            throw ValidationException::withMessages([
                'client.telephone' => 'Le numero de telephone est invalide.',
            ]);
        }

        $client = $this->findByPhone($telephone);

        if ($client) {
            if ($client->est_blackliste) {
                throw ValidationException::withMessages([
                    'client.telephone' => $blacklistMessage,
                ]);
            }

            return $client;
        }

        $client = Client::query()->create([
            'nom' => trim((string) ($data['nom'] ?? '')),
            'prenom' => trim((string) ($data['prenom'] ?? '')),
            'telephone' => $telephone,
            'email' => $data['email'] ?? null,
            'source' => $data['source'] ?? $defaultSource,
        ]);

        if ($createPreferences) {
            $client->preferences()->create([
                'notifications_whatsapp' => true,
                'notifications_promos' => true,
            ]);
        }

        return $client;
    }

    /**
     * Lookup pur, sans creation. Pour l endpoint /client/lookup. Attend du E.164
     * deja normalise (le caller doit avoir appele PhoneNumber::normalize).
     */
    public function findByPhone(string $phoneE164): ?Client
    {
        return Client::query()->where('telephone', $phoneE164)->first();
    }
}
