<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\AvisCoiffure;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Avis verifies post-prestation (Phase 5 etape 3).
 *
 * Le token dans l URL provient du lien WhatsApp envoye 24h apres la fin
 * de la reservation (job SendReviewInvitation). Il est single-use et expire
 * apres 7 jours. L avis cree est marque verifie=true pour distinguer les
 * avis clients reels des avis non verifies soumis librement via le catalogue.
 */
class AvisController extends Controller
{
    /**
     * GET /api/client/avis/{token}
     *
     * Retourne les infos de prefill (prenom + coiffure_nom) pour la page
     * d avis frontend. Ne revele pas de donnees sensibles (pas d email, pas
     * de telephone, pas d historique).
     */
    public function prefill(string $token): JsonResponse
    {
        $reservation = $this->findByToken($token);

        if (! $reservation) {
            return response()->json(['message' => 'Lien invalide ou expiré.'], 404);
        }

        return response()->json([
            'data' => [
                'prenom' => $reservation->client->prenom,
                'coiffure_nom' => $reservation->details->first()?->coiffure_nom ?? '',
            ],
        ]);
    }

    /**
     * POST /api/client/avis/{token}
     *
     * Cree un avis verifie pour la reservation liee au token.
     * Token consomme apres soumission (single-use).
     */
    public function store(Request $request, string $token): JsonResponse
    {
        $reservation = $this->findByToken($token);

        if (! $reservation) {
            return response()->json(['message' => 'Lien invalide ou expiré.'], 422);
        }

        // Un seul avis verifie par reservation.
        $alreadyReviewed = AvisCoiffure::query()
            ->where('reservation_id', $reservation->id)
            ->where('verifie', true)
            ->exists();

        if ($alreadyReviewed) {
            return response()->json(['message' => 'Vous avez déjà soumis un avis pour cette prestation.'], 422);
        }

        $data = $request->validate([
            'note' => ['required', 'integer', 'min:1', 'max:5'],
            'commentaire' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $detail = $reservation->details->first();

        AvisCoiffure::query()->create([
            'coiffure_id' => $detail?->coiffure_id,
            'client_id' => $reservation->client_id,
            'reservation_id' => $reservation->id,
            'nom_client' => $reservation->client->prenom . ' ' . $reservation->client->nom,
            'note' => $data['note'],
            'commentaire' => $data['commentaire'],
            'verifie' => true,
            'statut' => 'en_attente',
        ]);

        // Consomme le token pour qu il ne puisse pas etre rejoue.
        $reservation->forceFill([
            'review_token' => null,
            'review_token_expires_at' => null,
        ])->save();

        return response()->json(['message' => 'Merci pour votre avis ! Il sera visible après modération.'], 201);
    }

    private function findByToken(string $token): ?Reservation
    {
        return Reservation::query()
            ->with(['client', 'details'])
            ->where('review_token', hash('sha256', $token))
            ->where('review_token_expires_at', '>', now())
            ->where('statut', 'terminee')
            ->first();
    }
}
