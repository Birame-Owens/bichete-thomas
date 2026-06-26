<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        DB::beginTransaction();

        try {
            // Créer l'utilisateur
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => Hash::make('password123'),
                'role' => 'client',
                'statut' => 'actif'
            ]);

            // Créer le profil client associé
            $client = Client::create([
                'nom' => 'User',
                'prenom' => 'Test',
                'telephone' => '+221770000000',
                'email' => 'test@example.com',
                'ville' => 'Dakar',
                'adresse_principale' => 'Test Address, Dakar',
                'user_id' => $user->id,
                'type_client' => 'nouveau',
                'accepte_whatsapp' => true,
                'accepte_email' => true,
                'accepte_promotions' => true
            ]);

            DB::commit();

            $this->command->info('✅ User créé: test@example.com / password123');
            $this->command->info('Client ID: ' . $client->id);

            // Créer également un utilisateur pour Omar Seck (client existant avec commandes)
            $omarClient = Client::where('email', 'omar.seck@email.com')->first();
            
            if ($omarClient && !$omarClient->user_id) {
                $omarUser = User::create([
                    'name' => $omarClient->prenom . ' ' . $omarClient->nom,
                    'email' => $omarClient->email,
                    'password' => Hash::make('password123'),
                    'role' => 'client',
                    'statut' => 'actif'
                ]);

                $omarClient->user_id = $omarUser->id;
                $omarClient->save();

                $this->command->info('✅ User créé: ' . $omarClient->email . ' / password123');
                $this->command->info('Client ID: ' . $omarClient->id . ' (avec commandes existantes)');
            }

        } catch (\Exception $e) {
            DB::rollback();
            $this->command->error('❌ Erreur: ' . $e->getMessage());
        }
    }
}
