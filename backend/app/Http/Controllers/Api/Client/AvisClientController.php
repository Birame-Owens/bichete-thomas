<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\AvisClient;
use App\Models\Client;
use App\Models\Commande;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AvisClientController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        try {
            $client = $this->getAuthenticatedClient();

            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous devez être connecté pour laisser un avis.',
                ], 401);
            }

            $validated = $request->validate([
                'commande_id' => ['required', 'integer', 'exists:commandes,id'],
                'produit_id' => ['required', 'integer', 'exists:produits,id'],
                'note_globale' => ['required', 'integer', 'min:1', 'max:5'],
                'commentaire' => ['required', 'string', 'min:10', 'max:1500'],
                'titre' => ['nullable', 'string', 'max:120'],
                'nom_affiche' => ['nullable', 'string', 'max:120'],
                'recommande_produit' => ['nullable', 'boolean'],
                'recommande_boutique' => ['nullable', 'boolean'],
                'photos' => ['nullable', 'array', 'max:3'],
                'photos.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            ]);

            $commande = Commande::where('id', $validated['commande_id'])
                ->where('client_id', $client->id)
                ->with('articles_commandes')
                ->first();

            if (!$commande) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commande introuvable pour ce compte.',
                ], 404);
            }

            if (!in_array($commande->statut, ['confirmee', 'en_preparation', 'prete', 'en_livraison', 'livree'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous pourrez laisser un avis dès que la commande sera confirmée.',
                ], 403);
            }

            $produitDansCommande = $commande->articles_commandes
                ->contains('produit_id', (int) $validated['produit_id']);

            if (!$produitDansCommande) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce produit ne fait pas partie de cette commande.',
                ], 422);
            }

            $existeDeja = AvisClient::where('client_id', $client->id)
                ->where('commande_id', $commande->id)
                ->where('produit_id', $validated['produit_id'])
                ->exists();

            if ($existeDeja) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous avez déjà laissé un avis pour ce produit dans cette commande.',
                ], 409);
            }

            // Photos jointes (optionnelles) — stockées sur le disque public sous avis/
            $photosPaths = [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $photosPaths[] = $photo->store('avis', 'public');
                }
            }

            $avis = AvisClient::create([
                'client_id' => $client->id,
                'commande_id' => $commande->id,
                'produit_id' => $validated['produit_id'],
                'titre' => $validated['titre'] ?? null,
                'commentaire' => $validated['commentaire'],
                'note_globale' => $validated['note_globale'],
                'nom_affiche' => $validated['nom_affiche'] ?? $client->prenom,
                'recommande_produit' => $request->boolean('recommande_produit', true),
                'recommande_boutique' => $request->boolean('recommande_boutique', true),
                'photos_avis' => $photosPaths ?: null,
                'statut' => 'en_attente',
                'est_visible' => false,
                'avis_verifie' => true,
                'adresse_ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Merci pour votre avis. Il sera publié après validation par la boutique.',
                'data' => [
                    'avis' => [
                        'id' => $avis->id,
                        'statut' => $avis->statut,
                        'produit_id' => $avis->produit_id,
                    ],
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur création avis client', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l’envoi de votre avis.',
            ], 500);
        }
    }

    private function getAuthenticatedClient(): ?Client
    {
        $user = Auth::user();

        if (!$user) {
            return null;
        }

        return Client::where('user_id', $user->id)->first();
    }
}
