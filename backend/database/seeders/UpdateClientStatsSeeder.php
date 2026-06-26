<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Client;
use App\Models\Commande;
use Illuminate\Support\Facades\DB;

class UpdateClientStatsSeeder extends Seeder
{
    public function run(): void
    {
        $clients = Client::all();

        foreach ($clients as $client) {
            $commandes = Commande::where('client_id', $client->id)->get();
            
            $client->nombre_commandes = $commandes->count();
            $client->total_depense = $commandes->sum('montant_total');
            $client->panier_moyen = $commandes->count() > 0 ? $commandes->avg('montant_total') : 0;
            
            if ($commandes->count() > 0) {
                $client->derniere_commande = $commandes->max('created_at');
            }
            
            $client->save();

            if ($commandes->count() > 0) {
                $this->command->info("✅ {$client->prenom} {$client->nom}: {$commandes->count()} commandes, " . number_format($client->total_depense, 0) . " FCFA");
            }
        }

        $this->command->info('');
        $this->command->info('🎉 Stats de tous les clients mises à jour');
    }
}
