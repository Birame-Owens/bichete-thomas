<?php

namespace App\Services\Client;

use App\Models\Commande;
use App\Models\Paiement;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NabooPayService
{
    private string $baseUrl;
    private ?string $apiKey;
    private ?string $webhookSecret;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.naboopay.base_url', 'https://api.naboopay.com'), '/');
        $this->apiKey = config('services.naboopay.api_key');
        $this->webhookSecret = config('services.naboopay.webhook_secret');
    }

    public function createTransaction(Commande $commande, Paiement $paiement, string $provider, array $data = []): array
    {
        if (blank($this->apiKey)) {
            throw new Exception('NabooPay API key is not configured.');
        }

        $commande->loadMissing(['articles_commandes.produit', 'client']);

        $rawPhone = $data['phone'] ?? $commande->telephone_livraison ?? $commande->client?->telephone;
        $normalizedPhone = $this->normalizePhone($rawPhone);
        $nameParts = $this->splitName($data['first_name'] ?? null, $data['last_name'] ?? null, $commande->nom_destinataire);
        $customer = array_filter([
            'first_name' => $nameParts['first_name'],
            'last_name' => $nameParts['last_name'],
            'phone' => $normalizedPhone,
        ], static fn ($value) => !blank($value));

        $payload = [
            'order_id' => $commande->numero_commande,
            'method_of_payment' => [$this->mapProvider($provider)],
            'products' => $this->buildProducts($commande),
            'success_url' => $this->frontendUrl("/checkout/success?order={$commande->numero_commande}"),
            'error_url' => $this->frontendUrl("/checkout/cancel?order={$commande->numero_commande}"),
            'fees_customer_side' => false,
            'is_escrow' => false,
            'is_merchant' => false,
            'customer' => $customer,
            'metadata' => [
                'commande_id' => $commande->id,
                'paiement_id' => $paiement->id,
                'reference_paiement' => $paiement->reference_paiement,
            ],
        ];

        try {
            Log::info('NabooPay transaction request', [
                'order_id' => $commande->numero_commande,
                'montant_total' => $commande->montant_total,
                'method_of_payment' => $payload['method_of_payment'],
                'has_customer_phone' => !blank($customer['phone'] ?? null),
                'products' => $payload['products'],
            ]);

            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('services.naboopay.timeout', 15))
                ->retry(2, 250, throw: false)
                ->post("{$this->baseUrl}/api/v2/transactions", $payload);
        } catch (ConnectionException $e) {
            throw new Exception('Impossible de joindre NabooPay. Veuillez reessayer.', previous: $e);
        }

        $body = $response->json() ?? [];

        if (!$response->successful()) {
            Log::warning('NabooPay transaction failed', [
                'order_id' => $commande->numero_commande,
                'status' => $response->status(),
                'request_products' => $payload['products'],
                'response' => $body,
            ]);

            $message = $body['error'] ?? $body['message'] ?? 'Erreur NabooPay';
            throw new Exception($message);
        }

        $checkoutUrl = $body['checkout_url']
            ?? $body['payment_url']
            ?? $body['redirect_url']
            ?? $body['url']
            ?? data_get($body, 'data.checkout_url')
            ?? data_get($body, 'data.payment_url')
            ?? data_get($body, 'data.redirect_url');

        if (blank($checkoutUrl)) {
            throw new Exception('NabooPay n\'a pas retourne d\'URL de paiement.');
        }

        $paiement->update([
            'transaction_id' => $body['order_id'] ?? data_get($body, 'data.order_id') ?? $commande->numero_commande,
            'numero_telephone' => $data['phone'] ?? null,
            'donnees_api' => json_encode($body),
            'message_retour' => 'Transaction NabooPay initiee',
            'date_initiation' => now(),
            'statut' => 'en_cours',
        ]);

        return [
            'success' => true,
            'payment_url' => $checkoutUrl,
            'transaction_id' => $paiement->transaction_id,
            'provider' => 'naboopay',
        ];
    }

    public function fetchTransaction(string $orderId): array
    {
        if (blank($this->apiKey)) {
            throw new Exception('NabooPay API key is not configured.');
        }

        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout((int) config('services.naboopay.timeout', 15))
            ->retry(2, 250, throw: false)
            ->get("{$this->baseUrl}/api/v2/transactions/{$orderId}");

        $body = $response->json() ?? [];

        if (!$response->successful()) {
            throw new Exception($body['error'] ?? $body['message'] ?? 'Erreur NabooPay');
        }

        return $body;
    }

    public function verifySignature(string $rawPayload, ?string $signature, array $payload = []): bool
    {
        if (blank($this->webhookSecret)) {
            return false;
        }

        if (blank($signature)) {
            return false;
        }

        $expectedRaw = hash_hmac('sha256', $rawPayload, $this->webhookSecret);
        if (hash_equals($expectedRaw, $signature)) {
            return true;
        }

        $encoded = json_encode($payload);
        $expectedEncoded = hash_hmac('sha256', $encoded ?: '', $this->webhookSecret);

        return hash_equals($expectedEncoded, $signature);
    }

    public function handleTransactionStatus(string $orderId, array $payload): bool
    {
        $status = strtolower((string) (
            $payload['transaction_status']
            ?? $payload['payment_status']
            ?? $payload['status']
            ?? data_get($payload, 'data.transaction_status')
            ?? data_get($payload, 'data.status')
            ?? 'pending'
        ));

        $paiement = Paiement::where('transaction_id', $orderId)
            ->orWhereHas('commande', fn ($query) => $query->where('numero_commande', $orderId))
            ->latest()
            ->first();

        if (!$paiement) {
            Log::warning('NabooPay webhook: paiement introuvable', ['order_id' => $orderId]);
            return false;
        }

        $paiement->update([
            'donnees_api' => json_encode($payload),
            'message_retour' => "NabooPay status: {$status}",
        ]);

        if (in_array($status, ['paid', 'success', 'successful', 'completed', 'valide'], true)) {
            app(CheckoutService::class)->confirmPayment($paiement);
            return true;
        }

        if (in_array($status, ['cancel', 'cancelled', 'canceled', 'failed', 'echoue', 'annule'], true)) {
            $paiement->update([
                'statut' => str_contains($status, 'cancel') || $status === 'annule' ? 'annule' : 'echoue',
            ]);

            $paiement->commande?->update(['statut' => 'echoue']);
            return true;
        }

        $paiement->update(['statut' => 'en_cours']);
        return true;
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (blank($phone)) {
            return null;
        }

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        if (str_starts_with($phone, '221')) {
            return '+' . $phone;
        }

        return '+221' . ltrim($phone, '0');
    }

    private function buildProducts(Commande $commande): array
    {
        $amount = (int) round((float) $commande->montant_total);

        if ($amount <= 10) {
            throw new Exception('Le montant total de la commande doit etre superieur a 10 FCFA.');
        }

        return [[
            'name' => "Commande {$commande->numero_commande}",
            'price' => $amount,
            'quantity' => 1,
            'description' => 'Commande NDEYA SHOP',
        ]];
    }

    private function splitName(?string $firstName, ?string $lastName, ?string $fallback): array
    {
        $firstName = trim((string) $firstName);
        $lastName = trim((string) $lastName);

        if ($firstName !== '' || $lastName !== '') {
            return [
                'first_name' => $firstName ?: null,
                'last_name' => $lastName ?: null,
            ];
        }

        $fallback = trim((string) $fallback);
        if ($fallback === '') {
            return ['first_name' => null, 'last_name' => null];
        }

        $parts = preg_split('/\s+/', $fallback);
        $first = array_shift($parts);
        $last = $parts ? implode(' ', $parts) : null;

        return [
            'first_name' => $first ?: null,
            'last_name' => $last,
        ];
    }

    private function mapProvider(string $provider): string
    {
        return match ($provider) {
            'wave' => config('services.naboopay.methods.wave', 'wave'),
            'orange_money' => config('services.naboopay.methods.orange_money', 'orange_money'),
            'card', 'carte_bancaire' => config('services.naboopay.methods.card', 'card'),
            default => throw new Exception("Provider de paiement non supporte: {$provider}"),
        };
    }

    private function frontendUrl(string $path): string
    {
        return rtrim(config('services.frontend_url', config('app.url')), '/') . $path;
    }
}
