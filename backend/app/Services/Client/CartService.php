<?php
// ================================================================
// 📝 FICHIER: app/Services/Client/CartService.php
// ================================================================

namespace App\Services\Client;

use App\Models\Produit;
use App\Models\Promotion;
use App\Models\ArticlesPanier;
use App\Models\Panier;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CartService
{
    private string $sessionKey = 'ndeya_cart';
    private string $couponKey = 'ndeya_coupon';

    private function resolveStockTotal($product): int
    {
        if ($product->couleur_tailles_stock) {
            $stockData = json_decode($product->couleur_tailles_stock, true) ?? [];
            $total = 0;
            foreach ($stockData as $tailles) {
                foreach ($tailles as $qty) {
                    $total += (int) $qty;
                }
            }
            return $total;
        }
        return (int) ($product->stock_disponible ?? 0);
    }

    public function currentIdentifier(): string
    {
        return $this->getCartIdentifier();
    }

    /**
     * Obtenir l'identifiant unique du panier (user_id ou session_id)
     */
    private function getCartIdentifier(): string
    {
        // Vérifier utilisateur authentifié (Sanctum ou session web)
        $user = request()->user() ?? Auth::user();
        
        // Si utilisateur authentifié, TOUJOURS utiliser son ID
        if ($user) {
            return 'user_' . $user->id;
        }

        $cartId = request()->header('X-Cart-Id');
        if (is_string($cartId) && preg_match('/^guest_[A-Za-z0-9._-]{8,120}$/', $cartId)) {
            return $cartId;
        }

        $cartCookie = request()->cookie('ndeya_cart_id');
        if (is_string($cartCookie) && preg_match('/^guest_[A-Za-z0-9._-]{8,120}$/', $cartCookie)) {
            return $cartCookie;
        }
        
        // Sinon, utiliser l'ID de session Laravel
        return 'guest_' . session()->getId();
    }

    /**
     * Migrer le panier de session vers l'utilisateur lors de la connexion
     */
    public function migrateGuestCart(): void
    {
        try {
            $user = request()->user() ?? Auth::user();
            
            if (!$user) {
                return;
            }

            // ID du panier invité basé sur la session actuelle
            $guestCartId = 'guest_' . session()->getId();
            $userCartKey = 'user_' . $user->id;

            // Éviter de migrer si on est déjà sur le panier utilisateur
            if ($guestCartId === $userCartKey) {
                return;
            }

            // Récupérer le panier de l'invité avec articles (éviter N+1)
            $guestPanier = Panier::where('session_id', $guestCartId)
                ->with('articles_paniers')
                ->first();
            
            if (!$guestPanier || $guestPanier->articles_paniers->isEmpty()) {
                return;
            }
            
            // Récupérer ou créer le panier utilisateur
            $userPanier = Panier::firstOrCreate(
                ['session_id' => $userCartKey],
                [
                    'identifiant' => $userCartKey,
                    'client_id' => null, // Sera null pour les utilisateurs sans client
                    'sous_total' => 0,
                    'nombre_articles' => 0,
                    'statut' => 'actif',
                    'derniere_activite' => now()
                ]
            );
            
            // Migrer les articles
            foreach ($guestPanier->articles_paniers as $item) {
                // Vérifier si l'article existe déjà dans le panier utilisateur
                $existingItem = $userPanier->articles_paniers()
                    ->where('produit_id', $item->produit_id)
                    ->where('taille_choisie', $item->taille_choisie)
                    ->where('couleur_choisie', $item->couleur_choisie)
                    ->first();

                if ($existingItem) {
                    // Additionner les quantités
                    $existingItem->quantite += $item->quantite;
                    $existingItem->prix_total = $existingItem->prix_unitaire * $existingItem->quantite;
                    $existingItem->save();
                    $item->delete();
                } else {
                    // Transférer l'article au nouveau panier
                    $item->panier_id = $userPanier->id;
                    $item->save();
                }
            }
            
            // Mettre à jour les totaux et supprimer l'ancien panier
            $this->updatePanierTotals($userPanier);
            $guestPanier->delete();
        } catch (\Exception $e) {
            // En cas d'erreur, on log mais on ne bloque pas la connexion
            \Log::warning('Erreur migration panier: ' . $e->getMessage());
        }
    }

    public function getCart(): array
    {
        $identifier = $this->getCartIdentifier();
        \Log::info('🛒 CartService@getCart - Identifier', ['identifier' => $identifier]);
        $cart = $this->fetchCart($identifier);
        \Log::info('🛒 CartService@getCart - Result', [
            'items_count' => count($cart['items']),
            'total' => $cart['total']
        ]);
        return $cart;
    }
    
    private function fetchCart(string $identifier): array
    {
        // Récupérer le panier
        $panier = Panier::where('session_id', $identifier)->first();
        
        \Log::info('🛒 CartService@fetchCart', [
            'identifier' => $identifier,
            'panier_found' => $panier ? true : false,
            'panier_id' => $panier?->id
        ]);
        
        if (!$panier) {
            \Log::info('🛒 CartService@fetchCart - Panier vide');
            return $this->getEmptyCart();
        }
        
        // Récupérer les articles avec les relations
        $cartItems = $panier->articles_paniers()
            ->with(['produit.images_produits' => function($q) {
                // ✅ Charger TOUS les images (comme ProductDetailPage)
                // Pas de filtre est_visible pour afficher même les images cachées
                $q->orderBy('ordre_affichage');
            }])
            ->get();
        
        $coupon = Session::get($this->couponKey);
        
        if ($cartItems->isEmpty()) {
            return $this->getEmptyCart();
        }

        $items = [];
        $subtotal = 0;

        foreach ($cartItems as $cartItem) {
            $product = $cartItem->produit;

            if (!$product || !$product->est_visible) {
                $cartItem->delete(); // Nettoyer les produits invalides
                continue;
            }

            $itemTotal = ($product->prix_promo ?: $product->prix) * $cartItem->quantite;
            $subtotal += $itemTotal;

            $prixUnitaire = $product->prix_promo ?: $product->prix;

            // Formater les images en tenant compte de la couleur choisie
            $imageData = $this->formatProductImagesForCart($product, $cartItem->couleur_choisie);
            
            
            $items[] = [
                'id' => $cartItem->id,
                'product' => [
                    'id' => $product->id,
                    'nom' => $product->nom,
                    'slug' => $product->slug,
                    'prix' => $product->prix,
                    'prix_promo' => $product->prix_promo,
                    'image' => $imageData['image'],
                    'image_principale' => $imageData['image_principale'],
                    'images' => $imageData['images'],
                    'en_stock' => !$product->gestion_stock || $this->resolveStockTotal($product) > 0
                ],
                'quantite' => $cartItem->quantite,
                'prix_unitaire' => $prixUnitaire,
                'taille' => $cartItem->taille_choisie,
                'couleur' => $cartItem->couleur_choisie,
                'prix_total' => $itemTotal,
                'added_at' => $cartItem->created_at->toISOString()
            ];
        }

        $discount = 0;
        $couponData = null;

        if ($coupon && isset($coupon['code'])) {
            $promotion = $this->validateCoupon($coupon['code'], $subtotal);
            if ($promotion) {
                $discount = $this->calculateDiscount($promotion, $subtotal);
                $couponData = [
                    'code' => $coupon['code'],
                    'nom' => $promotion->nom,
                    'type' => $promotion->type_promotion,
                    'valeur' => $promotion->valeur,
                    'discount' => $discount
                ];
            }
        }

        $shippingFee = $this->calculateShipping($subtotal);
        $total = $subtotal - $discount + $shippingFee;

        return [
            'items' => $items,
            'count' => count($items),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping_fee' => $shippingFee,
            'total' => $total,
            'coupon' => $couponData,
            'has_free_shipping' => $subtotal >= 50000
        ];
    }

    public function addItem(int $productId, int $quantity = 1, array $options = []): array
    {
        // Validation sécurité : quantité maximale
        if ($quantity > 100) {
            return ['success' => false, 'message' => 'Quantité maximale dépassée (max: 100)'];
        }
        
        if ($quantity < 1) {
            return ['success' => false, 'message' => 'Quantité invalide'];
        }
        
        $product = Produit::find($productId);
        
        if (!$product || !$product->est_visible) {
            return ['success' => false, 'message' => 'Produit non trouvé'];
        }

        if ($product->gestion_stock) {
            $couleur = $options['couleur'] ?? null;
            $taille = $options['taille'] ?? null;

            // Vérifier le stock par variante si disponible
            if ($couleur && $taille && $product->couleur_tailles_stock) {
                $stockData = json_decode($product->couleur_tailles_stock, true) ?? [];
                $variantStock = $stockData[$couleur][$taille] ?? null;
                if ($variantStock !== null && $variantStock < $quantity) {
                    return ['success' => false, 'message' => "Stock insuffisant pour {$couleur} / {$taille}"];
                }
            } elseif ($this->resolveStockTotal($product) < $quantity) {
                return ['success' => false, 'message' => 'Stock insuffisant'];
            }
        }

        $identifier = $this->getCartIdentifier();
        
        \Log::info('🛒 CartService@addItem', [
            'product_id' => $productId,
            'quantity' => $quantity,
            'identifier' => $identifier,
            'options' => $options
        ]);
        
        // Récupérer ou créer le panier
        $user = request()->user() ?? Auth::user();
        $panier = Panier::firstOrCreate(
            ['session_id' => $identifier],
            [
                'identifiant' => $identifier,
                'client_id' => null, // On n'utilise plus client_id, on utilise session_id
                'sous_total' => 0,
                'nombre_articles' => 0,
                'statut' => 'actif',
                'derniere_activite' => now()
            ]
        );
        
        // Vérifier si l'article existe déjà
        $existingItem = ArticlesPanier::where('panier_id', $panier->id)
            ->where('produit_id', $productId)
            ->where('taille_choisie', $options['taille'] ?? null)
            ->where('couleur_choisie', $options['couleur'] ?? null)
            ->first();

        if ($existingItem) {
            $existingItem->quantite += $quantity;
            $existingItem->save();
        } else {
            ArticlesPanier::create([
                'panier_id' => $panier->id,
                'produit_id' => $productId,
                'quantite' => $quantity,
                'taille_choisie' => $options['taille'] ?? null,
                'couleur_choisie' => $options['couleur'] ?? null,
                'prix_unitaire' => $product->prix_promo ?: $product->prix,
                'prix_total' => ($product->prix_promo ?: $product->prix) * $quantity,
                'date_ajout' => now(),
                'nombre_modifications' => 0,
                'est_reserve' => false
            ]);
        }
        
        // Mettre à jour le panier
        $this->updatePanierTotals($panier);
        
        \Log::info('✅ CartService@addItem - Produit ajouté', [
            'panier_id' => $panier->id,
            'identifier' => $identifier,
            'nombre_articles' => $panier->nombre_articles,
            'sous_total' => $panier->sous_total
        ]);

        return ['success' => true, 'message' => 'Produit ajouté au panier'];
    }

    public function updateItem(string $itemId, int $quantity): array
    {
        if ($quantity <= 0) {
            return $this->removeItem($itemId);
        }

        $item = ArticlesPanier::find($itemId);
        
        if (!$item) {
            return ['success' => false, 'message' => 'Article non trouvé'];
        }

        $item->quantite = $quantity;
        $item->prix_total = $item->prix_unitaire * $quantity;
        $item->nombre_modifications++;
        $item->derniere_modification = now();
        $item->save();
        
        // Mettre à jour les totaux du panier
        $this->updatePanierTotals($item->panier);

        return ['success' => true, 'message' => 'Panier mis à jour'];
    }

    public function removeItem(string $itemId): array
    {
        $item = ArticlesPanier::find($itemId);
        
        if ($item) {
            $panier = $item->panier;
            $item->delete();
            
            // Mettre à jour les totaux
            if ($panier) {
                $this->updatePanierTotals($panier);
            }
        }
        
        return ['success' => true, 'message' => 'Article retiré du panier'];
    }

    public function clearCart(): array
    {
        $identifier = $this->getCartIdentifier();
        
        $panier = Panier::where('session_id', $identifier)->first();
        
        if ($panier) {
            $panier->articles_paniers()->delete();
            $panier->delete();
        }
        
        Session::forget($this->couponKey);
        
        return ['success' => true, 'message' => 'Panier vidé'];
    }

    public function applyCoupon(string $code): array
    {
        $code = strtoupper(trim($code));
        $cart = $this->getCart();
        
        if (empty($cart['items'])) {
            return ['success' => false, 'message' => 'Votre panier est vide'];
        }

        $promotion = $this->validateCoupon($code, $cart['subtotal']);
        
        if (!$promotion) {
            return ['success' => false, 'message' => 'Code promo invalide ou expire'];
        }

        Session::put($this->couponKey, ['code' => $code]);
        $updatedCart = $this->fetchCart($this->getCartIdentifier());
        
        return [
            'success' => true,
            'message' => 'Code promo applique avec succes',
            'discount' => $updatedCart['discount'],
            'data' => $updatedCart,
        ];
    }

    public function removeCoupon(): array
    {
        Session::forget($this->couponKey);
        return [
            'success' => true,
            'message' => 'Code promo retire',
            'data' => $this->fetchCart($this->getCartIdentifier()),
        ];
    }

    public function generateWhatsAppMessage(): array
    {
        $cart = $this->getCart();
        
        if (empty($cart['items'])) {
            return ['success' => false, 'message' => 'Votre panier est vide'];
        }

        $message = "🛒 *COMMANDE NDEYA SHOP*\n\n";
        
        foreach ($cart['items'] as $item) {
            $message .= "📦 *{$item['product']['nom']}*\n";
            $message .= "   Quantité: {$item['quantity']}\n";
            if ($item['taille']) $message .= "   Taille: {$item['taille']}\n";
            if ($item['couleur']) $message .= "   Couleur: {$item['couleur']}\n";
            $message .= "   Prix: " . number_format($item['prix_total'], 0, ',', ' ') . " FCFA\n\n";
        }

        $message .= "💰 *RÉCAPITULATIF:*\n";
        $message .= "Sous-total: " . number_format($cart['subtotal'], 0, ',', ' ') . " FCFA\n";
        
        if ($cart['discount'] > 0) {
            $message .= "Remise: -" . number_format($cart['discount'], 0, ',', ' ') . " FCFA\n";
        }
        
        $message .= "Livraison: " . number_format($cart['shipping_fee'], 0, ',', ' ') . " FCFA\n";
        $message .= "*TOTAL: " . number_format($cart['total'], 0, ',', ' ') . " FCFA*\n\n";
        $message .= "Je souhaiterais passer cette commande. Merci ! 🙏";

        $whatsappNumber = config('app.whatsapp_number', '221784661412');
        
        return [
            'success' => true,
            'data' => [
                'message' => $message,
                'url' => "https://wa.me/{$whatsappNumber}?text=" . urlencode($message),
                'items_count' => count($cart['items']),
                'total' => $cart['total']
            ]
        ];
    }

    private function validateCoupon(string $code, float $subtotal): ?Promotion
    {
        return Promotion::whereRaw('lower(code) = ?', [strtolower($code)])
            ->where('est_active', true)
            ->where('date_debut', '<=', now())
            ->where('date_fin', '>=', now())
            ->where(function($query) use ($subtotal) {
                $query->whereNull('montant_minimum')
                      ->orWhere('montant_minimum', '<=', $subtotal);
            })
            ->first();
    }

    private function calculateDiscount(Promotion $promotion, float $subtotal): float
    {
        switch ($promotion->type_promotion) {
            case 'pourcentage':
                $discount = ($subtotal * $promotion->valeur) / 100;
                return $promotion->reduction_maximum ? 
                    min($discount, $promotion->reduction_maximum) : $discount;
                
            case 'montant_fixe':
                return min($promotion->valeur, $subtotal);
                
            default:
                return 0;
        }
    }

    /**
     * Mettre à jour les totaux du panier
     */
    private function updatePanierTotals(Panier $panier): void
    {
        $articles = $panier->articles_paniers;
        $panier->nombre_articles = $articles->sum('quantite');
        $panier->sous_total = $articles->sum('prix_total');
        $panier->derniere_activite = now();
        $panier->save();
    }

    private function calculateShipping(float $subtotal): float
    {
        // Récupérer les paramètres de livraison depuis la base de données
        $shippingSettings = \App\Models\ShippingSetting::getSettings();
        
        // Si la livraison est désactivée, retourner 0
        if (!$shippingSettings->is_enabled) {
            return 0;
        }
        
        // Appliquer la livraison gratuite si le seuil est atteint
        if ($subtotal >= $shippingSettings->free_threshold) {
            return 0;
        }
        
        // Retourner les frais par défaut
        return $shippingSettings->default_cost;
    }

    private function formatProductImagesForCart(Produit $product, ?string $couleur = null): array
    {
        $placeholderUrl = asset('images/placeholder-product.jpg');
        $images = [];
        $mainImage = null;
        $colorImage = null;

        foreach ($product->images_produits ?? [] as $image) {
            $urls = $this->imageUrls($image);

            if (!$urls['original']) {
                continue;
            }

            $imageData = [
                'id' => $image->id,
                'url' => $urls['medium'],
                'original' => $urls['original'],
                'thumb' => $urls['thumbnail'],
                'thumbnail' => $urls['thumbnail'],
                'medium' => $urls['medium'],
                'alt' => $image->alt_text ?: $product->nom,
                'est_principale' => (bool) $image->est_principale,
                'ordre' => $image->ordre_affichage,
            ];

            $images[] = $imageData;

            if ($image->est_principale) {
                $mainImage = $imageData;
            }

            // Première image correspondant à la couleur choisie
            if ($couleur && !$colorImage && $image->couleur_associee === $couleur) {
                $colorImage = $imageData;
            }
        }

        // Priorité: image de la couleur > image principale > première image
        $mainImage = $colorImage ?? $mainImage ?? $images[0] ?? null;

        if (!$mainImage && $product->image_principale) {
            $fallbackUrl = $this->assetUrlIfExists($product->image_principale) ?: $product->image;
            if ($fallbackUrl) {
                $mainImage = [
                    'id' => null,
                    'url' => $fallbackUrl,
                    'original' => $fallbackUrl,
                    'thumb' => $fallbackUrl,
                    'thumbnail' => $fallbackUrl,
                    'medium' => $fallbackUrl,
                    'alt' => $product->nom,
                    'est_principale' => true,
                    'ordre' => 0,
                ];
                $images[] = $mainImage;
            }
        }

        return [
            'image' => $mainImage['medium'] ?? $placeholderUrl,
            'image_principale' => $mainImage,
            'images' => $images,
        ];
    }

    private function imageUrls($image): array
    {
        $original = $this->assetUrlIfExists($image->chemin_original);
        $medium = $this->assetUrlIfExists($image->chemin_moyen) ?: $original;
        $thumbnail = $this->assetUrlIfExists($image->chemin_miniature) ?: $medium;

        return [
            'original' => $original,
            'medium' => $medium,
            'thumbnail' => $thumbnail,
        ];
    }

    private function assetUrlIfExists(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        $normalized = ltrim(preg_replace('#^/?storage/#', '', $path), '/');

        if (!Storage::disk('public')->exists($normalized)) {
            return null;
        }

        return asset('storage/' . $normalized);
    }

    private function generateItemId(int $productId, array $options): string
    {
        $optionsString = http_build_query($options);
        return md5($productId . '_' . $optionsString);
    }

    private function getEmptyCart(): array
    {
        return [
            'items' => [],
            'count' => 0,
            'subtotal' => 0,
            'discount' => 0,
            'shipping_fee' => 0,
            'total' => 0,
            'coupon' => null,
            'has_free_shipping' => false
        ];
    }
}
