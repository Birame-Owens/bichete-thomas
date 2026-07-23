<?php

namespace Tests\Unit;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryModelTest extends TestCase
{
    use RefreshDatabase;

    private function cat(array $data): Category
    {
        return Category::create(array_merge([
            'nom'             => 'Test',
            'slug'            => uniqid('cat-'),
            'est_active'      => true,
            'est_populaire'   => false,
            'ordre_affichage' => 1,
        ], $data));
    }

    public function test_category_has_subcategories_relationship(): void
    {
        $parent = $this->cat(['nom' => 'Parent', 'slug' => 'parent']);
        $child  = $this->cat(['nom' => 'Enfant', 'slug' => 'enfant', 'parent_id' => $parent->id]);

        $this->assertCount(1, $parent->fresh()->categories);
        $this->assertEquals($child->id, $parent->fresh()->categories->first()->id);
    }

    public function test_category_belongs_to_parent(): void
    {
        $parent = $this->cat(['nom' => 'Parent', 'slug' => 'parent']);
        $child  = $this->cat(['nom' => 'Enfant', 'slug' => 'enfant', 'parent_id' => $parent->id]);

        $this->assertEquals($parent->id, $child->fresh()->category->id);
    }

    public function test_root_category_has_no_parent(): void
    {
        $cat = $this->cat(['nom' => 'Root', 'slug' => 'root']);

        $this->assertNull($cat->fresh()->category);
    }

    public function test_soft_delete_hides_category(): void
    {
        $cat = $this->cat(['nom' => 'A supprimer', 'slug' => 'a-supprimer']);
        $id  = $cat->id;

        $cat->delete();

        $this->assertNull(Category::find($id));
        $this->assertNotNull(Category::withTrashed()->find($id));
    }

    public function test_est_active_cast_to_bool(): void
    {
        $active   = $this->cat(['nom' => 'Actif', 'slug' => 'actif', 'est_active' => true]);
        $inactive = $this->cat(['nom' => 'Inactif', 'slug' => 'inactif', 'est_active' => false]);

        $this->assertIsBool($active->fresh()->est_active);
        $this->assertTrue($active->fresh()->est_active);
        $this->assertFalse($inactive->fresh()->est_active);
    }
}
