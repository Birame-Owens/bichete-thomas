<?php

namespace Tests\Feature\Client;

use App\Models\Category;
use App\Models\Produit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \App\Http\Middleware\RateLimitMiddleware::class,
            \App\Http\Middleware\ApiResponseCache::class,
        ]);
        Cache::flush();

        $this->category = Category::create([
            'nom'             => 'Robes',
            'slug'            => 'robes',
            'est_active'      => true,
            'est_populaire'   => false,
            'ordre_affichage' => 1,
        ]);
    }

    private function makeProduit(array $overrides = []): Produit
    {
        return Produit::create(array_merge([
            'nom'              => 'Robe Rouge',
            'slug'             => 'robe-rouge',
            'description'      => 'Une belle robe rouge.',
            'image_principale' => 'produits/default-product.jpg',
            'prix'             => 25000,
            'categorie_id'     => $this->category->id,
            'stock_disponible' => 10,
            'seuil_alerte'     => 2,
            'gestion_stock'    => true,
            'est_visible'      => true,
            'est_populaire'    => false,
            'est_nouveaute'    => false,
        ], $overrides));
    }

    // ── GET /api/client/products ─────────────────────────────────────────────

    public function test_index_returns_products_list(): void
    {
        $this->makeProduit(['nom' => 'Produit A', 'slug' => 'produit-a']);
        $this->makeProduit(['nom' => 'Produit B', 'slug' => 'produit-b']);

        $response = $this->getJson('/api/client/products');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['data' => ['products', 'pagination']]);

        $this->assertCount(2, $response->json('data.products'));
    }

    public function test_index_returns_only_visible_products(): void
    {
        $this->makeProduit(['nom' => 'Visible', 'slug' => 'visible', 'est_visible' => true]);
        $this->makeProduit(['nom' => 'Caché',   'slug' => 'cache',   'est_visible' => false]);

        $response = $this->getJson('/api/client/products');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.products'));
    }

    public function test_index_returns_empty_when_no_products(): void
    {
        $response = $this->getJson('/api/client/products');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true);

        $this->assertCount(0, $response->json('data.products'));
    }

    public function test_index_supports_category_filter(): void
    {
        $other = Category::create([
            'nom'             => 'Chaussures',
            'slug'            => 'chaussures',
            'est_active'      => true,
            'est_populaire'   => false,
            'ordre_affichage' => 2,
        ]);

        $this->makeProduit(['nom' => 'Robe A', 'slug' => 'robe-a']);
        Produit::create([
            'nom'              => 'Escarpin',
            'slug'             => 'escarpin',
            'description'      => 'Des chaussures.',
            'image_principale' => 'produits/default-product.jpg',
            'prix'             => 12000,
            'categorie_id'     => $other->id,
            'stock_disponible' => 5,
            'seuil_alerte'     => 1,
            'gestion_stock'    => true,
            'est_visible'      => true,
            'est_populaire'    => false,
            'est_nouveaute'    => false,
        ]);

        $response = $this->getJson('/api/client/products?category=' . $this->category->id);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.products'));
    }

    // ── GET /api/client/products/{slug} ──────────────────────────────────────

    public function test_show_returns_product_by_slug(): void
    {
        $this->makeProduit(['nom' => 'Robe Rouge', 'slug' => 'robe-rouge']);

        $this->getJson('/api/client/products/robe-rouge')
             ->assertStatus(200)
             ->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/client/products/inexistant')
             ->assertStatus(404)
             ->assertJsonPath('success', false);
    }

    public function test_show_returns_404_for_invisible_product(): void
    {
        $this->makeProduit(['nom' => 'Masqué', 'slug' => 'masque', 'est_visible' => false]);

        $this->getJson('/api/client/products/masque')
             ->assertStatus(404);
    }

    // ── GET /api/client/products/trending ────────────────────────────────────

    public function test_trending_returns_success(): void
    {
        $this->makeProduit();

        $response = $this->getJson('/api/client/products/trending');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true);
    }

    // ── GET /api/client/products/new-arrivals ─────────────────────────────────

    public function test_new_arrivals_returns_success(): void
    {
        $this->makeProduit();

        $response = $this->getJson('/api/client/products/new-arrivals');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true);
    }
}
