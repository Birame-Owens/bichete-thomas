<?php

namespace Database\Factories;

use App\Models\CategorieCoiffure;
use App\Models\Coiffure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coiffure>
 */
class CoiffureFactory extends Factory
{
    protected $model = Coiffure::class;

    public function definition(): array
    {
        return [
            'categorie_coiffure_id' => CategorieCoiffure::factory(),
            'nom' => fake()->unique()->words(3, true),
            'description' => null,
            'image' => null,
            'actif' => true,
        ];
    }
}
