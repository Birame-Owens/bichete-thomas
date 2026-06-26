<?php

namespace Tests\Feature\Client;

use App\Models\Category;
use App\Models\Produit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \App\Http\Middleware\RateLimitMiddleware::class,
            \App\Http\Middleware\ApiResponseCache::class,
        ]);
    }

    private function makeCategory(array $overrides = []): Category
    {
        return Category::create(array_merge([
            'nom'             => 'Robes',
            'slug'            => 'robes',
            'est_active'      => true,
            'est_populaire'   => false,
            'ordre_affichage' => 1,
        ], $overrides));
    }

    private function makeProduit(Category $category, array $overrides = []): Produit
    {
        return Produit::create(array_merge([
            'nom'              => 'Produit Test',
            'slug'             => 'produit-test',
            'description'      => 'Description.',
            'image_principale' => 'produits/default-product.jpg',
            'prix'             => 15000,
            'categorie_id'     => $category->id,
            'stock_disponible' => 5,
            'seuil_alerte'     => 1,
            'gestion_stock'    => true,
            'est_visible'      => true,
            'est_populaire'    => false,
            'est_nouveaute'    => false,
        ], $overrides));
    }

    // ── GET /api/client/categories ────────────────────────────────────────────

    public function test_index_returns_only_active_categories(): void
    {
        $this->makeCategory(['nom' => 'Active',   'slug' => 'active',   'est_active' => true]);
        $this->makeCategory(['nom' => 'Inactive', 'slug' => 'inactive', 'est_active' => false]);

        $response = $this->getJson('/api/client/categories');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true);

        $noms = array_column($response->json('data'), 'nom');
        $this->assertContains('Active', $noms);
        $this->assertNotContains('Inactive', $noms);
    }

    public function test_index_returns_categories_with_required_fields(): void
    {
        $this->makeCategory();

        $response = $this->getJson('/api/client/categories');

        $response->assertStatus(200);
        $first = $response->json('data.0');

        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('nom', $first);
        $this->assertArrayHasKey('slug', $first);
        $this->assertArrayHasKey('url', $first);
        $this->assertArrayHasKey('produits_count', $first);
    }

    public function test_index_returns_empty_array_when_no_categories(): void
    {
        $response = $this->getJson('/api/client/categories');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonCount(0, 'data');
    }

    public function test_index_includes_subcategories_with_parent_id(): void
    {
        $parent = $this->makeCategory(['nom' => 'Parent', 'slug' => 'parent', 'est_active' => true]);
        $this->makeCategory(['nom' => 'Enfant', 'slug' => 'enfant', 'est_active' => true, 'parent_id' => $parent->id]);

        $response = $this->getJson('/api/client/categories');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);

        $child = collect($data)->firstWhere('slug', 'enfant');
        $this->assertEquals($parent->id, $child['parent_id']);
    }

    // ── GET /api/client/categories/{slug} ────────────────────────────────────

    public function test_show_returns_category_by_slug(): void
    {
        $this->makeCategory(['nom' => 'Robes', 'slug' => 'robes']);

        $this->getJson('/api/client/categories/robes')
             ->assertStatus(200)
             ->assertJsonPath('success', true)
             ->assertJsonPath('data.nom', 'Robes')
             ->assertJsonPath('data.slug', 'robes');
    }

    public function test_show_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/client/categories/inexistant')
             ->assertStatus(404)
             ->assertJsonPath('success', false);
    }

    public function test_show_returns_404_for_inactive_category(): void
    {
        $this->makeCategory(['nom' => 'Masquée', 'slug' => 'masquee', 'est_active' => false]);

        $this->getJson('/api/client/categories/masquee')
             ->assertStatus(404);
    }

    public function test_show_includes_subcategories(): void
    {
        $parent = $this->makeCategory(['nom' => 'Parent', 'slug' => 'parent']);
        $this->makeCategory(['nom' => 'Enfant', 'slug' => 'enfant', 'parent_id' => $parent->id]);

        $response = $this->getJson('/api/client/categories/parent');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => ['subcategories']]);
        $this->assertCount(1, $response->json('data.subcategories'));
    }

    public function test_show_counts_only_visible_products(): void
    {
        $cat = $this->makeCategory();
        $this->makeProduit($cat, ['nom' => 'Visible', 'slug' => 'visible', 'est_visible' => true]);
        $this->makeProduit($cat, ['nom' => 'Caché',   'slug' => 'cache',   'est_visible' => false]);

        $response = $this->getJson('/api/client/categories/robes');

        $response->assertStatus(200)
                 ->assertJsonPath('data.produits_count', 1);
    }

    // ── GET /api/client/categories/{slug}/products ────────────────────────────

    public function test_get_products_returns_products_of_category(): void
    {
        $cat = $this->makeCategory();
        $this->makeProduit($cat, ['nom' => 'Robe Rouge', 'slug' => 'robe-rouge']);

        $response = $this->getJson('/api/client/categories/robes/products');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true);
    }

    public function test_get_products_returns_404_for_unknown_category(): void
    {
        $this->getJson('/api/client/categories/inexistant/products')
             ->assertStatus(404);
    }
}
