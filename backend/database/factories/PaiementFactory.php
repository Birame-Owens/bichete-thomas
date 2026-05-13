<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Paiement;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Paiement>
 */
class PaiementFactory extends Factory
{
    protected $model = Paiement::class;

    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'client_id' => Client::factory(),
            'numero_recu' => 'BT-' . fake()->unique()->numerify('PAY-######'),
            'type' => 'acompte',
            'mode_paiement' => 'wave',
            'montant' => 5000,
            'devise' => 'FCFA',
            'statut' => 'en_attente',
            'date_paiement' => now(),
            'recu_envoye' => false,
        ];
    }
}
