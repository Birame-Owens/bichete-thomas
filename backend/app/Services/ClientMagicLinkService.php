<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Str;

/**
 * Gestion des magic links et sessions clients (Phase 5 etape 2).
 *
 * Principe : on stocke uniquement le hash SHA-256 du token en base.
 * Le token brut ne vit que dans le lien WhatsApp (magic link) ou dans
 * le cookie httpOnly (session). Si la DB est compromise, les tokens
 * bruts restent inconnus de l attaquant.
 */
class ClientMagicLinkService
{
    private const MAGIC_LINK_TTL_HOURS = 24;

    private const SESSION_TTL_DAYS = 90;

    // -----------------------------------------------------------------
    // Magic link (single-use, TTL 24h)
    // -----------------------------------------------------------------

    /**
     * Genere un magic link token pour le client.
     * Retourne le token brut a inclure dans l URL WhatsApp.
     * Ecrase le precedent token non-utilise (un seul lien valide a la fois).
     */
    public function generateMagicLink(Client $client): string
    {
        $raw = Str::random(64);

        $client->forceFill([
            'magic_link_token' => hash('sha256', $raw),
            'magic_link_expires_at' => now()->addHours(self::MAGIC_LINK_TTL_HOURS),
        ])->save();

        return $raw;
    }

    /**
     * Verifie un token magic link.
     * Retourne le client et consomme le token (single-use).
     * Retourne null si invalide ou expire.
     */
    public function verifyMagicLink(string $rawToken): ?Client
    {
        $hash = hash('sha256', $rawToken);

        $client = Client::query()
            ->where('magic_link_token', $hash)
            ->where('magic_link_expires_at', '>', now())
            ->first();

        if (! $client) {
            return null;
        }

        // Consommation : on efface le token pour qu il ne puisse pas etre rejoue.
        $client->forceFill([
            'magic_link_token' => null,
            'magic_link_expires_at' => null,
        ])->save();

        return $client;
    }

    // -----------------------------------------------------------------
    // Session persistante (cookie 90 jours)
    // -----------------------------------------------------------------

    /**
     * Cree une session persistante pour le client.
     * Retourne le token brut a poser dans le cookie httpOnly.
     */
    public function createSession(Client $client): string
    {
        $raw = Str::random(64);

        $client->forceFill([
            'session_token' => hash('sha256', $raw),
            'session_expires_at' => now()->addDays(self::SESSION_TTL_DAYS),
        ])->save();

        return $raw;
    }

    /**
     * Retrouve le client depuis un token de session brut (issu du cookie).
     * Retourne null si invalide, expire, ou client blackliste.
     */
    public function clientFromSession(string $rawToken): ?Client
    {
        $hash = hash('sha256', $rawToken);

        return Client::query()
            ->where('session_token', $hash)
            ->where('session_expires_at', '>', now())
            ->where('est_blackliste', false)
            ->first();
    }

    /**
     * Revoque la session du client (deconnexion).
     */
    public function revokeSession(Client $client): void
    {
        $client->forceFill([
            'session_token' => null,
            'session_expires_at' => null,
        ])->save();
    }

    // -----------------------------------------------------------------
    // URL
    // -----------------------------------------------------------------

    /**
     * Construit l URL du magic link a inclure dans le message WhatsApp.
     */
    public function buildUrl(string $rawToken): string
    {
        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return $base . '/client?magic_token=' . $rawToken;
    }
}
