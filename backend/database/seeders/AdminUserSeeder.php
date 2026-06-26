<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'ndeya@admin.com'],
            [
                'name' => 'NDEYA Admin',
                'email' => 'ndeya@admin.com',
                'password' => Hash::make('password'),
                'telephone' => '+221770000000',
                'role' => 'admin',
                'statut' => 'actif',
                'nombre_connexions' => 0,
                'email_verified_at' => now(),
            ]
        );

        $clientUser = User::updateOrCreate(
            ['email' => 'client@ndeya.com'],
            [
                'name' => 'Client Demo',
                'email' => 'client@ndeya.com',
                'password' => Hash::make('password'),
                'telephone' => '+221770000001',
                'role' => 'client',
                'statut' => 'actif',
                'nombre_connexions' => 0,
                'email_verified_at' => now(),
            ]
        );

        Client::updateOrCreate(
            ['email' => 'client@ndeya.com'],
            [
                'user_id' => $clientUser->id,
                'nom' => 'Demo',
                'prenom' => 'Client',
                'telephone' => '+221770000001',
                'ville' => 'Dakar',
                'adresse_principale' => 'Dakar',
                'type_client' => 'nouveau',
                'accepte_whatsapp' => true,
                'accepte_email' => true,
                'accepte_promotions' => true,
            ]
        );

        $this->command?->info('Default users ready: ndeya@admin.com / password, client@ndeya.com / password');
    }
}
