<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role'   => 'admin',
            'statut' => 'actif',
        ]);
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin, 'sanctum');
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

    // ── stats ─────────────────────────────────────────────────────────────────

    public function test_stats_returns_category_counts(): void
    {
        $this->makeCategory(['nom' => 'Robes', 'slug' => 'robes', 'est_active' => true]);
        $this->makeCategory(['nom' => 'Chaussures', 'slug' => 'chaussures', 'est_active' => false]);

        $this->asAdmin()
             ->getJson('/api/admin/categories/stats')
             ->assertStatus(200)
             ->assertJsonPath('success', true)
             ->assertJsonPath('data.total_categories', 2)
             ->assertJsonPath('data.categories_actives', 1);
    }

    public function test_stats_requires_authentication(): void
    {
        $this->getJson('/api/admin/categories/stats')
             ->assertStatus(401);
    }

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_index_lists_categories_with_pagination(): void
    {
        $this->makeCategory(['nom' => 'Robes', 'slug' => 'robes']);
        $this->makeCategory(['nom' => 'Parfums', 'slug' => 'parfums']);

        $response = $this->asAdmin()->getJson('/api/admin/categories');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['data' => ['categories', 'pagination']]);

        $this->assertCount(2, $response->json('data.categories'));
    }

    public function test_index_filters_by_type_parents(): void
    {
        $parent = $this->makeCategory(['nom' => 'Parent', 'slug' => 'parent']);
        $this->makeCategory(['nom' => 'Enfant', 'slug' => 'enfant', 'parent_id' => $parent->id]);

        $response = $this->asAdmin()->getJson('/api/admin/categories?type=parents');

        $response->assertStatus(200);
        $categories = $response->json('data.categories');
        // Only parent returned, plus sub_categories embedded
        $this->assertCount(1, $categories);
        $this->assertArrayHasKey('sous_categories', $categories[0]);
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_category_and_returns_201(): void
    {
        $response = $this->asAdmin()->postJson('/api/admin/categories', [
            'nom'           => 'Bijoux',
            'description'   => 'Tous les bijoux.',
            'est_active'    => true,
            'est_populaire' => false,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.category.nom', 'Bijoux');

        $this->assertDatabaseHas('categories', ['nom' => 'Bijoux', 'slug' => 'bijoux']);
    }

    public function test_store_generates_slug_from_nom(): void
    {
        $response = $this->asAdmin()->postJson('/api/admin/categories', [
            'nom'        => 'Bijoux & Accessoires',
            'est_active' => true,
        ]);

        $response->assertStatus(201);
        // Slug should be kebab-cased from nom
        $this->assertMatchesRegularExpression('/^bijoux/', $response->json('data.category.slug'));
    }

    public function test_store_requires_nom(): void
    {
        $this->asAdmin()
             ->postJson('/api/admin/categories', ['est_active' => true])
             ->assertStatus(422);
    }

    // ── show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_category_with_statistics(): void
    {
        $cat = $this->makeCategory();

        $this->asAdmin()
             ->getJson("/api/admin/categories/{$cat->id}")
             ->assertStatus(200)
             ->assertJsonPath('data.category.nom', 'Robes')
             ->assertJsonStructure(['data' => ['category' => ['statistics']]]);
    }

    public function test_show_returns_404_for_unknown_id(): void
    {
        $this->asAdmin()
             ->getJson('/api/admin/categories/99999')
             ->assertStatus(404);
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_category(): void
    {
        $cat = $this->makeCategory();

        $this->asAdmin()
             ->putJson("/api/admin/categories/{$cat->id}", [
                 'nom'           => 'Robes Modifiées',
                 'est_active'    => true,
                 'est_populaire' => false,
             ])
             ->assertStatus(200)
             ->assertJsonPath('data.category.nom', 'Robes Modifiées');

        $this->assertDatabaseHas('categories', ['id' => $cat->id, 'nom' => 'Robes Modifiées']);
    }

    // ── toggle-status ─────────────────────────────────────────────────────────

    public function test_toggle_status_inverts_est_active(): void
    {
        $cat = $this->makeCategory(['est_active' => true]);

        $this->asAdmin()
             ->postJson("/api/admin/categories/{$cat->id}/toggle-status")
             ->assertStatus(200)
             ->assertJsonPath('data.category.est_active', false);

        $this->assertDatabaseHas('categories', ['id' => $cat->id, 'est_active' => false]);
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_deletes_empty_category(): void
    {
        $cat = $this->makeCategory();

        $this->asAdmin()
             ->deleteJson("/api/admin/categories/{$cat->id}")
             ->assertStatus(200)
             ->assertJsonPath('success', true);

        $this->assertSoftDeleted('categories', ['id' => $cat->id]);
    }

    public function test_destroy_refuses_category_with_subcategories(): void
    {
        $parent = $this->makeCategory(['nom' => 'Parent', 'slug' => 'parent']);
        $this->makeCategory(['nom' => 'Enfant', 'slug' => 'enfant', 'parent_id' => $parent->id]);

        $this->asAdmin()
             ->deleteJson("/api/admin/categories/{$parent->id}")
             ->assertStatus(400)
             ->assertJsonPath('success', false);
    }
}
