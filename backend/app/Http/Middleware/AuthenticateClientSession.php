<?php

namespace App\Http\Middleware;

use App\Services\ClientMagicLinkService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifie le cookie de session client `bt_client_session`.
 *
 * Pas de sliding-window d inactivite : le cycle de reservation d un salon
 * est de plusieurs semaines, une fenetre courte deconnecterait la cliente
 * avant sa prochaine visite et viderait l interet du magic link.
 * La session expire apres 90 jours d absence totale (session_expires_at).
 *
 * Si valide, injecte `clientSession` dans la requete (instance Client).
 * Si invalide ou absent, retourne 401.
 */
class AuthenticateClientSession
{
    public function __construct(private readonly ClientMagicLinkService $magicLink) {}

    public function handle(Request $request, Closure $next): Response
    {
        $raw = $request->cookie('bt_client_session');

        if (! $raw || ! is_string($raw)) {
            return response()->json(['message' => 'Session expirée. Reconnectez-vous via votre lien WhatsApp.'], 401);
        }

        $client = $this->magicLink->clientFromSession($raw);

        if (! $client) {
            return response()->json(['message' => 'Session expirée. Reconnectez-vous via votre lien WhatsApp.'], 401)
                ->withoutCookie('bt_client_session');
        }

        $request->merge(['clientSession' => $client]);

        return $next($request);
    }
}
