<?php

namespace App\Services\Client;

use App\Models\Commande;
use App\Models\Paiement;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class NexPayService
{
    private string $apiUrl;
    private string $writeKey;
    private string $readKey;
    private string $projectId;

    public function __construct()
    {
        $this->apiUrl = config('services.nexpay.api_url');
        $this->writeKey = config('services.nexpay.write_key');
        $this->readKey = config('services.nexpay.read_key');
        $this->projectId = config('services.nexpay.project_id');

        if (!$this->writeKey || !$this->readKey || !$this->projectId) {
            throw new Exception('NexPay credentials not configured');
        }
    }

    /**
     * Créer une session de paiement NexPay
     * 
     * @param Commande $commande
     * @param string $provider 'wave' ou 'orange_money'
     * @return array
     */
    public function createPaymentSession(Commande $commande, string $provider = 'wave'): array
    {
        $client = $commande->client;
        
        $payload = [
            'amount' => (int) $commande->montant_total,
            'userId' => $commande->client_id ?? 'guest',
            'name' => $client->nom ?? 'Client',
            'phone' => $client->telephone,
            'email' => $client->email,
            'client_reference' => $commande->numero_commande,
            'projectId' => $this->projectId,
            'currency' => 'XOF',
            'metadata' => [
                'commande_id' => $commande->id,
                'numero_commande' => $commande->numero_commande,
                'provider' => $provider,
            ],
            'successUrl' => config('services.frontend_url') . "/checkout/success?order={$commande->numero_commande}",
            'provider' => $provider === 'orange_money' ? 'om' : 'wave',
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->writeKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->apiUrl}/payment/initiate", $payload);

            if (!$response->successful()) {
                Log::error('NexPay payment initiation failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new Exception('Payment initiation failed: ' . $response->body());
            }

            $data = $response->json();

            // Log la réponse pour débogage
            Log::info('NexPay payment initiated', [
                'commande' => $commande->numero_commande,
                'provider' => $provider,
                'response' => $data,
            ]);

            return $data;

        } catch (Exception $e) {
            Log::error('NexPay API error', [
                'message' => $e->getMessage(),
                'commande' => $commande->numero_commande,
            ]);
            throw $e;
        }
    }

    /**
     * Vérifier le statut d'un paiement (Long Polling)
     * 
     * @param string $sessionId
     * @return array
     */
    public function checkPaymentStatus(string $sessionId): array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->readKey,
            ])->timeout(65) // Long polling jusqu'à 60s
              ->get("{$this->apiUrl}/payment/session/{$sessionId}/status");

            if (!$response->successful()) {
                throw new Exception('Payment status check failed');
            }

            return $response->json();

        } catch (Exception $e) {
            Log::error('NexPay status check error', [
                'session_id' => $sessionId,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Récupérer les détails d'une session
     * 
     * @param string $sessionId
     * @return array
     */
    public function getSessionDetails(string $sessionId): array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->readKey,
            ])->get("{$this->apiUrl}/payment/session/{$sessionId}");

            if (!$response->successful()) {
                throw new Exception('Session details fetch failed');
            }

            return $response->json();

        } catch (Exception $e) {
            Log::error('NexPay session details error', [
                'session_id' => $sessionId,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Traiter un événement webhook NexPay
     * 
     * @param array $payload
     * @return bool
     */
    public function handleWebhook(array $payload): bool
    {
        try {
            $type = $payload['type'] ?? null;
            $data = $payload['data'] ?? [];

            Log::info('NexPay webhook received', [
                'type' => $type,
                'reference' => $data['client_reference'] ?? 'unknown',
            ]);

            switch ($type) {
                case 'payment.succeeded':
                    return $this->handlePaymentSucceeded($data);

                case 'payment.failed':
                    return $this->handlePaymentFailed($data);

                case 'payment.cancelled':
                    return $this->handlePaymentCancelled($data);

                default:
                    Log::warning('Unknown NexPay webhook type', ['type' => $type]);
                    return false;
            }

        } catch (Exception $e) {
            Log::error('NexPay webhook handling error', [
                'message' => $e->getMessage(),
                'payload' => $payload,
            ]);
            return false;
        }
    }

    /**
     * Gérer un paiement réussi
     */
    private function handlePaymentSucceeded(array $data): bool
    {
        $numeroCommande = $data['client_reference'] ?? null;
        
        if (!$numeroCommande) {
            Log::error('NexPay webhook: missing client_reference');
            return false;
        }

        $commande = Commande::where('numero_commande', $numeroCommande)->first();

        if (!$commande) {
            Log::error('NexPay webhook: commande not found', ['numero' => $numeroCommande]);
            return false;
        }

        // Créer ou mettre à jour le paiement
        $paiement = Paiement::updateOrCreate(
            ['commande_id' => $commande->id],
            [
                'client_id' => $commande->client_id,
                'montant' => $data['amount'] ?? $commande->montant_total,
                'methode_paiement' => $data['provider']['code'] === 'wave' ? 'wave' : 'orange_money',
                'statut' => 'valide',
                'date_paiement' => now(),
                'reference_paiement' => $data['client_reference'],
                'transaction_id' => $data['provider']['transaction_id'] ?? null,
                'details_paiement' => $data,
            ]
        );

        // Confirmer la commande
        $commande->update([
            'statut' => 'confirmee',
            'date_confirmation' => now(),
        ]);

        Log::info('NexPay payment confirmed', [
            'commande' => $numeroCommande,
            'paiement_id' => $paiement->id,
        ]);

        // TODO: Dispatcher les jobs (email, facture, etc.)
        // dispatch(new GenerateInvoicePdfJob($commande));
        // dispatch(new SendOrderConfirmationEmailJob($commande));

        return true;
    }

    /**
     * Gérer un paiement échoué
     */
    private function handlePaymentFailed(array $data): bool
    {
        $numeroCommande = $data['client_reference'] ?? null;

        if (!$numeroCommande) {
            return false;
        }

        $commande = Commande::where('numero_commande', $numeroCommande)->first();

        if ($commande) {
            Paiement::updateOrCreate(
                ['commande_id' => $commande->id],
                [
                    'statut' => 'echoue',
                    'details_paiement' => $data,
                ]
            );

            $commande->update(['statut' => 'annulee']);

            Log::warning('NexPay payment failed', ['commande' => $numeroCommande]);
        }

        return true;
    }

    /**
     * Gérer un paiement annulé
     */
    private function handlePaymentCancelled(array $data): bool
    {
        $numeroCommande = $data['client_reference'] ?? null;

        if (!$numeroCommande) {
            return false;
        }

        $commande = Commande::where('numero_commande', $numeroCommande)->first();

        if ($commande) {
            Paiement::updateOrCreate(
                ['commande_id' => $commande->id],
                [
                    'statut' => 'annule',
                    'details_paiement' => $data,
                ]
            );

            $commande->update(['statut' => 'annulee']);

            Log::info('NexPay payment cancelled', ['commande' => $numeroCommande]);
        }

        return true;
    }
}
