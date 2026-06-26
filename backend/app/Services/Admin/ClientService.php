<?php

namespace App\Services\Admin;

use App\Models\Client;
use App\Models\Commande;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ClientService
{
    /**
     * Créer un nouveau client
     */
    public function createClient(array $data): Client
    {
        DB::beginTransaction();

        try {
            // Calculer le score de fidélité initial
            $data['score_fidelite'] = 0;
            $data['nombre_commandes'] = 0;
            $data['total_depense'] = 0;
            $data['panier_moyen'] = 0;
            $data['type_client'] = 'nouveau';
            $data['priorite'] = $data['priorite'] ?? 'normale';

            $client = Client::create($data);

            DB::commit();

            Log::info('Nouveau client créé', [
                'client_id' => $client->id,
                'nom' => $client->nom . ' ' . $client->prenom,
                'telephone' => $client->telephone,
                'user_id' => auth()->id()
            ]);

            return $client->load(['commandes']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur création client', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Mettre à jour un client
     */
    public function updateClient(Client $client, array $data): Client
    {
        DB::beginTransaction();

        try {
            $client->update($data);

            // Recalculer les statistiques si nécessaire
            $this->updateClientStats($client);

            DB::commit();

            Log::info('Client mis à jour', [
                'client_id' => $client->id,
                'nom' => $client->nom . ' ' . $client->prenom,
                'user_id' => auth()->id()
            ]);

            return $client->fresh(['commandes']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur mise à jour client', [
                'client_id' => $client->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtenir les statistiques des clients
     */
    public function getStatistics(): array
    {
        return [
            'total_clients' => Client::count(),
            'nouveaux_clients_mois' => Client::whereMonth('created_at', now()->month)->count(),
            'clients_actifs' => Client::where('derniere_visite', '>=', now()->subDays(30))->count(),
            'clients_vip' => Client::where('type_client', 'vip')->count(),
            'clients_whatsapp' => Client::where('accepte_whatsapp', true)->count(),
            'panier_moyen_global' => Client::avg('panier_moyen') ?? 0,
            'score_fidelite_moyen' => Client::avg('score_fidelite') ?? 0,
            'clients_par_ville' => Client::select('ville', DB::raw('count(*) as total'))
                ->groupBy('ville')
                ->pluck('total', 'ville')
                ->toArray(),
            'clients_par_type' => Client::select('type_client', DB::raw('count(*) as total'))
                ->groupBy('type_client')
                ->pluck('total', 'type_client')
                ->toArray()
        ];
    }

    /**
     * Obtenir les clients VIP
     */
    public function getClientsVIP(): \Illuminate\Database\Eloquent\Collection
    {
        return Client::where('type_client', 'vip')
            ->orWhere('score_fidelite', '>=', 1000)
            ->orderBy('total_depense', 'desc')
            ->get();
    }

    /**
     * Obtenir les clients inactifs
     */
    public function getClientsInactifs(): \Illuminate\Database\Eloquent\Collection
    {
        return Client::where('derniere_visite', '<', now()->subDays(90))
            ->orWhereNull('derniere_visite')
            ->orderBy('derniere_visite', 'asc')
            ->get();
    }

    /**
     * Mettre à jour les statistiques d'un client
     */
    public function updateClientStats(Client $client): void
    {
        $commandes = $client->commandes()->where('statut', 'livree')->get();
        
        $nombreCommandes = $commandes->count();
        $totalDepense = $commandes->sum('montant_total');
        $panierMoyen = $nombreCommandes > 0 ? $totalDepense / $nombreCommandes : 0;
        $derniereCommande = $commandes->sortByDesc('created_at')->first()?->created_at;

        // Déterminer le type de client
        $typeClient = $this->determineClientType($nombreCommandes, $totalDepense);
        
        // Calculer le score de fidélité
        $scoreFidelite = $this->calculateFidelityScore($nombreCommandes, $totalDepense, $client->created_at);

        $client->update([
            'nombre_commandes' => $nombreCommandes,
            'total_depense' => $totalDepense,
            'panier_moyen' => $panierMoyen,
            'derniere_commande' => $derniereCommande,
            'type_client' => $typeClient,
            'score_fidelite' => $scoreFidelite
        ]);
    }

    /**
     * Déterminer le type de client
     */
    private function determineClientType(int $nombreCommandes, float $totalDepense): string
    {
        if ($nombreCommandes >= 10 && $totalDepense >= 500000) {
            return 'vip';
        } elseif ($nombreCommandes >= 5 && $totalDepense >= 200000) {
            return 'fidele';
        } elseif ($nombreCommandes >= 2) {
            return 'regulier';
        } else {
            return 'nouveau';
        }
    }

    /**
     * Calculer le score de fidélité
     */
    private function calculateFidelityScore(int $nombreCommandes, float $totalDepense, $dateCreation): int
    {
        $score = 0;
        
        // Points pour le nombre de commandes
        $score += $nombreCommandes * 50;
        
        // Points pour le montant dépensé (1 point par 1000 FCFA)
        $score += intval($totalDepense / 1000);
        
        // Bonus ancienneté (10 points par mois)
        $moisAnciennete = now()->diffInMonths($dateCreation);
        $score += $moisAnciennete * 10;
        
        return $score;
    }

    /**
     * Envoyer un message WhatsApp à un client
     */
    public function sendWhatsAppMessage(Client $client, string $message, string $type = 'notification'): bool
    {
        if (!$client->accepte_whatsapp || !$client->telephone) {
            return false;
        }

        try {
            // Configuration de l'API WhatsApp (à adapter selon votre fournisseur)
            $response = Http::post(config('whatsapp.api_url'), [
                'phone' => $this->formatPhoneNumber($client->telephone),
                'message' => $message,
                'type' => $type
            ]);

            if ($response->successful()) {
                // Enregistrer le message dans l'historique
                $client->messages_whatsapps()->create([
                    'message' => $message,
                    'type' => $type,
                    'statut' => 'envoye',
                    'envoye_par' => auth()->id(),
                    'date_envoi' => now()
                ]);

                Log::info('Message WhatsApp envoyé', [
                    'client_id' => $client->id,
                    'telephone' => $client->telephone,
                    'type' => $type
                ]);

                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Erreur envoi WhatsApp', [
                'client_id' => $client->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Envoyer une notification de nouveauté à plusieurs clients
     */
    public function sendNoveltyNotification(string $message, array $clientIds = [], array $filters = []): array
    {
        $query = Client::where('accepte_whatsapp', true)
                      ->where('accepte_promotions', true);

        // Filtrer par IDs spécifiques
        if (!empty($clientIds)) {
            $query->whereIn('id', $clientIds);
        }

        // Appliquer les filtres
        if (!empty($filters)) {
            if (isset($filters['type_client'])) {
                $query->whereIn('type_client', (array) $filters['type_client']);
            }
            if (isset($filters['ville'])) {
                $query->whereIn('ville', (array) $filters['ville']);
            }
            if (isset($filters['score_fidelite_min'])) {
                $query->where('score_fidelite', '>=', $filters['score_fidelite_min']);
            }
        }

        $clients = $query->get();
        $results = [
            'total_clients' => $clients->count(),
            'envoyes' => 0,
            'echecs' => 0,
            'details' => []
        ];

        foreach ($clients as $client) {
            $personalizedMessage = $this->personalizeMessage($message, $client);
            $success = $this->sendWhatsAppMessage($client, $personalizedMessage, 'promotion');
            
            if ($success) {
                $results['envoyes']++;
            } else {
                $results['echecs']++;
            }

            $results['details'][] = [
                'client_id' => $client->id,
                'nom' => $client->nom . ' ' . $client->prenom,
                'telephone' => $client->telephone,
                'success' => $success
            ];
        }

        Log::info('Notification nouveauté envoyée', [
            'total_clients' => $results['total_clients'],
            'envoyes' => $results['envoyes'],
            'echecs' => $results['echecs'],
            'user_id' => auth()->id()
        ]);

        return $results;
    }

    /**
     * Personnaliser un message pour un client
     */
    private function personalizeMessage(string $message, Client $client): string
    {
        $placeholders = [
            '{nom}' => $client->prenom ?: $client->nom,
            '{nom_complet}' => $client->nom . ' ' . $client->prenom,
            '{score_fidelite}' => $client->score_fidelite,
            '{type_client}' => $this->getTypeClientLabel($client->type_client)
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $message);
    }

    /**
     * Formater le numéro de téléphone pour WhatsApp
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Nettoyer le numéro
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Ajouter l'indicatif du Sénégal si nécessaire
        if (strlen($phone) === 9 && substr($phone, 0, 1) === '7') {
            $phone = '221' . $phone;
        } elseif (strlen($phone) === 10 && substr($phone, 0, 2) === '07') {
            $phone = '221' . substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Obtenir le libellé du type de client
     */
    private function getTypeClientLabel(string $type): string
    {
        $labels = [
            'nouveau' => 'Nouveau',
            'regulier' => 'Régulier',
            'fidele' => 'Fidèle',
            'vip' => 'VIP'
        ];

        return $labels[$type] ?? $type;
    }

    /**
     * Rechercher des clients
     */
    public function searchClients(array $criteria): \Illuminate\Database\Eloquent\Collection
    {
        $query = Client::query();

        if (!empty($criteria['search'])) {
            $search = $criteria['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'ILIKE', "%{$search}%")
                  ->orWhere('prenom', 'ILIKE', "%{$search}%")
                  ->orWhere('telephone', 'ILIKE', "%{$search}%")
                  ->orWhere('email', 'ILIKE', "%{$search}%");
            });
        }

        if (!empty($criteria['type_client'])) {
            $query->where('type_client', $criteria['type_client']);
        }

        if (!empty($criteria['ville'])) {
            $query->where('ville', $criteria['ville']);
        }

        if (!empty($criteria['accepte_whatsapp'])) {
            $query->where('accepte_whatsapp', true);
        }

        return $query->with(['commandes' => function ($q) {
            $q->latest()->limit(5);
        }])->get();
    }
}