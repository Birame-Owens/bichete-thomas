<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProduitControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role'   => 'admin',
            'statut' => 'actif',
        ]);

        $this->category = Category::create([
            'nom'             => 'Robes',
            'slug'            => 'robes',
            'est_active'      => true,
            'est_populaire'   => false,
            'ordre_affichage' => 1,
        ]);
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin, 'sanctum');
    }

    private function makeProduit(array $overrides = []): Produit
    {
        return Produit::create(array_merge([
            'nom'              => 'Robe Rouge',
            'slug'             => 'robe-rouge',
            'description'      => 'Une belle robe.',
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

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_products(): void
    {
        $this->makeProduit(['nom' => 'Produit A', 'slug' => 'produit-a']);
        $this->makeProduit(['nom' => 'Produit B', 'slug' => 'produit-b']);

        $response = $this->asAdmin()->getJson('/api/admin/produits');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['data' => ['produits', 'pagination']]);

        $this->assertCount(2, $response->json('data.produits'));
    }

    public function test_index_filters_by_status_visible(): void
    {
        $this->makeProduit(['nom' => 'Visible', 'slug' => 'visible', 'est_visible' => true]);
        $this->makeProduit(['nom' => 'Caché', 'slug' => 'cache', 'est_visible' => false]);

        $response = $this->asAdmin()->getJson('/api/admin/produits?status=visible');

        $this->assertCount(1, $response->json('data.produits'));
        $this->assertEquals('Visible', $response->json('data.produits.0.nom'));
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/admin/produits')->assertStatus(401);
    }

    // ── show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_product_details(): void
    {
        $produit = $this->makeProduit();

        $this->asAdmin()
             ->getJson("/api/admin/produits/{$produit->id}")
             ->assertStatus(200)
             ->assertJsonPath('data.produit.nom', 'Robe Rouge')
             ->assertJsonPath('data.produit.prix', 25000);
    }

    public function test_show_returns_404_for_unknown_product(): void
    {
        $this->asAdmin()
             ->getJson('/api/admin/produits/99999')
             ->assertStatus(404);
    }

    // ── toggleStatus ──────────────────────────────────────────────────────────

    public function test_toggle_status_hides_visible_product(): void
    {
        $produit = $this->makeProduit(['est_visible' => true]);

        $this->asAdmin()
             ->postJson("/api/admin/produits/{$produit->id}/toggle-status")
             ->assertStatus(200)
             ->assertJsonPath('data.produit.est_visible', false);

        $this->assertDatabaseHas('produits', ['id' => $produit->id, 'est_visible' => false]);
    }

    public function test_toggle_status_shows_hidden_product(): void
    {
        $produit = $this->makeProduit(['est_visible' => false]);

        $this->asAdmin()
             ->postJson("/api/admin/produits/{$produit->id}/toggle-status")
             ->assertStatus(200)
             ->assertJsonPath('data.produit.est_visible', true);
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_deletes_product_without_orders(): void
    {
        $produit = $this->makeProduit();

        $this->asAdmin()
             ->deleteJson("/api/admin/produits/{$produit->id}")
             ->assertStatus(200)
             ->assertJsonPath('success', true);

        $this->assertSoftDeleted('produits', ['id' => $produit->id]);
    }

    // ── stock status logic ────────────────────────────────────────────────────

    public function test_product_shows_out_of_stock_status(): void
    {
        $produit = $this->makeProduit(['stock_disponible' => 0]);

        $response = $this->asAdmin()->getJson("/api/admin/produits/{$produit->id}");

        $this->assertEquals('out_of_stock', $response->json('data.produit.stock_status.status'));
    }

    public function test_product_shows_low_stock_when_at_alert_threshold(): void
    {
        $produit = $this->makeProduit(['stock_disponible' => 2, 'seuil_alerte' => 2]);

        $response = $this->asAdmin()->getJson("/api/admin/produits/{$produit->id}");

        $this->assertEquals('low_stock', $response->json('data.produit.stock_status.status'));
    }

    public function test_product_shows_in_stock_above_threshold(): void
    {
        $produit = $this->makeProduit(['stock_disponible' => 10, 'seuil_alerte' => 2]);

        $response = $this->asAdmin()->getJson("/api/admin/produits/{$produit->id}");

        $this->assertEquals('in_stock', $response->json('data.produit.stock_status.status'));
    }

    // ── duplicate ─────────────────────────────────────────────────────────────

    public function test_duplicate_creates_copy_in_draft_mode(): void
    {
        $produit = $this->makeProduit(['nom' => 'Original', 'slug' => 'original', 'est_visible' => true]);

        $response = $this->asAdmin()->postJson("/api/admin/produits/{$produit->id}/duplicate");

        $response->assertStatus(200)
                 ->assertJsonPath('data.produit.est_visible', false);

        $this->assertStringContainsString('Copie', $response->json('data.produit.nom'));
        $this->assertDatabaseCount('produits', 2);
    }
}
