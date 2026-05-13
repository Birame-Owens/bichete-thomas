<?php

namespace Database\Factories;

use App\Models\Coiffure;
use App\Models\DetailReservation;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DetailReservation>
 */
class DetailReservationFactory extends Factory
{
    protected $model = DetailReservation::class;

    public function definition(): array
    {
        $coiffure = Coiffure::factory()->create();

        return [
            'reservation_id' => Reservation::factory(),
            'coiffure_id' => $coiffure->id,
            'coiffure_nom' => $coiffure->nom,
            'variante_nom' => 'Standard',
            'prix_unitaire' => 20000,
            'duree_minutes' => 120,
            'quantite' => 1,
            'option_ids' => [],
            'montant_options' => 0,
            'montant_total' => 20000,
            'ordre' => 1,
        ];
    }
}
