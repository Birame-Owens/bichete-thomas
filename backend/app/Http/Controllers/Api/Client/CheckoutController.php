<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Services\Client\CheckoutService;
use App\Services\Client\NabooPayService;
use App\Models\Commande;
use App\Models\Paiement;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Exception;

class CheckoutController extends Controller
{
    protected $checkoutService;

    public function __construct(CheckoutService $checkoutService)
    {
        $this->checkoutService = $checkoutService;
    }

    /**
     * Créer une commande (guest ou authentifié)
     */
    public function createOrder(Request $request)
    {
        try {
            $result = $this->checkoutService->createOrder(
                $request->all(),
                $request->header('Idempotency-Key')
            );

            return response()->json($result, ($result['idempotent_replay'] ?? false) ? 200 : 201);

        } catch (Exception $e) {
            \Log::error('❌ CheckoutController@createOrder - Erreur', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // Distinguer erreurs métier (400) des erreurs techniques (500)
            $isBusinessError = str_contains($e->getMessage(), 'Un compte existe déjà') 
                            || str_contains($e->getMessage(), 'email') 
                            || str_contains($e->getMessage(), 'stock')
                            || str_contains($e->getMessage(), 'stock')
                            || str_contains($e->getMessage(), 'téléphone')
                            || str_contains($e->getMessage(), 'connecter')
                            || str_contains($e->getMessage(), 'existe déjà');

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'type' => $isBusinessError ? 'validation' : 'server_error',
                'status' => $isBusinessError ? 400 : 500
            ], $isBusinessError ? 400 : 500);
        }
    }

    /**
     * Initier le paiement
     */
    public function initiatePayment(Request $request, $orderNumber)
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|in:card,carte_bancaire,wave,orange_money',
            'phone' => 'required_if:provider,card,carte_bancaire,wave,orange_money|string',
            'first_name' => 'nullable|string',
            'last_name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = Commande::where('numero_commande', $orderNumber);
            if (auth()->check() && ($client = auth()->user()->client)) {
                $query->where('client_id', $client->id);
            }
            $commande = $query->firstOrFail();

            if ($commande->statut !== 'en_attente') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette commande a déjà été traitée'
                ], 400);
            }

            $result = $this->checkoutService->initiatePayment(
                $commande,
                $request->provider,
                $request->only(['phone', 'first_name', 'last_name']),
                $request->header('Idempotency-Key')
            );

            return response()->json($result);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Page de succès après paiement
     */
    public function success(Request $request)
    {
        try {
            $orderNumber = $request->query('order');
            $sessionId = $request->query('session_id');

            if (!$orderNumber) {
                return response()->json([
                    'success' => false,
                    'message' => 'Numéro de commande manquant'
                ], 400);
            }
            $this->syncNabooPayStatus($orderNumber);

            $commande = Commande::where('numero_commande', $orderNumber)
                ->with([
                    'articles_commandes.produit.images_produits',
                    'client',
                    'paiements'
                ])
                ->firstOrFail();

            // Formatter les articles pour le frontend
            $commande->articles = $commande->articles_commandes->map(function ($article) {
                return [
                    'id' => $article->id,
                    'nom_produit' => $article->nom_produit,
                    'description_produit' => $article->description_produit,
                    'prix_unitaire' => $article->prix_unitaire,
                    'quantite' => $article->quantite,
                    'prix_total_article' => $article->prix_total_article,
                    'taille_choisie' => $article->taille_choisie,
                    'couleur_choisie' => $article->couleur_choisie,
                    'produit' => $article->produit
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'commande' => $commande,
                    'message' => 'Votre commande a été enregistrée avec succès!'
                ]
            ]);

        } catch (Exception $e) {
            \Log::error('Erreur page success', [
                'order' => $request->query('order'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Page d'annulation de paiement
     */
    public function cancel(Request $request)
    {
        try {
            $orderNumber = $request->query('order');

            if (!$orderNumber) {
                return response()->json([
                    'success' => false,
                    'message' => 'Numéro de commande manquant'
                ], 400);
            }

            $query = Commande::where('numero_commande', $orderNumber);
            if (auth()->check() && ($client = auth()->user()->client)) {
                $query->where('client_id', $client->id);
            }
            $commande = $query->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => [
                    'commande' => $commande,
                    'message' => 'Le paiement a été annulé. Vous pouvez réessayer quand vous voulez.'
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Récupérer les détails d'une commande
     */
    public function getOrder($orderNumber)
    {
        try {
            $this->syncNabooPayStatus($orderNumber);

            $query = Commande::where('numero_commande', $orderNumber);
            if (auth()->check() && ($client = auth()->user()->client)) {
                $query->where('client_id', $client->id);
            }
            $commande = $query
                ->with(['articles.produit.images_produits', 'client', 'paiements'])
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $commande
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }
    }

    /**
     * Récupérer les commandes d'un utilisateur (si connecté)
     */
    public function getUserOrders(Request $request)
    {
        try {
            if (!auth()->check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous devez être connecté'
                ], 401);
            }

            $user = auth()->user();
            $client = $user->client;

            if (!$client) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            $commandes = Commande::where('client_id', $client->id)
                ->with(['articles.produit.images_produits', 'paiements'])
                ->orderBy('date_commande', 'desc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'data' => $commandes
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Récupérer une commande par son numéro (pour page success)
     */
    public function getOrderByNumber($orderNumber)
    {
        try {
            $this->syncNabooPayStatus($orderNumber);

            \Log::info('🔍 getOrderByNumber appelé', ['numero_commande' => $orderNumber]);

            $query = Commande::where('numero_commande', $orderNumber);
            if (auth()->check() && ($client = auth()->user()->client)) {
                $query->where('client_id', $client->id);
            }
            $commande = $query
                ->with(['articles.produit.images_produits', 'client', 'paiements'])
                ->firstOrFail();

            \Log::info('✅ Commande trouvée', [
                'id' => $commande->id,
                'numero' => $commande->numero_commande,
                'nb_articles' => $commande->articles->count(),
                'client' => $commande->client ? $commande->client->email : 'N/A'
            ]);

            return response()->json([
                'success' => true,
                'data' => $commande
            ]);

        } catch (Exception $e) {
            \Log::error('❌ getOrderByNumber erreur', [
                'numero_commande' => $orderNumber,
                'message' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }
    }

    /**
     * Valider un code promo (endpoint public)
     */
    public function validateCoupon(Request $request)
    {
        $request->validate([
            'code'             => 'required|string',
            'montant_commande' => 'required|numeric|min:0',
        ]);

        $promotion = Promotion::whereRaw('lower(code) = ?', [strtolower(trim($request->code))])
            ->where('est_active', true)
            ->where('date_debut', '<=', now())
            ->where('date_fin', '>=', now())
            ->first();

        if (!$promotion) {
            return response()->json(['success' => false, 'message' => 'Code promo invalide ou expiré'], 404);
        }

        $montant = (float) $request->montant_commande;

        if ($promotion->montant_minimum && $montant < $promotion->montant_minimum) {
            return response()->json([
                'success' => false,
                'message' => 'Montant minimum requis : ' . number_format($promotion->montant_minimum, 0, ',', ' ') . ' F',
            ], 400);
        }

        $discount = 0;
        if ($promotion->type_promotion === 'pourcentage') {
            $discount = ($montant * $promotion->valeur) / 100;
            if ($promotion->reduction_maximum) {
                $discount = min($discount, $promotion->reduction_maximum);
            }
        } elseif ($promotion->type_promotion === 'montant_fixe') {
            $discount = min($promotion->valeur, $montant);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'code'         => strtoupper($promotion->code),
                'nom'          => $promotion->nom,
                'type'         => $promotion->type_promotion,
                'valeur'       => $promotion->valeur,
                'discount'     => round($discount),
                'nouveau_total' => round($montant - $discount),
            ],
        ]);
    }

    private function syncNabooPayStatus(string $orderNumber): void
    {
        try {
            $commande = Commande::where('numero_commande', $orderNumber)
                ->with('paiements')
                ->first();

            if (!$commande || in_array($commande->statut, ['confirmee', 'en_preparation', 'prete', 'en_livraison', 'livree'], true)) {
                return;
            }

            $paiement = $commande->paiements()
                ->whereIn('methode_paiement', ['wave', 'orange_money', 'carte_bancaire'])
                ->latest()
                ->first();

            if (!$paiement) {
                return;
            }

            $payload = app(NabooPayService::class)->fetchTransaction($paiement->transaction_id ?: $orderNumber);
            app(NabooPayService::class)->handleTransactionStatus($orderNumber, $payload);
        } catch (Exception $e) {
            Log::warning('Synchronisation NabooPay impossible', [
                'order' => $orderNumber,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
