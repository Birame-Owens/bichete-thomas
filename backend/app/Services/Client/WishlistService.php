<?php

namespace App\Services\Client;

use App\Models\Produit;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

class WishlistService
{
    private string $sessionKey = 'ndeya_wishlist';

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

    public function getWishlist(): array
    {
        $user = Auth::guard('sanctum')->user();

        if ($user) {
            return $this->getWishlistFromDatabase($user->id);
        }

        if ($this->supportsGuestWishlist()) {
            return $this->getWishlistFromGuestDatabase($this->getGuestIdentifier());
        }

        return $this->getWishlistFromSession();
    }

    private function getWishlistFromDatabase(int $clientId): array
    {
        $wishlistItems = Wishlist::where('client_id', $clientId)
            ->with(['produit' => function ($q) {
                $q->where('est_visible', true)
                    ->with(['images_produits' => function ($query) {
                        $query->where('est_visible', true)->orderBy('ordre_affichage');
                    }, 'category']);
            }])
            ->get();

        return $this->formatWishlistItems($wishlistItems);
    }

    private function getWishlistFromGuestDatabase(string $guestIdentifier): array
    {
        $wishlistItems = Wishlist::where('guest_identifier', $guestIdentifier)
            ->with(['produit' => function ($q) {
                $q->where('est_visible', true)
                    ->with(['images_produits' => function ($query) {
                        $query->where('est_visible', true)->orderBy('ordre_affichage');
                    }, 'category']);
            }])
            ->get();

        return $this->formatWishlistItems($wishlistItems);
    }

    private function getWishlistFromSession(): array
    {
        $wishlistData = Session::get($this->sessionKey, []);

        if (empty($wishlistData)) {
            return ['items' => [], 'count' => 0];
        }

        $productIds = array_column($wishlistData, 'product_id');
        $products = Produit::whereIn('id', $productIds)
            ->where('est_visible', true)
            ->with(['images_produits' => function ($q) {
                $q->where('est_visible', true)->orderBy('ordre_affichage');
            }, 'category'])
            ->get();

        $items = [];
        foreach ($wishlistData as $item) {
            $product = $products->firstWhere('id', $item['product_id']);
            if ($product) {
                $items[] = [
                    'product' => $this->formatProduct($product),
                    'added_at' => $item['added_at'] ?? now()->toISOString(),
                ];
            }
        }

        return [
            'items' => $items,
            'count' => count($items),
        ];
    }

    public function addToWishlist(int $productId): array
    {
        $product = Produit::find($productId);

        if (!$product || !$product->est_visible) {
            return ['success' => false, 'message' => 'Produit non trouve'];
        }

        $user = Auth::guard('sanctum')->user();

        if ($user) {
            $exists = Wishlist::where('client_id', $user->id)
                ->where('produit_id', $productId)
                ->exists();

            if ($exists) {
                return ['success' => false, 'message' => 'Produit deja dans vos favoris'];
            }

            Wishlist::create([
                'client_id' => $user->id,
                'produit_id' => $productId,
            ]);

            return ['success' => true, 'message' => 'Produit ajoute aux favoris'];
        }

        if ($this->supportsGuestWishlist()) {
            $guestIdentifier = $this->getGuestIdentifier();
            $exists = Wishlist::where('guest_identifier', $guestIdentifier)
                ->where('produit_id', $productId)
                ->exists();

            if ($exists) {
                return ['success' => false, 'message' => 'Produit deja dans vos favoris'];
            }

            Wishlist::create([
                'guest_identifier' => $guestIdentifier,
                'produit_id' => $productId,
            ]);

            return ['success' => true, 'message' => 'Produit ajoute aux favoris'];
        }

        $wishlist = Session::get($this->sessionKey, []);
        $exists = collect($wishlist)->contains('product_id', $productId);

        if ($exists) {
            return ['success' => false, 'message' => 'Produit deja dans vos favoris'];
        }

        $wishlist[] = [
            'product_id' => $productId,
            'added_at' => now()->toISOString(),
        ];

        Session::put($this->sessionKey, $wishlist);

        return ['success' => true, 'message' => 'Produit ajoute aux favoris'];
    }

    public function removeFromWishlist(int $productId): array
    {
        $user = Auth::guard('sanctum')->user();

        if ($user) {
            Wishlist::where('client_id', $user->id)
                ->where('produit_id', $productId)
                ->delete();

            return ['success' => true, 'message' => 'Produit retire des favoris'];
        }

        if ($this->supportsGuestWishlist()) {
            Wishlist::where('guest_identifier', $this->getGuestIdentifier())
                ->where('produit_id', $productId)
                ->delete();

            return ['success' => true, 'message' => 'Produit retire des favoris'];
        }

        $wishlist = Session::get($this->sessionKey, []);
        $wishlist = array_filter($wishlist, fn ($item) => $item['product_id'] !== $productId);
        Session::put($this->sessionKey, array_values($wishlist));

        return ['success' => true, 'message' => 'Produit retire des favoris'];
    }

    public function clearWishlist(): array
    {
        $user = Auth::guard('sanctum')->user();

        if ($user) {
            Wishlist::where('client_id', $user->id)->delete();
            return ['success' => true, 'message' => 'Favoris vides'];
        }

        if ($this->supportsGuestWishlist()) {
            Wishlist::where('guest_identifier', $this->getGuestIdentifier())->delete();
            return ['success' => true, 'message' => 'Favoris vides'];
        }

        Session::forget($this->sessionKey);

        return ['success' => true, 'message' => 'Favoris vides'];
    }

    public function isInWishlist(int $productId): bool
    {
        $user = Auth::guard('sanctum')->user();

        if ($user) {
            return Wishlist::where('client_id', $user->id)
                ->where('produit_id', $productId)
                ->exists();
        }

        if ($this->supportsGuestWishlist()) {
            return Wishlist::where('guest_identifier', $this->getGuestIdentifier())
                ->where('produit_id', $productId)
                ->exists();
        }

        $wishlist = Session::get($this->sessionKey, []);

        return collect($wishlist)->contains('product_id', $productId);
    }

    public function moveToCart(int $productId): array
    {
        $cartService = new CartService();
        $result = $cartService->addItem($productId, 1);

        if ($result['success']) {
            $this->removeFromWishlist($productId);
            return ['success' => true, 'message' => 'Produit deplace vers le panier'];
        }

        return $result;
    }

    public function getCount(): int
    {
        $user = Auth::guard('sanctum')->user();

        if ($user) {
            return Wishlist::where('client_id', $user->id)->count();
        }

        if ($this->supportsGuestWishlist()) {
            return Wishlist::where('guest_identifier', $this->getGuestIdentifier())->count();
        }

        return count(Session::get($this->sessionKey, []));
    }

    public function getWishlistCount($user): int
    {
        if ($user) {
            return Wishlist::where('client_id', $user->id)->count();
        }

        if ($this->supportsGuestWishlist()) {
            return Wishlist::where('guest_identifier', $this->getGuestIdentifier())->count();
        }

        return count(Session::get($this->sessionKey, []));
    }

    private function formatWishlistItems($wishlistItems): array
    {
        $items = [];

        foreach ($wishlistItems as $item) {
            if ($item->produit) {
                $items[] = [
                    'product' => $this->formatProduct($item->produit),
                    'added_at' => $item->created_at?->toISOString() ?? now()->toISOString(),
                ];
            }
        }

        return [
            'items' => $items,
            'count' => count($items),
        ];
    }

    private function formatProduct(Produit $product): array
    {
        return [
            'id' => $product->id,
            'nom' => $product->nom,
            'slug' => $product->slug,
            'prix' => $product->prix,
            'prix_promo' => $product->prix_promo,
            'prix_affiche' => $product->prix_promo ?: $product->prix,
            'en_promo' => $product->prix_promo !== null,
            'image' => $product->image,
            'image_principale' => $product->image,
            'category' => $product->category ? $product->category->nom : '',
            'en_stock' => !$product->gestion_stock || $this->resolveStockTotal($product) > 0,
            'note_moyenne' => $product->note_moyenne,
            'url' => "/produits/{$product->slug}",
        ];
    }

    private function supportsGuestWishlist(): bool
    {
        static $supports = null;

        if ($supports !== null) {
            return $supports;
        }

        return $supports = Schema::hasColumn('wishlists', 'guest_identifier')
            && Schema::hasColumn('wishlists', 'produit_id');
    }

    private function getGuestIdentifier(): string
    {
        $cartId = request()->header('X-Cart-Id');
        if (is_string($cartId) && preg_match('/^guest_[A-Za-z0-9._-]{8,120}$/', $cartId)) {
            return $cartId;
        }

        $cartCookie = request()->cookie('ndeya_cart_id');
        if (is_string($cartCookie) && preg_match('/^guest_[A-Za-z0-9._-]{8,120}$/', $cartCookie)) {
            return $cartCookie;
        }

        return 'guest_' . session()->getId();
    }
}
