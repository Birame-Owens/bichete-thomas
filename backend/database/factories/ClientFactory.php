<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            // Format E.164 senegalais : +221 77 + 6 chiffres uniques
            'telephone' => '+221770' . fake()->unique()->numerify('######'),
            'email' => null,
            'source' => 'en_ligne',
            'nombre_reservations_terminees' => 0,
            'fidelite_disponible' => false,
            'est_blackliste' => false,
        ];
    }
}
