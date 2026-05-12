<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Services\ClientMagicLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientSessionController extends Controller
{
    public function __construct(private readonly ClientMagicLinkService $magicLink) {}

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
}
