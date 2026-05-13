<?php

namespace Database\Factories;

use App\Models\Coiffure;
use App\Models\VarianteCoiffure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VarianteCoiffure>
 */
class VarianteCoiffureFactory extends Factory
{
    protected $model = VarianteCoiffure::class;

    public function definition(): array
    {
        return [
            'coiffure_id' => Coiffure::factory(),
            'nom' => 'Standard',
            'prix' => fake()->randomElement([5000, 10000, 15000, 20000, 25000]),
            'duree_minutes' => fake()->randomElement([30, 60, 90, 120]),
            'actif' => true,
        ];
    }
}
