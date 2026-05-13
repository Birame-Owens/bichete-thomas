<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'date_reservation' => fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            'heure_debut' => '10:00',
            'heure_fin' => '12:00',
            'duree_totale_minutes' => 120,
            'statut' => 'en_attente',
            'source' => 'en_ligne',
            'montant_total' => 20000,
            'montant_reduction' => 0,
            'montant_acompte' => 5000,
            'montant_restant' => 15000,
            'devise' => 'FCFA',
            'fidelite_appliquee' => false,
        ];
    }

    /** Reservation deja terminee (pour les tests d avis / invitations). */
    public function terminee(int $daysAgo = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'terminee',
            'date_reservation' => now()->subDays($daysAgo)->toDateString(),
            'heure_debut' => '10:00',
            'heure_fin' => '12:00',
            'terminee_at' => now()->subDays($daysAgo),
        ]);
    }
}
