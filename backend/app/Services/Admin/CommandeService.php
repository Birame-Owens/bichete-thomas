<?php

namespace App\Services\Admin;

use App\Models\Commande;
use App\Models\ArticlesCommande;
use App\Models\Produit;
use App\Models\Client;
use App\Models\MesureClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommandeService
{
    /**
     * Créer une nouvelle commande
     */
    public function createCommande(array $data): Commande
    {
        DB::beginTransaction();

        try {
            $client = $this->resolveCommandeClient($data);

            // Calculer les montants
            $montants = $this->calculateMontants($data['articles'], $data['frais_livraison'] ?? 0, $data['remise'] ?? 0);
            
            // Générer le numéro de commande
            $numeroCommande = $this->generateNumeroCommande();
            
            // Créer la commande
            $commande = Commande::create([
                'numero_commande' => $numeroCommande,
                'client_id' => $client->id,
                'nom_destinataire' => $data['nom_destinataire'],
                'telephone_livraison' => $data['telephone_livraison'],
                'adresse_livraison' => $data['adresse_livraison'],
                'instructions_livraison' => $data['instructions_livraison'] ?? null,
                'mode_livraison' => $data['mode_livraison'] ?? 'domicile',
                'date_livraison_prevue' => $data['date_livraison_prevue'] ?? null,
                'sous_total' => $montants['sous_total'],
                'frais_livraison' => $data['frais_livraison'] ?? 0,
                'remise' => $data['remise'] ?? 0,
                'montant_total' => $montants['total'],
                'statut' => 'en_attente',
                'priorite' => $data['priorite'] ?? 'normale',
                'est_cadeau' => $data['est_cadeau'] ?? false,
                'message_cadeau' => $data['message_cadeau'] ?? null,
                'code_promo' => $data['code_promo'] ?? null,
                'notes_client' => $data['notes_client'] ?? null,
                'notes_admin' => $data['notes_admin'] ?? null,
                'source' => 'admin'
            ]);

            // Créer les articles de commande avec gestion des mesures
            $this->createArticlesCommande($commande, $data['articles'], $client->id);

            // Décrémenter le stock
            DB::commit();

            Log::info('Nouvelle commande créée', [
                'commande_id' => $commande->id,
                'numero_commande' => $commande->numero_commande,
                'montant_total' => $commande->montant_total,
                'user_id' => auth()->id()
            ]);

            return $commande->load(['client', 'articles_commandes.produit']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur création commande', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Mettre à jour une commande (seulement si non payée)
     */
    public function updateCommande(Commande $commande, array $data): Commande
    {
        // Vérifier si la commande peut être modifiée
        if ($this->isCommandePaid($commande)) {
            throw new \Exception('Impossible de modifier une commande déjà payée');
        }

        DB::beginTransaction();

        try {
            // Si les articles ont changé, recalculer les montants
            if (isset($data['articles'])) {
                $montants = $this->calculateMontants($data['articles'], $data['frais_livraison'] ?? 0, $data['remise'] ?? 0);
                
                // Remettre en stock les anciens articles
                // Supprimer les anciens articles
                $commande->articles_commandes()->delete();
                
                // Créer les nouveaux articles avec mesures
                $this->createArticlesCommande($commande, $data['articles'], $commande->client_id);
                
                // Décrémenter le stock pour les nouveaux articles
                $data['sous_total'] = $montants['sous_total'];
                $data['montant_total'] = $montants['total'];
            }

            $commande->update($data);

            DB::commit();

            Log::info('Commande mise à jour', [
                'commande_id' => $commande->id,
                'numero_commande' => $commande->numero_commande,
                'user_id' => auth()->id()
            ]);

            return $commande->fresh(['client', 'articles_commandes.produit']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur mise à jour commande', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Supprimer une commande (seulement si non payée)
     */
    public function deleteCommande(Commande $commande): bool
    {
        if ($this->isCommandePaid($commande)) {
            throw new \Exception('Impossible de supprimer une commande déjà payée');
        }

        DB::beginTransaction();

        try {
            // Supprimer les articles de commande
            $commande->articles_commandes()->delete();

            // Supprimer la commande
            $commande->delete();

            DB::commit();

            Log::info('Commande supprimée', [
                'commande_id' => $commande->id,
                'numero_commande' => $commande->numero_commande,
                'user_id' => auth()->id()
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur suppression commande', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Rechercher des commandes avec filtres avancés
     */
    public function searchCommandes(array $filters)
    {
        $query = Commande::with(['client', 'articles_commandes.produit'])
            ->orderBy('created_at', 'desc');

        // Recherche par numéro de commande
        if (!empty($filters['numero_commande'])) {
            $query->where('numero_commande', 'ILIKE', '%' . $filters['numero_commande'] . '%');
        }

        // Recherche par client
        if (!empty($filters['client_search'])) {
            $query->whereHas('client', function($q) use ($filters) {
                $q->where('nom', 'ILIKE', '%' . $filters['client_search'] . '%')
                  ->orWhere('prenom', 'ILIKE', '%' . $filters['client_search'] . '%')
                  ->orWhere('telephone', 'ILIKE', '%' . $filters['client_search'] . '%');
            });
        }

        // Recherche par produit
        if (!empty($filters['produit_search'])) {
            $query->whereHas('articles_commandes.produit', function($q) use ($filters) {
                $q->where('nom', 'ILIKE', '%' . $filters['produit_search'] . '%');
            });
        }

        // Filtrage par statut
        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        // Filtrage par date
        if (!empty($filters['date_debut'])) {
            $query->whereDate('created_at', '>=', $filters['date_debut']);
        }

        if (!empty($filters['date_fin'])) {
            $query->whereDate('created_at', '<=', $filters['date_fin']);
        }

        // Filtrage par priorité
        if (!empty($filters['priorite'])) {
            $query->where('priorite', $filters['priorite']);
        }

        return $query;
    }

    /**
     * Calculer les montants de la commande
     */
    public function calculateMontants(array $articles, float $fraisLivraison, float $remise = 0): array
    {
        $sousTotal = 0;
        
        foreach ($articles as $article) {
            $sousTotal += $article['quantite'] * $article['prix_unitaire'];
        }
        
        $total = $sousTotal + $fraisLivraison - $remise;
        
        return [
            'sous_total' => $sousTotal,
            'total' => max(0, $total)
        ];
    }

    /**
     * Générer un numéro de commande unique
     */
    public function generateNumeroCommande(): string
{
    $year = date('Y');
    $month = date('m');
    
    // Trouver le plus grand numéro existant
    $pattern = "CMD-{$year}{$month}-%";
    $lastNumber = Commande::withTrashed()
        ->where('numero_commande', 'LIKE', $pattern)
        ->max(DB::raw("CAST(SUBSTRING(numero_commande FROM '-([0-9]+)$') AS INTEGER)"));
    
    $nextNumber = ($lastNumber ?? 0) + 1;
    $numero = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    
    return "CMD-{$year}{$month}-{$numero}";
}
    /**
     * Obtenir les statistiques des commandes
     */
     public function getStatistics(): array
    {
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();
        $thisYear = now()->startOfYear();

        return [
            // Statistiques générales
            'total_commandes' => Commande::count(),
            'commandes_aujourd_hui' => Commande::whereDate('created_at', $today)->count(),
            'commandes_ce_mois' => Commande::where('created_at', '>=', $thisMonth)->count(),
            'commandes_cette_annee' => Commande::where('created_at', '>=', $thisYear)->count(),

            // Chiffre d'affaires basé sur les PAIEMENTS VALIDÉS
            'ca_total' => DB::table('paiements')
                ->where('statut', 'valide')
                ->sum('montant'),
            
            'ca_ce_mois' => DB::table('paiements')
                ->where('statut', 'valide')
                ->where('created_at', '>=', $thisMonth)
                ->sum('montant'),
                
            'ca_cette_annee' => DB::table('paiements')
                ->where('statut', 'valide')
                ->where('created_at', '>=', $thisYear)
                ->sum('montant'),

            // Commandes par statut
            'commandes_par_statut' => Commande::select('statut', DB::raw('count(*) as total'))
                ->groupBy('statut')
                ->pluck('total', 'statut')
                ->toArray(),

            // Commandes en attente d'action
            'commandes_en_attente' => Commande::where('statut', 'en_attente')->count(),
            'commandes_en_preparation' => Commande::where('statut', 'en_preparation')->count(),
            'commandes_pretes' => Commande::where('statut', 'prete')->count(),

            // Commandes payées (entièrement)
            'commandes_payees' => Commande::whereHas('paiements', function($query) {
                $query->where('statut', 'valide');
            })
            ->whereRaw('(SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE commande_id = commandes.id AND statut = \'valide\') >= montant_total')
            ->count(),

            // Commandes urgentes et en retard
            'commandes_urgentes' => Commande::whereIn('priorite', ['urgente', 'tres_urgente'])
                ->whereNotIn('statut', ['livree', 'annulee'])
                ->count(),
            'commandes_en_retard' => Commande::where('date_livraison_prevue', '<', now())
                ->whereNotIn('statut', ['livree', 'annulee'])
                ->count(),

            // Panier moyen basé sur les commandes PAYÉES
            'panier_moyen' => round(DB::table('paiements')
                ->where('statut', 'valide')
                ->avg('montant') ?? 0, 0),
            
            // Top clients ce mois (basé sur les paiements)
            'top_clients_mois' => DB::table('clients')
                ->leftJoin('commandes', 'clients.id', '=', 'commandes.client_id')
                ->leftJoin('paiements', function($join) use ($thisMonth) {
                    $join->on('commandes.id', '=', 'paiements.commande_id')
                         ->where('paiements.statut', 'valide')
                         ->where('paiements.created_at', '>=', $thisMonth);
                })
                // NB: la table clients du salon n'a pas de soft delete (deleted_at)
                ->whereNotNull('paiements.id')
                ->select(
                    'clients.id',
                    'clients.nom',
                    'clients.prenom',
                    'clients.telephone',
                    DB::raw('COUNT(DISTINCT paiements.id) as paiements_count'),
                    DB::raw('SUM(paiements.montant) as total_paye')
                )
                ->groupBy('clients.id', 'clients.nom', 'clients.prenom', 'clients.telephone')
                ->having(DB::raw('COUNT(DISTINCT paiements.id)'), '>', 0)
                ->orderBy('total_paye', 'desc')
                ->limit(5)
                ->get(),

            // Évolution mensuelle basée sur les paiements
            'evolution_mensuelle' => $this->getEvolutionMensuellePayments()
        ];
    }

    /**
     * Évolution mensuelle basée sur les paiements validés
     */
    private function getEvolutionMensuellePayments(): array
    {
        $evolution = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();
            
            $commandes = Commande::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
            
            // CA basé sur les paiements validés du mois
            $ca = DB::table('paiements')
                ->where('statut', 'valide')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->sum('montant');
            
            $evolution[] = [
                'mois' => $date->format('M Y'),
                'commandes' => $commandes,
                'chiffre_affaires' => $ca
            ];
        }
        
        return $evolution;
    }

    /**
     * Vérifier si une commande est payée
     */
    private function isCommandePaid(Commande $commande): bool
    {
        return $commande->paiements()
            ->where('statut', 'valide')
            ->sum('montant') >= $commande->montant_total;
    }

    // ========== MÉTHODES PRIVÉES ==========

    /**
     * Créer les articles de commande avec gestion des mesures
     */
    public function confirmCommandeStock(Commande $commande): void
    {
        if ($commande->stock_decremented_at) {
            return;
        }

        $commande->loadMissing('articles_commandes.produit');

        foreach ($commande->articles_commandes as $article) {
            $produit = $article->produit;

            if ($produit && $produit->gestion_stock) {
                $stockDisponible = $produit->stock_disponible;
                $couleur = trim((string) ($article->couleur_choisie ?? ''));
                $taille = trim((string) ($article->taille_choisie ?? ''));
                $stockMapRaw = $produit->couleur_tailles_stock;
                $stockMap = null;
                if (is_string($stockMapRaw) && $stockMapRaw !== '') {
                    $stockMap = json_decode($stockMapRaw, true);
                } elseif (is_array($stockMapRaw)) {
                    $stockMap = $stockMapRaw;
                }
                if (is_array($stockMap) && count($stockMap) > 0) {
                    $norm = function (string $value): string {
                        $value = trim($value);
                        return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
                    };
                    $normalized = [];
                    foreach ($stockMap as $colorName => $sizes) {
                        if (!is_array($sizes)) {
                            continue;
                        }
                        $sizeMap = [];
                        foreach ($sizes as $sizeKey => $count) {
                            $sizeMap[trim((string) $sizeKey)] = (int) $count;
                        }
                        $normalized[$norm((string) $colorName)] = $sizeMap;
                    }

                    $colorKey = $couleur !== '' ? $norm($couleur) : '';
                    if ($colorKey !== '' && $taille !== '') {
                        $stockDisponible = $normalized[$colorKey][$taille] ?? 0;
                    } elseif ($colorKey !== '') {
                        $stockDisponible = array_sum($normalized[$colorKey] ?? []);
                    } elseif ($taille !== '') {
                        $sum = 0;
                        foreach ($normalized as $sizes) {
                            $sum += $sizes[$taille] ?? 0;
                        }
                        $stockDisponible = $sum;
                    }
                }

                if ($stockDisponible < $article->quantite) {
                    throw new \Exception("Stock insuffisant pour {$produit->nom}. Disponible: {$stockDisponible}");
                }

                $produit->decrement('stock_disponible', $article->quantite);
                $produit->increment('nombre_ventes', $article->quantite);
            }
        }

        $commande->update(['stock_decremented_at' => now()]);
    }

    private function resolveCommandeClient(array $data): Client
    {
        if (!empty($data['client_id'])) {
            return Client::findOrFail($data['client_id']);
        }

        $clientData = $data['new_client'] ?? [];
        $phone = $this->normalizePhone($clientData['telephone'] ?? $data['telephone_livraison'] ?? '');
        $email = isset($clientData['email']) ? strtolower(trim($clientData['email'])) : null;

        $client = null;
        if ($email) {
            $client = Client::whereRaw('lower(email) = ?', [$email])->first();
        }

        if (!$client && $phone) {
            $client = Client::where('telephone', $phone)->first();
        }

        $names = $this->splitClientName($clientData['nom_complet'] ?? $data['nom_destinataire'] ?? '');

        $payload = [
            'nom' => $clientData['nom'] ?? $names['nom'],
            'prenom' => $clientData['prenom'] ?? $names['prenom'],
            'telephone' => $phone ?: ($clientData['telephone'] ?? $data['telephone_livraison']),
            'email' => $email,
            'adresse_principale' => $clientData['adresse'] ?? $data['adresse_livraison'],
            'ville' => $clientData['ville'] ?? 'Dakar',
            'indications_livraison' => $data['instructions_livraison'] ?? null,
            'derniere_visite' => now(),
        ];

        if ($client) {
            $client->update(array_filter($payload, fn ($value) => $value !== null && $value !== ''));
            return $client;
        }

        return Client::create(array_merge($payload, [
            'type_client' => 'nouveau',
            'accepte_whatsapp' => true,
            'accepte_email' => true,
            'accepte_sms' => true,
            'accepte_promotions' => true,
            'priorite' => 'normale',
        ]));
    }

    private function splitClientName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2);

        return [
            'prenom' => $parts[0] ?? 'Client',
            'nom' => $parts[1] ?? 'Boutique',
        ];
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (!$phone) {
            return null;
        }

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        if (str_starts_with($phone, '221')) {
            return '+' . $phone;
        }

        return '+221' . ltrim($phone, '0');
    }

    private function createArticlesCommande(Commande $commande, array $articles, int $clientId): void
    {
        foreach ($articles as $articleData) {
            $produit = Produit::find($articleData['produit_id']);
            
            $mesuresJson = null;
            $utiliseMesuresClient = false;

            // Gestion des mesures personnalisées
            if (!empty($articleData['mesures']) && is_array($articleData['mesures'])) {
                // Filtrer les mesures vides
                $mesuresFiltered = array_filter($articleData['mesures'], function($value) {
                    return !is_null($value) && $value !== '' && $value > 0;
                });

                if (!empty($mesuresFiltered)) {
                    $mesuresJson = json_encode($mesuresFiltered);
                    
                    // Sauvegarder dans mesures_clients si pas encore existant
                    $this->saveMesuresClient($clientId, $mesuresFiltered);
                }
            }
            // Sinon utiliser les mesures existantes du client
            elseif (!empty($articleData['utilise_mesures_client'])) {
                $mesuresClient = MesureClient::where('client_id', $clientId)->first();
                if ($mesuresClient) {
                    $mesuresArray = $mesuresClient->getMesuresRemplies();
                    if (!empty($mesuresArray)) {
                        $mesuresJson = json_encode($mesuresArray);
                        $utiliseMesuresClient = true;
                    }
                }
            }

            // Créer l'article de commande
            ArticlesCommande::create([
                'commande_id' => $commande->id,
                'produit_id' => $articleData['produit_id'],
                'nom_produit' => $produit->nom,
                'description_produit' => $produit->description,
                'quantite' => $articleData['quantite'],
                'prix_unitaire' => $articleData['prix_unitaire'],
                'prix_total_article' => $articleData['quantite'] * $articleData['prix_unitaire'],
                'taille_choisie' => $articleData['taille'] ?? null,
                'couleur_choisie' => $articleData['couleur'] ?? null,
                'mesures_client' => $mesuresJson,
                'demandes_personnalisation' => $articleData['instructions'] ?? null,
                'statut_production' => 'en_attente'
            ]);
        }
    }

    /**
     * Sauvegarder ou mettre à jour les mesures du client
     */
    private function saveMesuresClient(int $clientId, array $mesures): void
    {
        try {
            $mesureClient = MesureClient::where('client_id', $clientId)->first();
            
            $mesuresData = [
                'client_id' => $clientId,
                'date_prise_mesures' => now(),
                'mesures_valides' => true
            ];

            // Mapper les mesures
            $champsAutorises = [
                'epaule', 'poitrine', 'taille', 'longueur_robe', 'tour_bras',
                'tour_cuisses', 'longueur_jupe', 'ceinture', 'tour_fesses',
                'buste', 'longueur_manches_longues', 'longueur_manches_courtes',
                'longueur_short', 'cou', 'longueur_taille_basse'
            ];

            foreach ($champsAutorises as $champ) {
                if (isset($mesures[$champ]) && is_numeric($mesures[$champ])) {
                    $mesuresData[$champ] = floatval($mesures[$champ]);
                }
            }

            if ($mesureClient) {
                $mesureClient->update($mesuresData);
            } else {
                MesureClient::create($mesuresData);
            }

            Log::info('Mesures client sauvegardées', [
                'client_id' => $clientId,
                'action' => $mesureClient ? 'mise_a_jour' : 'creation'
            ]);

        } catch (\Exception $e) {
            Log::warning('Erreur sauvegarde mesures client', [
                'client_id' => $clientId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Décrémenter le stock des produits
     */
    private function decrementStock(array $articles): void
    {
        foreach ($articles as $article) {
            $produit = Produit::find($article['produit_id']);
            if ($produit && $produit->gestion_stock) {
                $produit->decrement('stock_disponible', $article['quantite']);
            }
        }
    }

    /**
     * Remettre en stock les produits
     */
    private function restoreStock($articlesCommande): void
    {
        foreach ($articlesCommande as $article) {
            if ($article->produit && $article->produit->gestion_stock) {
                $article->produit->increment('stock_disponible', $article->quantite);
            }
        }
    }
}
