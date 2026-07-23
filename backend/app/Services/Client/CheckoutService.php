<?php

namespace App\Services\Client;

use App\Models\Client;
use App\Models\Commande;
use App\Models\ArticlesCommande;
use App\Models\Paiement;
use App\Models\Produit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Exception;

class CheckoutService
{
    private function resolveStockTotal($produit): int
    {
        if ($produit->couleur_tailles_stock) {
            $stockData = json_decode($produit->couleur_tailles_stock, true) ?? [];
            $total = 0;
            foreach ($stockData as $tailles) {
                foreach ($tailles as $qty) {
                    $total += (int) $qty;
                }
            }
            return $total;
        }
        return (int) ($produit->stock_disponible ?? 0);
    }

    private function resolveCheckoutClient(array $customerData): Client
    {
        $email = strtolower(trim($customerData['email']));
        $phone = $this->normalizePhone($customerData['telephone'] ?? '');
        $wantsAccount = (bool) ($customerData['create_account'] ?? false);
        $loggedUser = auth('sanctum')->user() ?? request()->user() ?? Auth::user();
        $temporaryPassword = null;

        $user = $loggedUser ?: \App\Models\User::whereRaw('lower(email) = ?', [$email])->first();

        $client = null;
        if ($loggedUser) {
            $client = $loggedUser->client;
        }

        if (!$client && $user) {
            $client = Client::where('user_id', $user->id)->first();
        }

        if (!$client) {
            $client = Client::whereRaw('lower(email) = ?', [$email])->first();
        }

        if (!$client && $phone) {
            $client = Client::where('telephone', $phone)->first();
        }

        if (!$user && $wantsAccount) {
            $temporaryPassword = Str::random(12);
            $user = \App\Models\User::create([
                'name' => trim($customerData['prenom'] . ' ' . $customerData['nom']),
                'email' => $email,
                'password' => bcrypt($temporaryPassword),
                'email_verified_at' => now(),
                'role' => 'client',
                'statut' => 'actif',
            ]);
        }

        $clientData = [
            'user_id' => $user?->id,
            'nom' => $customerData['nom'],
            'prenom' => $customerData['prenom'],
            'telephone' => $phone ?: $customerData['telephone'],
            'email' => $email,
            'adresse_principale' => $customerData['adresse_livraison'],
            'ville' => $customerData['ville'] ?? 'Dakar',
            'indications_livraison' => $customerData['notes_livraison'] ?? null,
            'type_client' => $client?->type_client ?? 'nouveau',
            'derniere_visite' => now(),
        ];

        if ($client) {
            if ($client->user_id && $user && $client->user_id !== $user->id) {
                unset($clientData['user_id']);
            }

            // Éviter une violation de contrainte unique : si le téléphone ou
            // l'email saisi appartient déjà à un AUTRE client, on ne l'écrase pas
            // sur ce client. L'info de livraison reste stockée sur la commande.
            foreach (['telephone', 'email'] as $uniqueField) {
                if (!empty($clientData[$uniqueField])
                    && $clientData[$uniqueField] !== $client->{$uniqueField}
                    && Client::where($uniqueField, $clientData[$uniqueField])
                        ->where('id', '!=', $client->id)->exists()) {
                    unset($clientData[$uniqueField]);
                }
            }

            $client->update($clientData);
        } else {
            $client = Client::create(array_merge($clientData, [
                'accepte_whatsapp' => true,
                'accepte_email' => true,
                'accepte_sms' => true,
                'accepte_promotions' => true,
                'priorite' => 'normale',
            ]));
        }

        $client->temporary_password = $temporaryPassword;
        $client->is_new_account = $temporaryPassword !== null;
        $client->account_already_exists = $wantsAccount && $user && $temporaryPassword === null;

        if ($temporaryPassword) {
            cache()->put("checkout_account_password:{$client->id}", $temporaryPassword, now()->addDay());
        }

        return $client;
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (!$phone) {
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

    /**
     * Créer une commande (avec ou sans authentification)
     */
    public function createOrder(array $data, ?string $idempotencyKey = null)
    {
        $idempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey);

        if ($idempotencyKey) {
            $existingCommande = Commande::where('idempotency_key', $idempotencyKey)->first();

            if ($existingCommande) {
                return $this->orderResponse($existingCommande, null, true);
            }
        }

        DB::beginTransaction();

        try {
            // 1. Obtenir ou créer le client
            $client = $this->getOrCreateClient($data['customer']);

            // 2. Valider les articles du panier
            $validatedItems = $this->validateCartItems($data['items']);

            // 3. Calculer les totaux
            $totals = $this->calculateTotals($validatedItems, $data['coupon_code'] ?? null, $data['delivery_zone_id'] ?? null);

            // 4. Créer la commande
            $commande = $this->createCommande($client, $data, $totals, $idempotencyKey);

            // 5. Créer les articles de commande
            $this->createOrderItems($commande, $validatedItems);

            // 6. Mettre à jour le stock
            DB::commit();

            return $this->orderResponse($commande, $totals);

        } catch (QueryException $e) {
            DB::rollBack();

            if ($idempotencyKey && $this->isUniqueConstraintViolation($e)) {
                $existingCommande = Commande::where('idempotency_key', $idempotencyKey)->first();

                if ($existingCommande) {
                    return $this->orderResponse($existingCommande, null, true);
                }
            }

            throw $e;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Obtenir client existant ou créer client avec compte User
     * SOLUTION PROFESSIONNELLE: Unicité email + Compte auto-créé avec mot de passe temporaire
     */
    private function getOrCreateClient(array $customerData)
    {
        return $this->resolveCheckoutClient($customerData);
    }

    /**
     * Valider les articles du panier
     */
    private function validateCartItems(array $items)
    {
        $validatedItems = [];

        foreach ($items as $item) {
            $quantity = max(0, (int) ($item['quantity'] ?? 0));

            if ($quantity < 1) {
                throw new Exception('La quantite des articles doit etre superieure a zero.');
            }

            $produit = Produit::with('images_produits')
                ->where('id', $item['product_id'])
                ->where('est_visible', true)
                ->first();

            if (!$produit) {
                throw new Exception("Produit {$item['product_id']} non disponible");
            }

            // Vérifier le stock si gestion activée
            if ($produit->gestion_stock) {
                $couleur = $item['options']['couleur'] ?? null;
                $taille  = $item['options']['taille']  ?? null;

                if ($couleur && $taille && $produit->couleur_tailles_stock) {
                    $stockData     = json_decode($produit->couleur_tailles_stock, true) ?? [];
                    $variantStock  = $stockData[$couleur][$taille] ?? null;
                    if ($variantStock !== null && $variantStock < $quantity) {
                        throw new Exception("Stock insuffisant pour {$produit->nom} ({$couleur} / {$taille}). Disponible: {$variantStock}");
                    }
                } elseif ($this->resolveStockTotal($produit) < $quantity) {
                    throw new Exception("Stock insuffisant pour {$produit->nom}. Disponible: {$this->resolveStockTotal($produit)}");
                }
            }

            $validatedItems[] = [
                'produit' => $produit,
                'quantity' => $quantity,
                'options' => $item['options'] ?? null, // Taille, couleur, etc.
            ];
        }

        return $validatedItems;
    }

    /**
     * Calculer les totaux (subtotal, remise, livraison, total)
     */
    private function calculateTotals(array $items, $couponCode = null, $deliveryZoneId = null)
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $prix = $this->resolveProductUnitPrice($item['produit']);

            $subtotal += $prix * $item['quantity'];
        }

        // Appliquer la remise si coupon valide
        $discount = 0;
        $promotion = null;

        if ($couponCode) {
            $couponCode = strtoupper(trim((string) $couponCode));
            $promotion = \App\Models\Promotion::whereRaw('lower(code) = ?', [strtolower($couponCode)])
                ->where('est_active', true)
                ->where('date_debut', '<=', now())
                ->where('date_fin', '>=', now())
                ->first();

            if ($promotion) {
                // Vérifier le montant minimum
                if ($promotion->montant_minimum && $subtotal < $promotion->montant_minimum) {
                    // Ne pas appliquer la promotion si le montant minimum n'est pas atteint
                    $promotion = null;
                } else {
                    // Calculer la remise selon le type
                    if ($promotion->type_promotion === 'pourcentage') {
                        $discount = ($subtotal * $promotion->valeur) / 100;
                        
                        // Appliquer la réduction maximum si définie
                        if ($promotion->reduction_maximum && $discount > $promotion->reduction_maximum) {
                            $discount = $promotion->reduction_maximum;
                        }
                    } elseif ($promotion->type_promotion === 'montant_fixe') {
                        $discount = min($promotion->valeur, $subtotal);
                    } elseif ($promotion->type_promotion === 'livraison_gratuite') {
                        // La livraison gratuite sera gérée plus bas
                        $discount = 0;
                    }
                }
            }
        }

        // Frais de livraison - Zone sélectionnée ou fallback sur ShippingSetting
        $shippingCost = 0;
        $deliveryZone = null;

        if ($deliveryZoneId) {
            $deliveryZone = \App\Models\DeliveryZone::where('id', $deliveryZoneId)
                ->where('est_active', true)
                ->first();
        }

        if ($deliveryZone) {
            $shippingCost = (float) $deliveryZone->prix;
        } else {
            $shippingSettings = \App\Models\ShippingSetting::getSettings();
            if ($shippingSettings->is_enabled) {
                $freeShippingThreshold = $shippingSettings->free_threshold;
                $shippingCost = ($subtotal - $discount) >= $freeShippingThreshold ? 0 : $shippingSettings->default_cost;
            }
        }

        // Livraison gratuite via promo
        if ($promotion && $promotion->type_promotion === 'livraison_gratuite') {
            $shippingCost = 0;
        }

        $total = $subtotal - $discount + $shippingCost;

        return [
            'subtotal'      => $subtotal,
            'discount'      => $discount,
            'shipping'      => $shippingCost,
            'total'         => $total,
            'promotion'     => $promotion,
            'delivery_zone' => $deliveryZone,
        ];
    }

    /**
     * Créer la commande
     */
    private function createCommande(Client $client, array $data, array $totals, ?string $idempotencyKey = null)
    {
        $nomComplet = trim(($data['customer']['prenom'] ?? '') . ' ' . ($data['customer']['nom'] ?? ''));
        
        return Commande::create([
            'client_id'          => $client->id,
            'numero_commande'    => $this->generateOrderNumber(),
            'idempotency_key'    => $idempotencyKey,
            'statut'             => 'en_attente',
            'sous_total'         => $totals['subtotal'],
            'montant_total'      => $totals['total'],
            'remise'             => $totals['discount'] ?? 0,
            'code_promo'         => $totals['promotion']?->code,
            'frais_livraison'    => $totals['shipping'],
            'delivery_zone_id'   => $totals['delivery_zone']?->id,
            'zone_livraison_nom' => $totals['delivery_zone']?->nom,
            'adresse_livraison'  => $data['customer']['adresse_livraison'],
            'telephone_livraison'=> $data['customer']['telephone'],
            'nom_destinataire'   => $nomComplet ?: 'Client',
            'notes_client'       => $data['notes'] ?? null,
        ]);
    }

    /**
     * Créer les articles de commande
     */
    private function createOrderItems(Commande $commande, array $items)
    {
        foreach ($items as $item) {
            $produit = $item['produit'];
            $prix = $this->resolveProductUnitPrice($produit);

            ArticlesCommande::create([
                'commande_id' => $commande->id,
                'produit_id' => $produit->id,
                'nom_produit' => $produit->nom,
                'description_produit' => $produit->description_courte ?? $produit->description,
                'quantite' => $item['quantity'],
                'prix_unitaire' => $prix,
                'prix_total_article' => $prix * $item['quantity'],
                'taille_choisie' => $item['options']['taille'] ?? null,
                'couleur_choisie' => $item['options']['couleur'] ?? null,
                'options_supplementaires' => !empty($item['options']) ? json_encode($item['options']) : null,
            ]);
        }
    }

    /**
     * Mettre à jour le stock des produits
     */
    private function updateStock(array $items)
    {
        foreach ($items as $item) {
            $produit  = $item['produit'];
            $quantity = $item['quantity'];

            if ($produit->gestion_stock) {
                $couleur = $item['options']['couleur'] ?? null;
                $taille  = $item['options']['taille']  ?? null;

                // Décrémenter le stock par variante si disponible
                if ($couleur && $taille && $produit->couleur_tailles_stock) {
                    $stockData = json_decode($produit->couleur_tailles_stock, true) ?? [];
                    if (isset($stockData[$couleur][$taille])) {
                        $stockData[$couleur][$taille] = max(0, $stockData[$couleur][$taille] - $quantity);
                        $produit->couleur_tailles_stock = json_encode($stockData);
                        $produit->save();
                    }
                } else {
                    $produit->decrement('stock_disponible', $quantity);
                }

                $produit->increment('nombre_ventes', $quantity);
            }
        }
    }

    /**
     * Decrementer le stock seulement quand la commande est confirmee.
     */
    private function decrementConfirmedOrderStock(Commande $commande): void
    {
        if ($commande->stock_decremented_at) {
            return;
        }

        $commande->loadMissing('articles_commandes.produit');

        foreach ($commande->articles_commandes as $article) {
            $produit = $article->produit;

            if ($produit && $produit->gestion_stock) {
                $couleur = $article->couleur_choisie;
                $taille  = $article->taille_choisie;

                if ($couleur && $taille && $produit->couleur_tailles_stock) {
                    $stockData = json_decode($produit->couleur_tailles_stock, true) ?? [];
                    if (isset($stockData[$couleur][$taille])) {
                        $stockData[$couleur][$taille] = max(0, $stockData[$couleur][$taille] - $article->quantite);
                        $produit->couleur_tailles_stock = json_encode($stockData);
                        $produit->save();
                    }
                } else {
                    $produit->decrement('stock_disponible', $article->quantite);
                }

                $produit->increment('nombre_ventes', $article->quantite);
            }
        }

        $commande->update(['stock_decremented_at' => now()]);
    }

    private function updatePromotionStats(Commande $commande): void
    {
        if (blank($commande->code_promo)) {
            return;
        }

        $promotion = \App\Models\Promotion::where('code', $commande->code_promo)->first();
        if (!$promotion) {
            return;
        }

        $promotion->increment('nombre_utilisations');
        $promotion->increment('nombre_commandes');
        $promotion->increment('chiffre_affaires_genere', $commande->montant_total);
    }

    private function resolveProductUnitPrice(Produit $produit): float
    {
        if ($this->isPromoActive($produit)) {
            return (float) $produit->prix_promo;
        }

        return (float) $produit->prix;
    }

    private function isPromoActive(Produit $produit): bool
    {
        if ($produit->prix_promo === null) {
            return false;
        }

        $now = now();

        if ($produit->debut_promo && $now->lt($produit->debut_promo)) {
            return false;
        }

        if ($produit->fin_promo && $now->gt($produit->fin_promo)) {
            return false;
        }

        return true;
    }

    /**
     * Générer un numéro de commande unique
     */
    private function generateOrderNumber()
    {
        $prefix = 'CMD';
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(6));

        return "{$prefix}-{$date}-{$random}";
    }
    /**
     * Initier le paiement via NabooPay.
     */
    public function initiatePayment(Commande $commande, string $provider, array $data = [], ?string $idempotencyKey = null)
    {
        $idempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey);

        $methodePaiement = match ($provider) {
            'card', 'carte_bancaire' => 'carte_bancaire',
            'wave' => 'wave',
            'orange_money' => 'orange_money',
            default => throw new Exception("Provider de paiement non supporte: {$provider}"),
        };

        $paiement = $this->findReusablePayment($commande, $methodePaiement, $idempotencyKey);

        if ($paiement && $paiement->statut === 'en_cours') {
            $paymentUrl = $this->extractPaymentUrlFromPayment($paiement);

            if ($paymentUrl) {
                return [
                    'success' => true,
                    'payment_url' => $paymentUrl,
                    'transaction_id' => $paiement->transaction_id,
                    'provider' => 'naboopay',
                    'idempotent_replay' => true,
                ];
            }
        }

        if (!$paiement) {
            try {
                $paiement = Paiement::create([
                    'commande_id' => $commande->id,
                    'client_id' => $commande->client_id,
                    'montant' => $commande->montant_total,
                    'methode_paiement' => $methodePaiement,
                    'statut' => 'en_attente',
                    'reference_paiement' => $this->generatePaymentReference(),
                    'idempotency_key' => $idempotencyKey,
                ]);
            } catch (QueryException $e) {
                if (!$idempotencyKey || !$this->isUniqueConstraintViolation($e)) {
                    throw $e;
                }

                $paiement = Paiement::where('idempotency_key', $idempotencyKey)->first();
                if (!$paiement) {
                    throw $e;
                }
            }
        } elseif ($paiement->statut !== 'en_cours') {
            $paiement->update([
                'statut' => 'en_attente',
                'message_retour' => null,
            ]);
        }

        try {
            return app(NabooPayService::class)->createTransaction($commande, $paiement, $provider, $data);
        } catch (Exception $e) {
            $paiement->update([
                'statut' => 'echoue',
                'message_retour' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
    /**
     * Generer référence de paiement unique
     */
    private function generatePaymentReference()
    {
        return 'PAY-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(8));
    }

    private function normalizeIdempotencyKey(?string $key): ?string
    {
        $key = trim((string) $key);

        if ($key === '') {
            return null;
        }

        return Str::limit($key, 255, '');
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        return in_array((string) $e->getCode(), ['23000', '23505'], true);
    }

    private function orderResponse(Commande $commande, ?array $totals = null, bool $idempotentReplay = false): array
    {
        return [
            'success' => true,
            'idempotent_replay' => $idempotentReplay,
            'data' => [
                'commande' => $commande->load(['articles.produit', 'client']),
                'totals' => $totals,
            ]
        ];
    }

    private function findReusablePayment(Commande $commande, string $methodePaiement, ?string $idempotencyKey): ?Paiement
    {
        if ($idempotencyKey) {
            $paiement = Paiement::where('idempotency_key', $idempotencyKey)->first();

            if ($paiement) {
                if ($paiement->commande_id !== $commande->id || $paiement->methode_paiement !== $methodePaiement) {
                    throw new Exception('Cle d idempotence deja utilisee pour une autre operation de paiement.');
                }

                return $paiement;
            }
        }

        return $commande->paiements()
            ->where('methode_paiement', $methodePaiement)
            ->whereIn('statut', ['en_attente', 'en_cours', 'echoue', 'annule'])
            ->latest()
            ->first();
    }

    private function extractPaymentUrlFromPayment(Paiement $paiement): ?string
    {
        $body = is_string($paiement->donnees_api)
            ? json_decode($paiement->donnees_api, true)
            : $paiement->donnees_api;

        if (!is_array($body)) {
            return null;
        }

        return $body['checkout_url']
            ?? $body['payment_url']
            ?? $body['redirect_url']
            ?? $body['url']
            ?? data_get($body, 'data.checkout_url')
            ?? data_get($body, 'data.payment_url')
            ?? data_get($body, 'data.redirect_url');
    }

    /**
     * Confirmer le paiement
    * OPTIMISE: Email admin + Email client en file d'attente
     */
    public function confirmPayment(Paiement $paiement)
    {
        // Vérifier si déjà validé pour éviter double traitement
        if ($paiement->statut === 'valide') {
            \Log::warning('Paiement déjà validé, skip', ['paiement_id' => $paiement->id]);
            return [
                'success' => true,
                'commande' => $paiement->commande->load(['articles.produit', 'client', 'paiements']),
                'message' => 'Paiement déjà confirmé',
            ];
        }

        DB::beginTransaction();

        try {
            $paiement = Paiement::whereKey($paiement->id)->lockForUpdate()->firstOrFail();

            if ($paiement->statut === 'valide') {
                DB::commit();

                \Log::warning('Paiement deja valide apres verrouillage, skip', ['paiement_id' => $paiement->id]);

                return [
                    'success' => true,
                    'commande' => $paiement->commande->load(['articles.produit', 'client', 'paiements']),
                    'message' => 'Paiement deja confirme',
                ];
            }

            // 1. Mettre à jour le paiement
            $paiement->update([
                'statut' => 'valide',
                'date_paiement' => now(),
            ]);

            // 2. Mettre à jour la commande
            $commande = $paiement->commande;
            $commande->update([
                'statut' => 'confirmee',
                'date_confirmation' => now(),
            ]);

            $this->decrementConfirmedOrderStock($commande);
            $this->updatePromotionStats($commande);

            // 3. Vider le panier du client ou invité (OPTIMISÉ)
            if ($commande->client_id) {
                // Client authentifié : vider par client_id
                $deletedItems = \App\Models\ArticlesPanier::whereHas('panier', function($q) use ($commande) {
                    $q->where('client_id', $commande->client_id);
                })->delete();
                
                \Log::info('Panier vidé (client authentifié)', [
                    'client_id' => $commande->client_id,
                    'items_deleted' => $deletedItems
                ]);
            } else {
                // Client invité : vider par identifier de session
                $sessionIdentifier = 'guest_' . session()->getId();
                $deletedItems = \App\Models\ArticlesPanier::whereHas('panier', function($q) use ($sessionIdentifier) {
                    $q->where('identifier', $sessionIdentifier);
                })->delete();
                
                \Log::info('Panier vidé (invité)', [
                    'session_identifier' => $sessionIdentifier,
                    'items_deleted' => $deletedItems
                ]);
            }

            // 4. Mettre à jour les stats du client (optimisé)
            $client = $commande->client;
            $client->increment('nombre_commandes');
            $client->increment('total_depense', $commande->montant_total);
            
            // Calculer panier moyen
            $panierMoyen = $client->nombre_commandes > 0 
                ? $client->total_depense / $client->nombre_commandes 
                : 0;
                
            $client->update([
                'derniere_commande' => now(),
                'panier_moyen' => $panierMoyen,
            ]);

            DB::commit();

            // ===== NOTIFICATIONS ASYNCHRONES (performances optimisées) =====
            
            // 1. Notification WhatsApp à l'admin (TEMPORAIREMENT DÉSACTIVÉ - Twilio non configuré)
            // \App\Jobs\SendWhatsAppNotificationJob::dispatch(
            //     config('app.admin_whatsapp', '+221771397393'),
            //     $this->formatAdminWhatsAppMessage($commande, $paiement),
            //     'nouvelle_commande'
            // )->onQueue('high');

            // 2. Email de confirmation au client avec credentials si nouveau compte
            $temporaryPassword = cache()->pull("checkout_account_password:{$client->id}");
            $isNewAccount = filled($temporaryPassword);
            
            \App\Jobs\SendOrderConfirmationEmailJob::dispatch($commande, $temporaryPassword, $isNewAccount)
                ->onQueue('emails');

            // 3. Email admin apres confirmation de commande
            \App\Jobs\SendAdminOrderNotificationEmailJob::dispatch($commande)
                ->onQueue('emails');

            \Log::info('Paiement confirmé avec succès', [
                'paiement_id' => $paiement->id,
                'commande_id' => $commande->id,
                'montant' => $commande->montant_total,
                'new_account_created' => $isNewAccount
            ]);

            return [
                'success' => true,
                'commande' => $commande->load(['articles.produit', 'client', 'paiements']),
                'message' => 'Paiement confirmé avec succès',
            ];

        } catch (Exception $e) {
            DB::rollBack();
            \Log::error('Erreur confirmation paiement', [
                'paiement_id' => $paiement->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Formater message WhatsApp pour admin
     */
    private function formatAdminWhatsAppMessage(Commande $commande, Paiement $paiement)
    {
        $client = $commande->client;
        $montant = number_format($commande->montant_total, 0, ',', ' ');
        
        return "🎉 *NOUVELLE COMMANDE PAYÉE*\n\n"
            . "📦 N°: *{$commande->numero_commande}*\n"
            . "👤 Client: {$client->prenom} {$client->nom}\n"
            . "📞 Tél: {$client->telephone}\n"
            . "💰 Montant: *{$montant} FCFA*\n"
            . "💳 Paiement: {$paiement->methode_paiement}\n"
            . "📍 Livraison: {$commande->ville_livraison}\n\n"
            . "🕐 " . now()->format('d/m/Y à H:i') . "\n\n";
    }

}
