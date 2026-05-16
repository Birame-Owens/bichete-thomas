<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\ClientMagicLinkService;
use App\Services\ClientResolver;
use App\Services\WhatsappService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Propaganistas\LaravelPhone\Rules\Phone;

class ClientSessionController extends Controller
{
    public function __construct(
        private readonly ClientMagicLinkService $magicLink,
        private readonly ClientResolver $clientResolver,
        private readonly WhatsappService $whatsapp,
    ) {}

    /**
     * POST /api/client/auth/login
     *
     * Demande un magic link par WhatsApp pour un client deja connu.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'telephone' => ['required', 'string', 'max:30', (new Phone())->country(['SN'])->international()],
        ]);

        $telephone = PhoneNumber::normalize((string) $data['telephone']);
        $client = $telephone ? $this->clientResolver->findByPhone($telephone) : null;

        if (! $client || $client->est_blackliste) {
            return response()->json([
                'message' => 'Aucun compte client actif ne correspond à ce telephone.',
            ], 404);
        }

        return $this->sendMagicLinkResponse($client, 'client-login-' . $client->id);
    }

    /**
     * POST /api/client/auth/register
     *
     * Cree la fiche client si le numero est nouveau, puis envoie un magic link.
     *
     * Reponse ambigue intentionnelle : si le numero existe deja (ou est blackliste),
     * on retourne le meme message generique sans rien faire. Un attaquant ne peut
     * donc pas enumerer les numeros enregistres en base, ni declencher un magic
     * link vers le telephone d autrui via cette route.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prenom' => ['required', 'string', 'max:255'],
            'nom' => ['required', 'string', 'max:255'],
            'telephone' => ['required', 'string', 'max:30', (new Phone())->country(['SN'])->international()],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $telephone = PhoneNumber::normalize((string) $data['telephone']);
        $existing = $telephone ? $this->clientResolver->findByPhone($telephone) : null;

        // Reponse ambigue : ne pas reveler si le numero est deja enregistre.
        if ($existing) {
            return response()->json([
                'message' => 'Si ce numéro est éligible, vous recevrez un lien de connexion par WhatsApp.',
            ], 200);
        }

        $client = $this->clientResolver->findOrCreate(
            $data,
            defaultSource: 'en_ligne',
            blacklistMessage: 'Ce telephone ne peut pas creer de compte client.',
        );

        if (! empty($data['email']) && empty($client->email)) {
            $client->forceFill(['email' => $data['email']])->save();
        }

        return $this->sendMagicLinkResponse($client, 'client-register-' . $client->id, 201);
    }

    /**
     * POST /api/client/auth/magic-link
     *
     * Verifie le token magic link, cree une session 90j et pose le cookie.
     * Token single-use : consomme immediatement a la verification.
     */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64'],
        ]);

        $client = $this->magicLink->verifyMagicLink($data['token']);

        if (! $client) {
            return response()->json([
                'message' => 'Lien invalide ou expiré.',
            ], 422);
        }

        $sessionToken = $this->magicLink->createSession($client);

        return response()->json([
            'message' => 'Connexion reussie.',
            'data' => [
                'nom' => $client->nom,
                'prenom' => $client->prenom,
                'telephone' => $client->telephone,
            ],
        ])->cookie(
            'bt_client_session',
            $sessionToken,
            60 * 24 * 90, // 90 jours en minutes
            '/',
            null,
            app()->isProduction(), // Secure uniquement en prod (HTTPS)
            true,  // httpOnly
            false, // raw
            'lax', // sameSite
        );
    }

    /**
     * GET /api/client/session
     *
     * Retourne les infos du client connecte (cookie valide requis).
     * Utilise par le frontend au montage pour le prefill automatique.
     */
    public function session(Request $request): JsonResponse
    {
        /** @var \App\Models\Client $client */
        $client = $request->get('clientSession');

        return response()->json([
            'data' => [
                'nom' => $client->nom,
                'prenom' => $client->prenom,
                'telephone' => $client->telephone,
            ],
        ]);
    }

    /**
     * DELETE /api/client/session
     *
     * Deconnecte le client : revoque le session token en DB et efface le cookie.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var \App\Models\Client $client */
        $client = $request->get('clientSession');
        $this->magicLink->revokeSession($client);

        return response()->json(['message' => 'Deconnecte.'])
            ->withoutCookie('bt_client_session');
    }

    private function sendMagicLinkResponse(Client $client, string $context, int $status = 200): JsonResponse
    {
        $rawToken = $this->magicLink->generateMagicLink($client);
        $url = $this->magicLink->buildUrl($rawToken);
        $message = implode("\n", [
            "Bonjour {$client->prenom},",
            'Voici votre lien de connexion Bichette Thomas :',
            $url,
            '',
            'Ce lien est valable 24h.',
        ]);

        $sent = $this->whatsapp->send($client->telephone, $message, $context);

        $payload = [
            'message' => $sent
                ? 'Lien de connexion envoye par WhatsApp.'
                : 'Compte pret. WhatsApp n est pas configure pour envoyer le lien automatiquement.',
        ];

        if (! app()->isProduction()) {
            $payload['debug_magic_url'] = $url;
        }

        return response()->json($payload, $status);
    }
}
