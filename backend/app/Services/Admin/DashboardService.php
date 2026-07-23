<?php

namespace App\Services\Admin;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Produit;
use App\Models\Paiement;
use App\Models\Stock;
use App\Models\ArticlesCommande;
use App\Models\AvisClient;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardService
{
    /**
     * Obtenir les statistiques du dashboard (version corrigée)
     */
    public function getDashboardStats(): array
    {
        try {
            $today = Carbon::today();
            $thisMonth = Carbon::now()->startOfMonth();
            $lastMonth = Carbon::now()->subMonth()->startOfMonth();
            $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

            return [
                'overview' => $this->getOverviewStats($today, $thisMonth),
                'sales' => $this->getSalesStats($thisMonth, $lastMonth, $lastMonthEnd),
                'orders' => $this->getOrdersStatsSimple($today, $thisMonth),
                'products' => $this->getProductsStatsSimple(),
                'low_stock_products' => $this->getLowStockProductsSimple(),
                'popular_products' => $this->getPopularProductsSimple(),
                'recent_activities' => $this->getRecentActivitiesSimple()
            ];

        } catch (\Exception $e) {
            Log::error('Erreur getDashboardStats', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Statistiques générales d'aperçu
     */
    private function getOverviewStats(Carbon $today, Carbon $thisMonth): array
    {
        return [
            'total_clients' => Client::count(),
            'nouveaux_clients_mois' => Client::where('created_at', '>=', $thisMonth)->count(),
            'nouveaux_clients_aujourd_hui' => Client::whereDate('created_at', $today)->count(),
            'commandes_aujourd_hui' => Commande::whereDate('created_at', $today)->count(),
            'chiffre_affaires_mois' => $this->getMonthlyRevenue($thisMonth),
            'chiffre_affaires_aujourd_hui' => $this->getTodayRevenue()
        ];
    }

    /**
     * Statistiques des ventes avec comparaisons
     */
    private function getSalesStats(Carbon $thisMonth, Carbon $lastMonth, Carbon $lastMonthEnd): array
    {
        $currentMonthRevenue = $this->getMonthlyRevenue($thisMonth);
        $previousMonthRevenue = $this->getMonthlyRevenue($lastMonth, $lastMonthEnd);

        $growthPercentage = $previousMonthRevenue > 0 
            ? (($currentMonthRevenue - $previousMonthRevenue) / $previousMonthRevenue) * 100 
            : 0;

        return [
            'current_month' => round($currentMonthRevenue, 0),
            'previous_month' => round($previousMonthRevenue, 0),
            'growth_percentage' => round($growthPercentage, 2),
            'is_positive_growth' => $growthPercentage >= 0
        ];
    }

    /**
     * Statistiques des commandes
     */
    private function getOrdersStatsSimple(Carbon $today, Carbon $thisMonth): array
    {
        $totalMonth = Commande::where('created_at', '>=', $thisMonth)->count();
        $pending = Commande::where('statut', 'en_attente')->count();
        $confirmed = Commande::where('statut', 'confirmee')->count();
        $inProduction = Commande::where('statut', 'en_production')->count();
        $completed = Commande::where('statut', 'livree')
            ->where('created_at', '>=', $thisMonth)->count();
        $cancelled = Commande::where('statut', 'annulee')
            ->where('created_at', '>=', $thisMonth)->count();

        return [
            'total_month' => $totalMonth,
            'pending' => $pending,
            'confirmed' => $confirmed,
            'in_production' => $inProduction,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'completion_rate' => $totalMonth > 0 ? round(($completed / $totalMonth) * 100, 1) : 0,
            'cancellation_rate' => $totalMonth > 0 ? round(($cancelled / $totalMonth) * 100, 1) : 0,
            'orders_today' => Commande::whereDate('created_at', $today)->count(),
            'average_processing_days' => $this->getAverageProcessingTime()
        ];
    }

    /**
     * Temps moyen de traitement (compatible PostgreSQL)
     */
    private function getAverageProcessingTime(): float
    {
        try {
            $result = DB::table('commandes')
                ->whereNotNull('date_debut_production')
                ->whereNotNull('date_fin_production')
                ->where('created_at', '>=', Carbon::now()->subMonths(3))
                ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (date_fin_production - date_debut_production))/86400) as avg_days'))
                ->first();

            return round((float) ($result->avg_days ?? 0), 1);
            
        } catch (\Exception $e) {
            Log::warning('Erreur calcul temps production', ['error' => $e->getMessage()]);
            return 7.5; // Valeur par défaut
        }
    }

    /**
     * Statistiques des produits (corrigée pour votre structure)
     */
    private function getProductsStatsSimple(): array
    {
        try {
            // Utilisation des VRAIES colonnes de votre table produits
            $totalProduits = DB::table('produits')
                ->where('est_visible', true)
                ->whereNull('deleted_at')
                ->count();

            // Stock total basé sur stock_disponible
            $totalStock = DB::table('produits')
                ->where('est_visible', true)
                ->whereNull('deleted_at')
                ->sum('stock_disponible');

            // Stock faible basé sur seuil_alerte
            $lowStockCount = DB::table('produits')
                ->where('est_visible', true)
                ->whereNull('deleted_at')
                ->whereRaw('stock_disponible <= seuil_alerte')
                ->where('seuil_alerte', '>', 0)
                ->count();

            // Rupture de stock
            $outOfStockCount = DB::table('produits')
                ->where('est_visible', true)
                ->whereNull('deleted_at')
                ->where('stock_disponible', 0)
                ->count();

            // Valeur du stock
            $stockValue = DB::table('produits')
                ->where('est_visible', true)
                ->whereNull('deleted_at')
                ->selectRaw('SUM(stock_disponible * prix) as valeur_totale')
                ->value('valeur_totale') ?? 0;

            return [
                'total_produits' => $totalProduits,
                'total_stock' => (int) $totalStock,
                'low_stock' => (int) $lowStockCount,
                'out_of_stock' => (int) $outOfStockCount,
                'stock_value' => round($stockValue, 0)
            ];
            
        } catch (\Exception $e) {
            Log::warning('Erreur calcul stock', ['error' => $e->getMessage()]);
            return [
                'total_produits' => 0,
                'total_stock' => 0,
                'low_stock' => 0,
                'out_of_stock' => 0,
                'stock_value' => 0
            ];
        }
    }

    /**
     * Produits avec stock faible (corrigé)
     */
    private function getLowStockProductsSimple(int $limit = 10): array
    {
        try {
            return DB::table('produits as p')
                ->leftJoin('categories as c', 'p.categorie_id', '=', 'c.id')
                ->select([
                    'p.id',
                    'p.nom',
                    'p.prix',
                    'c.nom as category',
                    'p.stock_disponible as stock_actuel',
                    'p.seuil_alerte as stock_minimum'
                ])
                ->where('p.est_visible', true)
                ->whereNull('p.deleted_at')
                ->whereRaw('p.stock_disponible <= p.seuil_alerte')
                ->where('p.seuil_alerte', '>', 0)
                ->orderBy('p.stock_disponible', 'asc')
                ->limit($limit)
                ->get()
                ->map(function($item) {
                    return [
                        'id' => $item->id,
                        'nom' => $item->nom,
                        'category' => $item->category ?? 'Sans catégorie',
                        'prix_vente' => (float) $item->prix,
                        'stock_actuel' => (int) $item->stock_actuel,
                        'stock_minimum' => (int) $item->stock_minimum
                    ];
                })
                ->toArray();
                
        } catch (\Exception $e) {
            Log::warning('Erreur getLowStockProducts', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Produits les plus vendus
     */
    private function getPopularProductsSimple(int $limit = 10): array
    {
        try {
            $thirtyDaysAgo = Carbon::now()->subDays(30);

            $result = DB::table('articles_commande as ac')
                ->join('produits as p', 'ac.produit_id', '=', 'p.id')
                ->join('commandes as c', 'ac.commande_id', '=', 'c.id')
                ->leftJoin('categories as cat', 'p.categorie_id', '=', 'cat.id')
                ->select([
                    'p.id',
                    'p.nom',
                    'p.prix',
                    'cat.nom as category',
                    DB::raw('SUM(ac.quantite) as total_ventes'),
                    DB::raw('SUM(ac.prix_total_article) as chiffre_affaires')
                ])
                ->where('c.created_at', '>=', $thirtyDaysAgo)
                ->whereIn('c.statut', ['confirmee', 'en_production', 'livree'])
                ->where('p.est_visible', true)
                ->whereNull('p.deleted_at')
                ->groupBy('p.id', 'p.nom', 'p.prix', 'cat.nom')
                ->orderBy('total_ventes', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($item) {
                    return [
                        'id'              => $item->id,
                        'nom'             => $item->nom,
                        'prix'            => (float) $item->prix,
                        'category'        => $item->category ?? 'Sans catégorie',
                        'ventes'          => (int) $item->total_ventes,
                        'chiffre_affaires' => (float) $item->chiffre_affaires
                    ];
                })
                ->toArray();

            if (!empty($result)) {
                return $result;
            }

            // Fallback sur le compteur dénormalisé si aucune vente récente
            return DB::table('produits as p')
                ->leftJoin('categories as c', 'p.categorie_id', '=', 'c.id')
                ->select(['p.id', 'p.nom', 'p.prix', 'c.nom as category', 'p.nombre_ventes'])
                ->where('p.est_visible', true)
                ->whereNull('p.deleted_at')
                ->orderBy('p.nombre_ventes', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($item) {
                    return [
                        'id'              => $item->id,
                        'nom'             => $item->nom,
                        'prix'            => (float) $item->prix,
                        'category'        => $item->category ?? 'Sans catégorie',
                        'ventes'          => (int) $item->nombre_ventes,
                        'chiffre_affaires' => (float) ($item->nombre_ventes * $item->prix)
                    ];
                })
                ->toArray();

        } catch (\Exception $e) {
            Log::warning('Erreur getPopularProducts', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Activités récentes
     */
    private function getRecentActivitiesSimple(int $limit = 10): array
    {
        try {
            $sevenDaysAgo = Carbon::now()->subDays(7);
            $activities = collect();

            // Nouvelles commandes
            $recentOrders = Commande::where('created_at', '>=', $sevenDaysAgo)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function($order) {
                    return [
                        'type' => 'commande',
                        'title' => "Nouvelle commande #{$order->numero_commande}",
                        'description' => "Commande de {$order->montant_total} FCFA",
                        'date' => $order->created_at,
                        'amount' => $order->montant_total
                    ];
                });

            // Nouveaux clients
            $recentClients = Client::where('created_at', '>=', $sevenDaysAgo)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function($client) {
                    return [
                        'type' => 'client',
                        'title' => "Nouveau client",
                        'description' => "{$client->prenom} {$client->nom} s'est inscrit",
                        'date' => $client->created_at
                    ];
                });

            return $activities
                ->merge($recentOrders)
                ->merge($recentClients)
                ->sortByDesc('date')
                ->take($limit)
                ->values()
                ->toArray();

        } catch (\Exception $e) {
            Log::warning('Erreur getRecentActivities', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Stats rapides pour mise à jour temps réel
     */
    public function getQuickStats(): array
    {
        return [
            'commandes_en_attente' => Commande::where('statut', 'en_attente')->count(),
            'commandes_aujourd_hui' => Commande::whereDate('created_at', Carbon::today())->count(),
            'chiffre_affaires_aujourd_hui' => $this->getTodayRevenue(),
            'nouveaux_clients_semaine' => Client::where('created_at', '>=', Carbon::now()->startOfWeek())->count(),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Chiffre d'affaires mensuel
     */
    private function getMonthlyRevenue(Carbon $startDate, Carbon $endDate = null): float
    {
        try {
            $query = DB::table('paiements')
                ->where('statut', 'valide')
                ->where('created_at', '>=', $startDate);

            if ($endDate) {
                $query->where('created_at', '<=', $endDate);
            }

            // CA = montant encaissé MOINS les frais de livraison (argent du livreur).
            return (float) $query->sum('montant')
                - $this->fraisLivraisonPayes($startDate, $endDate);

        } catch (\Exception $e) {
            Log::warning('Erreur calcul revenue', ['error' => $e->getMessage()]);
            return 0.0;
        }
    }

    /**
     * Chiffre d'affaires du jour
     */
    private function getTodayRevenue(): float
    {
        try {
            $gross = (float) DB::table('paiements')
                ->where('statut', 'valide')
                ->whereDate('created_at', Carbon::today())
                ->sum('montant');

            $fraisLivraison = (float) DB::table('commandes')
                ->whereIn('commandes.id', function ($sub) {
                    $sub->select('commande_id')->from('paiements')
                        ->where('statut', 'valide')
                        ->whereDate('created_at', Carbon::today());
                })
                ->sum('frais_livraison');

            return $gross - $fraisLivraison;

        } catch (\Exception $e) {
            Log::warning('Erreur calcul today revenue', ['error' => $e->getMessage()]);
            return 0.0;
        }
    }

    /**
     * Total des frais de livraison des commandes ayant au moins un paiement validé
     * sur la période. Soustrait du CA : la livraison est l'argent du livreur, pas
     * le chiffre d'affaires de la boutique. Compté une seule fois par commande
     * (robuste même en cas d'acomptes / paiements multiples).
     */
    private function fraisLivraisonPayes(Carbon $startDate, Carbon $endDate = null): float
    {
        return (float) DB::table('commandes')
            ->whereIn('commandes.id', function ($sub) use ($startDate, $endDate) {
                $sub->select('commande_id')->from('paiements')
                    ->where('statut', 'valide')
                    ->where('created_at', '>=', $startDate);
                if ($endDate) {
                    $sub->where('created_at', '<=', $endDate);
                }
            })
            ->sum('frais_livraison');
    }
}