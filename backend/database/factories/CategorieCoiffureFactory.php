<?php

namespace Database\Factories;

use App\Models\CategorieCoiffure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategorieCoiffure>
 */
class CategorieCoiffureFactory extends Factory
{
    protected $model = CategorieCoiffure::class;

    public function definition(): array
    {
        return [
            'nom' => fake()->unique()->words(2, true),
            'description' => null,
            'image' => null,
            'actif' => true,
        ];
    }
}
