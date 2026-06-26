<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class LinkExistingClientsSeeder extends Seeder
{
    public function run(): void
    {
        DB::beginTransaction();

        try {
            // Omar Seck - 8 commandes
            $omarUser = User::create([
                'name' => 'Omar Seck',
                'email' => 'omar.seck@email.com',
                'password' => Hash::make('password123'),
                'role' => 'client',
                'statut' => 'actif'
            ]);

            $omarClient = Client::find(2);
            $omarClient->user_id = $omarUser->id;
            $omarClient->save();

            $this->command->info('✅ Omar Seck: omar.seck@email.com / password123');

            // Fatimata Kane - 3 commandes
            $fatiUser = User::create([
                'name' => 'Fatimata Kane',
                'email' => 'fatimata.kane@email.com',
                'password' => Hash::make('password123'),
                'role' => 'client',
                'statut' => 'actif'
            ]);

            $fatiClient = Client::find(3);
            $fatiClient->user_id = $fatiUser->id;
            $fatiClient->save();

            $this->command->info('✅ Fatimata Kane: fatimata.kane@email.com / password123');

            // Aminata Diallo - 5 commandes
            $aminataUser = User::create([
                'name' => 'Aminata Diallo',
                'email' => 'aminata.diallo@email.com',
                'password' => Hash::make('password123'),
                'role' => 'client',
                'statut' => 'actif'
            ]);

            $aminataClient = Client::find(1);
            $aminataClient->user_id = $aminataUser->id;
            $aminataClient->save();

            $this->command->info('✅ Aminata Diallo: aminata.diallo@email.com / password123');

            DB::commit();

            $this->command->info('');
            $this->command->info('🎉 Tous les clients avec commandes ont maintenant un compte User');
            $this->command->info('Mot de passe pour tous: password123');

        } catch (\Exception $e) {
            DB::rollback();
            $this->command->error('❌ Erreur: ' . $e->getMessage());
        }
    }
}
