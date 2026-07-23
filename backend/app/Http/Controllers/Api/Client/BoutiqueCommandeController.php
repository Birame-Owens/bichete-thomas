<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Commande;
use App\Models\Paiement;
use App\Models\Produit;
use App\Services\Admin\CommandeService;
use App\Services\ClientResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Checkout public de la boutique (phase 2 ecommerce).
 *
 * Meme circuit de paiement que les reservations : Paiement en_attente +
 * session NabooPay (Wave / Orange Money), confirme par le retour client
 * signe et/ou le webhook (PaymentController::naboopay*), qui passe la
 * commande en "confirmee". Le paiement a la livraison cree simplement la
 * commande en attente, reglee a la remise du colis.
 */
class BoutiqueCommandeController extends Controller
{
    public function __construct(
        private ClientResolver $clients,
        private CommandeService $commandes,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client.prenom' => ['required', 'string', 'max:100'],
            'client.nom' => ['required', 'string', 'max:100'],
            'client.telephone' => ['required', 'string', 'max:30'],
            'client.email' => ['nullable', 'email', 'max:255'],
            'mode_livraison' => ['required', 'in:domicile,boutique'],
            'adresse_livraison' => ['required_if:mode_livraison,domicile', 'nullable', 'string', 'max:500'],
            'instructions_livraison' => ['nullable', 'string', 'max:500'],
            'mode_paiement' => ['required', 'in:wave,orange_money,livraison'],
            'articles' => ['required', 'array', 'min:1', 'max:30'],
            'articles.*.produit_id' => ['required', 'integer', 'exists:produits,id'],
            'articles.*.quantite' => ['required', 'integer', 'min:1', 'max:20'],
            'articles.*.couleur' => ['nullable', 'string', 'max:50'],
            'articles.*.taille' => ['nullable', 'string', 'max:50'],
            'success_url' => ['nullable', 'url', 'max:500'],
            'cancel_url' => ['nullable', 'url', 'max:500'],
        ]);

        $client = $this->clients->findOrCreate(
            [
                'prenom' => $data['client']['prenom'],
                'nom' => $data['client']['nom'],
                'telephone' => $data['client']['telephone'],
                'email' => $data['client']['email'] ?? null,
            ],
            defaultSource: 'en_ligne',
        );

        // Prix TOUJOURS recalcules cote serveur depuis la base (jamais du client).
        $produits = Produit::with('category')
            ->whereIn('id', collect($data['articles'])->pluck('produit_id'))
            ->where('est_visible', true)
            ->whereHas('category', fn ($q) => $q->where('est_active', true))
            ->get()
            ->keyBy('id');

        $lignes = [];
        $sousTotal = 0.0;

        foreach ($data['articles'] as $article) {
            $produit = $produits->get($article['produit_id']);

            if (! $produit) {
                throw ValidationException::withMessages([
                    'articles' => 'Un des produits de votre panier n est plus disponible.',
                ]);
            }

            $prixUnitaire = $this->prixActuel($produit);
            $total = round($prixUnitaire * $article['quantite'], 2);
            $sousTotal += $total;

            $lignes[] = [
                'produit' => $produit,
                'quantite' => $article['quantite'],
                'prix_unitaire' => $prixUnitaire,
                'prix_total' => $total,
                'couleur' => $article['couleur'] ?? null,
                'taille' => $article['taille'] ?? null,
            ];
        }

        $fraisLivraison = $data['mode_livraison'] === 'boutique' ? 0.0 : 2000.0;
        $montantTotal = round($sousTotal + $fraisLivraison, 2);

        $result = DB::transaction(function () use ($data, $client, $lignes, $sousTotal, $fraisLivraison, $montantTotal) {
            $commande = Commande::create([
                'numero_commande' => $this->commandes->generateNumeroCommande(),
                'client_id' => $client->id,
                'sous_total' => round($sousTotal, 2),
                'frais_livraison' => $fraisLivraison,
                'remise' => 0,
                'montant_tva' => 0,
                'montant_total' => $montantTotal,
                'statut' => 'en_attente',
                'adresse_livraison' => $data['mode_livraison'] === 'boutique'
                    ? 'Retrait au salon Bichette Thomas'
                    : (string) $data['adresse_livraison'],
                'telephone_livraison' => $client->telephone,
                'nom_destinataire' => trim($client->prenom . ' ' . $client->nom),
                'instructions_livraison' => $data['instructions_livraison'] ?? null,
                'mode_livraison' => $data['mode_livraison'] === 'boutique' ? 'boutique' : 'domicile',
                'source' => 'site_web',
                'notes_client' => $data['mode_paiement'] === 'livraison'
                    ? 'Paiement a la livraison choisi par le client.'
                    : null,
            ]);

            foreach ($lignes as $ligne) {
                $commande->articles_commandes()->create([
                    'produit_id' => $ligne['produit']->id,
                    'nom_produit' => $ligne['produit']->nom,
                    'prix_unitaire' => $ligne['prix_unitaire'],
                    'quantite' => $ligne['quantite'],
                    'prix_total_article' => $ligne['prix_total'],
                    'taille_choisie' => $ligne['taille'],
                    'couleur_choisie' => $ligne['couleur'],
                    'statut_production' => 'en_attente',
                ]);
            }

            $payment = null;

            if (in_array($data['mode_paiement'], ['wave', 'orange_money'], true)) {
                $payment = Paiement::create([
                    'commande_id' => $commande->id,
                    'client_id' => $client->id,
                    'numero_recu' => 'TEMP-' . Str::uuid()->toString(),
                    'type' => 'complet',
                    'mode_paiement' => $data['mode_paiement'],
                    'montant' => $montantTotal,
                    'devise' => 'FCFA',
                    'statut' => 'en_attente',
                    'date_paiement' => now(),
                    'notes' => 'Paiement boutique en ligne initie via NabooPay.',
                ]);
            }

            return ['commande' => $commande, 'payment' => $payment];
        });

        /** @var Commande $commande */
        $commande = $result['commande'];
        /** @var Paiement|null $payment */
        $payment = $result['payment'];

        $checkoutUrl = null;

        if ($payment) {
            $checkoutUrl = $this->createNaboopayCheckoutSession($payment, $commande, $client, $data, $lignes);
        }

        Log::info('Commande boutique creee', [
            'commande_id' => $commande->id,
            'numero' => $commande->numero_commande,
            'montant' => $montantTotal,
            'mode_paiement' => $data['mode_paiement'],
        ]);

        return response()->json([
            'message' => $payment
                ? 'Commande creee. Continuez vers NabooPay pour payer.'
                : 'Commande enregistree ! Le salon vous contactera pour la livraison.',
            'data' => [
                'numero_commande' => $commande->numero_commande,
                'montant_total' => $montantTotal,
                'checkout_url' => $checkoutUrl,
                'requires_redirect' => $checkoutUrl !== null,
            ],
        ], 201);
    }

    private function prixActuel(Produit $produit): float
    {
        if ($produit->prix_promo === null) {
            return (float) $produit->prix;
        }

        $now = now();

        if ($produit->debut_promo && $now->lt($produit->debut_promo)) {
            return (float) $produit->prix;
        }

        if ($produit->fin_promo && $now->gt($produit->fin_promo)) {
            return (float) $produit->prix;
        }

        return (float) $produit->prix_promo;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lignes
     */
    private function createNaboopayCheckoutSession(
        Paiement $payment,
        Commande $commande,
        Client $client,
        array $data,
        array $lignes,
    ): string {
        $apiKey = config('services.naboopay.api_key');

        if (! $apiKey) {
            throw ValidationException::withMessages([
                'mode_paiement' => 'Le paiement en ligne n est pas encore configure. Choisissez le paiement a la livraison.',
            ]);
        }

        $successUrl = $this->appendQuery(
            $data['success_url'] ?? config('app.url') . '/boutique/commande-confirmee?paiement=naboopay_success',
            [
                'paiement_id' => $payment->id,
                'signature' => $this->naboopayReturnSignature($payment->id),
                'commande' => $commande->numero_commande,
            ]
        );
        $errorUrl = $this->appendQuery(
            $data['cancel_url'] ?? config('app.url') . '/boutique/commande-confirmee?paiement=naboopay_cancel',
            ['paiement_id' => $payment->id, 'commande' => $commande->numero_commande]
        );

        $products = array_map(fn (array $ligne): array => [
            'name' => (string) $ligne['produit']->nom,
            'description' => 'Boutique Bichette Thomas',
            'amount' => (int) round((float) $ligne['prix_unitaire']),
            'price' => (int) round((float) $ligne['prix_unitaire']),
            'quantity' => (int) $ligne['quantite'],
        ], $lignes);

        if ((float) $commande->frais_livraison > 0) {
            $products[] = [
                'name' => 'Livraison',
                'description' => 'Frais de livraison Dakar',
                'amount' => (int) round((float) $commande->frais_livraison),
                'price' => (int) round((float) $commande->frais_livraison),
                'quantity' => 1,
            ];
        }

        $payload = [
            'order_id' => 'BT-CMD-' . $payment->id . '-' . Str::upper(Str::random(8)),
            'method_of_payment' => match ($payment->mode_paiement) {
                'wave' => ['wave'],
                'orange_money' => ['orange_money'],
                default => ['wave', 'orange_money'],
            },
            'products' => $products,
            'customer' => [
                'first_name' => $client->prenom,
                'last_name' => $client->nom,
                'phone' => $this->internationalPhone($client->telephone),
                'email' => $client->email,
            ],
            'success_url' => $successUrl,
            'error_url' => $errorUrl,
            'is_escrow' => false,
            'is_merchant' => false,
            'fees_customer_side' => (bool) config('services.naboopay.fees_customer_side', true),
            'metadata' => [
                'commande_id' => $commande->id,
                'paiement_id' => $payment->id,
            ],
        ];

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders(['Authorization' => 'Bearer ' . $apiKey])
                ->post(rtrim((string) config('services.naboopay.base_url', 'https://api.naboopay.com/api/v1'), '/') . '/transaction/create-transaction', $payload);
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                'mode_paiement' => 'NabooPay est momentanement injoignable. Reessayez ou choisissez le paiement a la livraison.',
            ]);
        }

        if (! $response->successful()) {
            Log::error('NabooPay checkout boutique failed', [
                'status' => $response->status(),
                'body' => $response->json(),
                'commande_id' => $commande->id,
            ]);

            throw ValidationException::withMessages([
                'mode_paiement' => 'Le paiement en ligne a echoue. Reessayez ou choisissez le paiement a la livraison.',
            ]);
        }

        $body = $response->json();
        $checkoutUrl = (string) ($body['checkout_url'] ?? '');
        $orderId = (string) ($body['order_id'] ?? $payload['order_id']);

        if ($checkoutUrl === '') {
            throw ValidationException::withMessages([
                'mode_paiement' => 'NabooPay n a pas renvoye de lien de paiement.',
            ]);
        }

        $payment->update(['reference' => $orderId]);

        return $checkoutUrl;
    }

    private function naboopayReturnSignature(int|string $paymentId): string
    {
        return hash_hmac('sha256', 'naboopay-return|' . $paymentId, $this->naboopayReturnSecret());
    }

    private function naboopayReturnSecret(): string
    {
        return (string) config('app.key') . '|' . (string) config('services.naboopay.webhook_secret') . '|' . (string) config('services.naboopay.api_key');
    }

    /**
     * @param  array<string, int|string>  $params
     */
    private function appendQuery(string $url, array $params): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query($params);
    }

    private function internationalPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '221')) {
            return '+' . $digits;
        }

        return '+221' . ltrim($digits, '0');
    }
}
