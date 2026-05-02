<?php

namespace Database\Seeders;

use App\Models\ParametreSysteme;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminRole = Role::query()->updateOrCreate(
            ['nom' => 'admin'],
            ['description' => 'Administrateur de la plateforme']
        );

        $geranteRole = Role::query()->updateOrCreate(
            ['nom' => 'gerante'],
            ['description' => 'Gerante du salon']
        );

        User::query()->updateOrCreate([
            'email' => env('ADMIN_EMAIL', 'admin@bichette-thomas.test'),
        ], [
            'role_id' => $adminRole->id,
            'name' => env('ADMIN_NAME', 'Administratrice'),
            'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
            'email_verified_at' => now(),
        ]);

        User::query()->updateOrCreate([
            'email' => env('GERANTE_EMAIL', 'gerante@bichette-thomas.test'),
        ], [
            'role_id' => $geranteRole->id,
            'name' => env('GERANTE_NAME', 'Gerante'),
            'password' => Hash::make(env('GERANTE_PASSWORD', 'password')),
            'email_verified_at' => now(),
        ]);

        foreach ($this->defaultSystemSettings() as $setting) {
            ParametreSysteme::query()->updateOrCreate(
                ['cle' => $setting['cle']],
                $setting
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultSystemSettings(): array
    {
        return [
            [
                'cle' => 'montant_acompte_defaut',
                'valeur' => ['value' => 5000],
                'type' => 'decimal',
                'description' => 'Montant fixe d acompte propose par defaut.',
                'modifiable' => true,
            ],
            [
                'cle' => 'pourcentage_acompte',
                'valeur' => ['value' => 30],
                'type' => 'decimal',
                'description' => 'Pourcentage d acompte applique sur le montant total.',
                'modifiable' => true,
            ],
            [
                'cle' => 'heure_ouverture',
                'valeur' => ['value' => '09:00'],
                'type' => 'time',
                'description' => 'Heure d ouverture du salon.',
                'modifiable' => true,
            ],
            [
                'cle' => 'heure_fermeture',
                'valeur' => ['value' => '19:00'],
                'type' => 'time',
                'description' => 'Heure de fermeture du salon.',
                'modifiable' => true,
            ],
            [
                'cle' => 'nombre_reservations_fidelite',
                'valeur' => ['value' => 9],
                'type' => 'integer',
                'description' => 'Nombre de reservations terminees avant recompense fidelite.',
                'modifiable' => true,
            ],
        ];
    }
}
