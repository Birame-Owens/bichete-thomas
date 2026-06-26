<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Services\Client\NexPayService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class NexPayController extends Controller
{
    private NexPayService $nexPayService;

    public function __construct(NexPayService $nexPayService)
    {
        $this->nexPayService = $nexPayService;
    }

    /**
     * Initier un paiement Wave ou Orange Money
     * 
     * POST /api/client/nexpay/initiate
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function initiate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'numero_commande' => 'required|string|exists:commandes,numero_commande',
                'provider' => 'required|string|in:wave,orange_money',
            ]);

            $commande = Commande::where('numero_commande', $validated['numero_commande'])
                ->with('client')
                ->first();

            if (!$commande) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commande non trouvée',
                ], 404);
            }

            // Vérifier que la commande est en attente
            if ($commande->statut !== 'en_attente') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette commande ne peut plus être payée',
                ], 400);
            }

            // Créer la session de paiement NexPay
            $paymentData = $this->nexPayService->createPaymentSession(
                $commande,
                $validated['provider']
            );

            return response()->json([
                'success' => true,
                'message' => 'Paiement initié avec succès',
                'data' => [
                    'payment_url' => $paymentData['data']['wave_launch_url'] ?? $paymentData['data']['payment_url'] ?? null,
                    'qr_code' => $paymentData['data']['qr_code'] ?? null,
                    'session_id' => $paymentData['data']['session_id'] ?? null,
                    'provider' => $validated['provider'],
                ],
            ]);

        } catch (Exception $e) {
            Log::error('NexPay initiation error', [
                'message' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'initialisation du paiement',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Vérifier le statut d'un paiement (Long Polling)
     * 
     * GET /api/client/nexpay/status/{sessionId}
     * 
     * @param string $sessionId
     * @return JsonResponse
     */
    public function checkStatus(string $sessionId): JsonResponse
    {
        try {
            $statusData = $this->nexPayService->checkPaymentStatus($sessionId);

            return response()->json([
                'success' => true,
                'data' => $statusData,
            ]);

        } catch (Exception $e) {
            Log::error('NexPay status check error', [
                'session_id' => $sessionId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification du statut',
            ], 500);
        }
    }

    /**
     * Callback de succès/annulation (redirection depuis NexPay)
     * 
     * GET /api/client/nexpay/callback
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function callback(Request $request): JsonResponse
    {
        try {
            $numeroCommande = $request->query('order');
            $sessionId = $request->query('session_id');

            if (!$numeroCommande) {
                return response()->json([
                    'success' => false,
                    'message' => 'Numéro de commande manquant',
                ], 400);
            }

            $commande = Commande::where('numero_commande', $numeroCommande)
                ->with(['paiement', 'client', 'articles.produit'])
                ->first();

            if (!$commande) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commande non trouvée',
                ], 404);
            }

            // Si on a un session_id, vérifier le statut
            if ($sessionId) {
                try {
                    $statusData = $this->nexPayService->checkPaymentStatus($sessionId);
                    
                    Log::info('NexPay callback status', [
                        'commande' => $numeroCommande,
                        'status' => $statusData['data']['status'] ?? 'unknown',
                    ]);
                } catch (Exception $e) {
                    Log::warning('NexPay callback: status check failed', [
                        'session_id' => $sessionId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'commande' => $commande,
                ],
            ]);

        } catch (Exception $e) {
            Log::error('NexPay callback error', [
                'message' => $e->getMessage(),
                'query' => $request->query(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du traitement du callback',
            ], 500);
        }
    }

    /**
     * Webhook NexPay (événements de paiement)
     * 
     * POST /api/nexpay/webhook
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            // Vérifier le secret webhook
            $webhookSecret = config('services.nexpay.webhook_secret');
            $receivedSecret = $request->header('x-webhook-secret');

            if ($webhookSecret && $receivedSecret !== $webhookSecret) {
                Log::warning('NexPay webhook: invalid secret');
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // Traiter l'événement
            $payload = $request->all();
            $success = $this->nexPayService->handleWebhook($payload);

            if ($success) {
                return response()->json(['success' => true], 200);
            }

            return response()->json(['error' => 'Webhook processing failed'], 500);

        } catch (Exception $e) {
            Log::error('NexPay webhook error', [
                'message' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['error' => 'Internal error'], 500);
        }
    }
}
