<?php

namespace App\Services\Admin;

use App\Models\Commande;
use App\Models\Client;
use App\Models\Produit;
use App\Models\Paiement;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RapportService
{
    /**
     * Dashboard avec KPIs principaux
     */
    public function getDashboardKPIs(): array
    {
        try {
            $dateActuelle = now();
            $debutMoisActuel = $dateActuelle->copy()->startOfMonth();
            $finMoisActuel = $dateActuelle->copy()->endOfMonth();
            $debutMoisPrecedent = $debutMoisActuel->copy()->subMonth();
            $finMoisPrecedent = $finMoisActuel->copy()->subMonth();

            // CA ce mois vs mois précédent
            // CA = montant encaissé MOINS les frais de livraison (argent du livreur)
            $caCeMois = (float) (DB::table('paiements as p')
                ->join('commandes as c', 'p.commande_id', '=', 'c.id')
                ->where('p.statut', 'valide')
                ->whereBetween('p.created_at', [$debutMoisActuel, $finMoisActuel])
                ->sum(DB::raw('p.montant - c.frais_livraison')) ?? 0);

            $caMoisPrecedent = (float) (DB::table('paiements as p')
                ->join('commandes as c', 'p.commande_id', '=', 'c.id')
                ->where('p.statut', 'valide')
                ->whereBetween('p.created_at', [$debutMoisPrecedent, $finMoisPrecedent])
                ->sum(DB::raw('p.montant - c.frais_livraison')) ?? 0);

            // Commandes ce mois vs mois précédent
            $commandesCeMois = DB::table('commandes')
                ->whereBetween('created_at', [$debutMoisActuel, $finMoisActuel])
                ->count();

            $commandesMoisPrecedent = DB::table('commandes')
                ->whereBetween('created_at', [$debutMoisPrecedent, $finMoisPrecedent])
                ->count();

            // Nouveaux clients ce mois
            $nouveauxClients = DB::table('clients')
                ->whereBetween('created_at', [$debutMoisActuel, $finMoisActuel])
                ->count();

            $nouveauxClientsMoisPrecedent = DB::table('clients')
                ->whereBetween('created_at', [$debutMoisPrecedent, $finMoisPrecedent])
                ->count();

            // Panier moyen
            $panierMoyen = $commandesCeMois > 0 ? $caCeMois / $commandesCeMois : 0;
            $panierMoyenPrecedent = $commandesMoisPrecedent > 0 ? $caMoisPrecedent / $commandesMoisPrecedent : 0;

            // Calcul des évolutions
            $evolutionCa = $caMoisPrecedent > 0 ? (($caCeMois - $caMoisPrecedent) / $caMoisPrecedent) * 100 : 0;
            $evolutionCommandes = $commandesMoisPrecedent > 0 ? (($commandesCeMois - $commandesMoisPrecedent) / $commandesMoisPrecedent) * 100 : 0;
            $evolutionClients = $nouveauxClientsMoisPrecedent > 0 ? (($nouveauxClients - $nouveauxClientsMoisPrecedent) / $nouveauxClientsMoisPrecedent) * 100 : 0;
            $evolutionPanier = $panierMoyenPrecedent > 0 ? (($panierMoyen - $panierMoyenPrecedent) / $panierMoyenPrecedent) * 100 : 0;

            return [
                'ca_ce_mois' => (float)$caCeMois,
                'evolution_ca' => round($evolutionCa, 1),
                'commandes_ce_mois' => $commandesCeMois,
                'evolution_commandes' => round($evolutionCommandes, 1),
                'nouveaux_clients' => $nouveauxClients,
                'evolution_clients' => round($evolutionClients, 1),
                'panier_moyen' => (float)$panierMoyen,
                'evolution_panier' => round($evolutionPanier, 1)
            ];

        } catch (\Exception $e) {
            Log::error('Erreur dashboard KPIs', ['error' => $e->getMessage()]);
            return [
                'ca_ce_mois' => 0,
                'evolution_ca' => 0,
                'commandes_ce_mois' => 0,
                'evolution_commandes' => 0,
                'nouveaux_clients' => 0,
                'evolution_clients' => 0,
                'panier_moyen' => 0,
                'evolution_panier' => 0
            ];
        }
    }

    /**
     * Obtenir les alertes système
     */
    public function getAlertes(): array
    {
        try {
            $alertes = [];

            // Alertes de commandes en retard
            $commandesRetard = DB::table('commandes')
                ->where('date_livraison_prevue', '<', now())
                ->whereNotIn('statut', ['livree', 'annulee'])
                ->count();

            if ($commandesRetard > 0) {
                $alertes[] = [
                    'niveau' => 'warning',
                    'titre' => 'Commandes en retard',
                    'message' => "{$commandesRetard} commande(s) en retard de livraison",
                    'date' => now()->format('d/m/Y H:i'),
                    'type' => 'commandes'
                ];
            }

            // Alertes de paiements en attente
            $paiementsAttente = DB::table('paiements')
                ->where('statut', 'en_attente')
                ->where('created_at', '<', now()->subHours(24))
                ->count();

            if ($paiementsAttente > 0) {
                $alertes[] = [
                    'niveau' => 'info',
                    'titre' => 'Paiements en attente',
                    'message' => "{$paiementsAttente} paiement(s) en attente depuis plus de 24h",
                    'date' => now()->format('d/m/Y H:i'),
                    'type' => 'paiements'
                ];
            }

            // Alertes de stock faible — variantes (couleur_tailles_stock)
            $variantStockFaible = 0;
            $produitsVariantes = DB::table('produits')
                ->whereNotNull('couleur_tailles_stock')
                ->where('est_visible', true)
                ->select('couleur_tailles_stock', 'couleur_tailles_seuil', 'seuil_alerte')
                ->get();

            foreach ($produitsVariantes as $p) {
                $stock = json_decode($p->couleur_tailles_stock, true) ?? [];
                $seuilData = $p->couleur_tailles_seuil ? json_decode($p->couleur_tailles_seuil, true) : [];
                $seuilGlobal = $p->seuil_alerte ?? 5;
                $alerte = false;
                foreach ($stock as $couleur => $tailles) {
                    if ($alerte) break;
                    foreach ($tailles as $taille => $qty) {
                        $seuil = $seuilData[$couleur][$taille] ?? $seuilGlobal;
                        if ($qty > 0 && $qty <= $seuil) { $alerte = true; break; }
                    }
                }
                if ($alerte) $variantStockFaible++;
            }

            // Produits sans variantes avec stock faible
            $stockFaibleSansVariante = DB::table('produits')
                ->whereNull('couleur_tailles_stock')
                ->whereColumn('stock_disponible', '<=', 'seuil_alerte')
                ->where('seuil_alerte', '>', 0)
                ->where('est_visible', true)
                ->count();

            $stockFaible = $variantStockFaible + $stockFaibleSansVariante;

            if ($stockFaible > 0) {
                $alertes[] = [
                    'niveau' => 'warning',
                    'titre' => 'Stock faible',
                    'message' => "{$stockFaible} produit(s) avec un stock en alerte",
                    'date' => now()->format('d/m/Y H:i'),
                    'type' => 'stock'
                ];
            }

            return $alertes;

        } catch (\Exception $e) {
            Log::error('Erreur alertes', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Analyser les tendances
     */
    public function analyserTendances(string $type, Carbon $dateDebut, Carbon $dateFin): array
    {
        try {
            switch ($type) {
                case 'ventes':
                    return $this->analyserTendancesVentes($dateDebut, $dateFin);
                default:
                    return ['evolution' => []];
            }

        } catch (\Exception $e) {
            Log::error('Erreur analyse tendances', ['error' => $e->getMessage()]);
            return ['evolution' => []];
        }
    }

    /**
     * Analyser les tendances de ventes
     */
    private function analyserTendancesVentes(Carbon $dateDebut, Carbon $dateFin): array
    {
        try {
            $evolution = DB::table('paiements as p')
                ->join('commandes as c', 'p.commande_id', '=', 'c.id')
                ->where('p.statut', 'valide')
                ->whereBetween('p.created_at', [$dateDebut, $dateFin])
                ->select([
                    DB::raw("TO_CHAR(p.created_at, 'YYYY-MM') as periode"),
                    DB::raw('SUM(p.montant - c.frais_livraison) as chiffre_affaires'),
                    DB::raw('COUNT(DISTINCT c.id) as nombre_commandes')
                ])
                ->groupBy(DB::raw("TO_CHAR(p.created_at, 'YYYY-MM')"))
                ->orderBy('periode')
                ->get();

            return [
                'evolution' => $evolution->toArray()
            ];

        } catch (\Exception $e) {
            Log::error('Erreur tendances ventes', ['error' => $e->getMessage()]);
            return ['evolution' => []];
        }
    }

    /**
     * Rapport des ventes par période
     */
    public function getVentesReport(Carbon $dateDebut, Carbon $dateFin, string $groupBy = 'day'): array
    {
        try {
            $dateFormat = match($groupBy) {
                'day' => 'YYYY-MM-DD',
                'week' => 'YYYY-IW',
                'month' => 'YYYY-MM',
                'year' => 'YYYY',
                default => 'YYYY-MM-DD'
            };

            $query = DB::table('paiements as p')
                ->join('commandes as c', 'p.commande_id', '=', 'c.id')
                ->where('p.statut', 'valide')
                ->whereBetween('p.created_at', [$dateDebut, $dateFin]);

            $ventes = $query
                ->select([
                    DB::raw("TO_CHAR(p.created_at, '{$dateFormat}') as periode"),
                    DB::raw('COUNT(DISTINCT c.id) as nombre_commandes'),
                    DB::raw('SUM(p.montant - c.frais_livraison) as chiffre_affaires'),
                    DB::raw('AVG(p.montant - c.frais_livraison) as panier_moyen'),
                    DB::raw('COUNT(DISTINCT c.client_id) as clients_uniques')
                ])
                ->groupBy(DB::raw("TO_CHAR(p.created_at, '{$dateFormat}')"))
                ->orderBy('periode')
                ->get();

            // Calculs supplémentaires
            $totaux = [
                'total_ca' => $ventes->sum('chiffre_affaires'),
                'total_commandes' => $ventes->sum('nombre_commandes'),
                'panier_moyen_global' => $ventes->isNotEmpty() ? $ventes->avg('panier_moyen') : 0,
                'total_clients_uniques' => $ventes->sum('clients_uniques'),
                'periode_debut' => $dateDebut->format('d/m/Y'),
                'periode_fin' => $dateFin->format('d/m/Y')
            ];

            return [
                'ventes' => $ventes->toArray(),
                'totaux' => $totaux,
                'graphique_data' => $this->formatForChart($ventes)
            ];

        } catch (\Exception $e) {
            Log::error('Erreur rapport ventes', ['error' => $e->getMessage()]);
            return [
                'ventes' => [],
                'totaux' => [
                    'total_ca' => 0,
                    'total_commandes' => 0,
                    'panier_moyen_global' => 0,
                    'total_clients_uniques' => 0,
                    'periode_debut' => $dateDebut->format('d/m/Y'),
                    'periode_fin' => $dateFin->format('d/m/Y')
                ],
                'graphique_data' => []
            ];
        }
    }

    /**
     * Rapport des produits les plus vendus
     */
    public function getProduitsReport(Carbon $dateDebut, Carbon $dateFin, int $limit = 20): array
    {
        try {
            $produits = DB::table('articles_commande as ac')
                ->join('produits as p', 'ac.produit_id', '=', 'p.id')
                ->join('commandes as c', 'ac.commande_id', '=', 'c.id')
                ->join('categories as cat', 'p.categorie_id', '=', 'cat.id')
                ->whereBetween('c.created_at', [$dateDebut, $dateFin])
                ->whereIn('c.statut', ['confirmee', 'en_preparation', 'prete', 'livree'])
                ->select([
                    'p.id',
                    'p.nom',
                    'cat.nom as categorie',
                    'p.prix',
                    DB::raw('SUM(ac.quantite) as total_vendu'),
                    DB::raw('SUM(ac.prix_total_article) as chiffre_affaires'),
                    DB::raw('COUNT(DISTINCT c.id) as nombre_commandes'),
                    DB::raw('AVG(ac.prix_unitaire) as prix_moyen')
                ])
                ->groupBy('p.id', 'p.nom', 'cat.nom', 'p.prix')
                ->orderBy('total_vendu', 'desc')
                ->limit($limit)
                ->get();

            // Analyse par catégorie
            $categories = DB::table('articles_commande as ac')
                ->join('produits as p', 'ac.produit_id', '=', 'p.id')
                ->join('commandes as c', 'ac.commande_id', '=', 'c.id')
                ->join('categories as cat', 'p.categorie_id', '=', 'cat.id')
                ->whereBetween('c.created_at', [$dateDebut, $dateFin])
                ->whereIn('c.statut', ['confirmee', 'en_preparation', 'prete', 'livree'])
                ->select([
                    'cat.nom as categorie',
                    DB::raw('SUM(ac.quantite) as total_vendu'),
                    DB::raw('SUM(ac.prix_total_article) as chiffre_affaires'),
                    DB::raw('COUNT(DISTINCT p.id) as produits_vendus')
                ])
                ->groupBy('cat.nom')
                ->orderBy('chiffre_affaires', 'desc')
                ->get();

            return [
                'produits' => $produits->toArray(),
                'categories' => $categories->toArray(),
                'periode' => [
                    'debut' => $dateDebut->format('d/m/Y'),
                    'fin' => $dateFin->format('d/m/Y')
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Erreur rapport produits', ['error' => $e->getMessage()]);
            return [
                'produits' => [],
                'categories' => [],
                'periode' => [
                    'debut' => $dateDebut->format('d/m/Y'),
                    'fin' => $dateFin->format('d/m/Y')
                ]
            ];
        }
    }

    /**
     * Rapport des clients
     */
    public function getClientsReport(Carbon $dateDebut, Carbon $dateFin): array
    {
        try {
            // Clients les plus actifs
            $topClients = DB::table('clients as cl')
                ->join('commandes as c', 'cl.id', '=', 'c.client_id')
                ->join('paiements as p', 'c.id', '=', 'p.commande_id')
                ->where('p.statut', 'valide')
                ->whereBetween('p.created_at', [$dateDebut, $dateFin])
                ->select([
                    'cl.id',
                    'cl.nom',
                    'cl.prenom',
                    'cl.telephone',
                    'cl.ville',
                    DB::raw('COUNT(DISTINCT c.id) as nombre_commandes'),
                    DB::raw('SUM(p.montant) as total_depense'),
                    DB::raw('AVG(p.montant) as panier_moyen'),
                    DB::raw('MAX(p.created_at) as derniere_commande')
                ])
                ->groupBy('cl.id', 'cl.nom', 'cl.prenom', 'cl.telephone', 'cl.ville')
                ->orderBy('total_depense', 'desc')
                ->limit(20)
                ->get();

            // Nouveaux clients
            $nouveauxClients = Client::whereBetween('created_at', [$dateDebut, $dateFin])
                ->select(['nom', 'prenom', 'telephone', 'ville', 'created_at'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Segmentation par ville
            $clientsParVille = DB::table('clients as cl')
                ->join('commandes as c', 'cl.id', '=', 'c.client_id')
                ->join('paiements as p', 'c.id', '=', 'p.commande_id')
                ->where('p.statut', 'valide')
                ->whereBetween('p.created_at', [$dateDebut, $dateFin])
                ->select([
                    'cl.ville',
                    DB::raw('COUNT(DISTINCT cl.id) as nombre_clients'),
                    DB::raw('COUNT(DISTINCT c.id) as nombre_commandes'),
                    DB::raw('SUM(p.montant - c.frais_livraison) as chiffre_affaires')
                ])
                ->groupBy('cl.ville')
                ->orderBy('chiffre_affaires', 'desc')
                ->get();

            return [
                'top_clients' => $topClients->toArray(),
                'nouveaux_clients' => $nouveauxClients->toArray(),
                'repartition_villes' => $clientsParVille->toArray(),
                'statistiques' => [
                    'total_nouveaux' => $nouveauxClients->count(),
                    'total_actifs' => $topClients->count(),
                    'ca_moyen_par_client' => $topClients->isNotEmpty() ? $topClients->avg('total_depense') : 0
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Erreur rapport clients', ['error' => $e->getMessage()]);
            return [
                'top_clients' => [],
                'nouveaux_clients' => [],
                'repartition_villes' => [],
                'statistiques' => [
                    'total_nouveaux' => 0,
                    'total_actifs' => 0,
                    'ca_moyen_par_client' => 0
                ]
            ];
        }
    }

    /**
     * Rapport financier détaillé
     */
    public function getFinancierReport(Carbon $dateDebut, Carbon $dateFin): array
    {
        try {
            // Chiffre d'affaires par méthode de paiement
            $paiementsParMethode = DB::table('paiements')
                ->where('statut', 'valide')
                ->whereBetween('created_at', [$dateDebut, $dateFin])
                ->select([
                    'methode_paiement',
                    DB::raw('COUNT(*) as nombre_transactions'),
                    DB::raw('SUM(montant) as total_montant'),
                    DB::raw('AVG(montant) as montant_moyen')
                ])
                ->groupBy('methode_paiement')
                ->orderBy('total_montant', 'desc')
                ->get();

            // Evolution quotidienne — CA hors frais de livraison
            $evolutionQuotidienne = DB::table('paiements as p')
                ->join('commandes as c', 'p.commande_id', '=', 'c.id')
                ->where('p.statut', 'valide')
                ->whereBetween('p.created_at', [$dateDebut, $dateFin])
                ->select([
                    DB::raw("TO_CHAR(p.created_at, 'YYYY-MM-DD') as date"),
                    DB::raw('COUNT(*) as nombre_paiements'),
                    DB::raw('SUM(p.montant - c.frais_livraison) as chiffre_affaires')
                ])
                ->groupBy(DB::raw("TO_CHAR(p.created_at, 'YYYY-MM-DD')"))
                ->orderBy('date')
                ->get();

            // Commandes non payées
            $commandesNonPayees = DB::table('commandes as c')
                ->leftJoin('paiements as p', function($join) {
                    $join->on('c.id', '=', 'p.commande_id')
                         ->where('p.statut', 'valide');
                })
                ->whereNotIn('c.statut', ['annulee'])
                ->whereNull('p.id')
                ->select([
                    'c.numero_commande',
                    'c.nom_destinataire',
                    'c.montant_total',
                    'c.created_at',
                    DB::raw('c.montant_total as montant_restant')
                ])
                ->orderBy('c.created_at', 'desc')
                ->get();

            $totaux = [
                'ca_total' => $paiementsParMethode->sum('total_montant'),
                'nombre_transactions' => $paiementsParMethode->sum('nombre_transactions'),
                'ticket_moyen' => $paiementsParMethode->isNotEmpty() ? $paiementsParMethode->avg('montant_moyen') : 0,
                'total_impaye' => $commandesNonPayees->sum('montant_restant')
            ];

            return [
                'paiements_par_methode' => $paiementsParMethode->toArray(),
                'evolution_quotidienne' => $evolutionQuotidienne->toArray(),
                'commandes_non_payees' => $commandesNonPayees->toArray(),
                'totaux' => $totaux
            ];

        } catch (\Exception $e) {
            Log::error('Erreur rapport financier', ['error' => $e->getMessage()]);
            return [
                'paiements_par_methode' => [],
                'evolution_quotidienne' => [],
                'commandes_non_payees' => [],
                'totaux' => [
                    'ca_total' => 0,
                    'nombre_transactions' => 0,
                    'ticket_moyen' => 0,
                    'total_impaye' => 0
                ]
            ];
        }
    }

    /**
     * Rapport des commandes par statut et période
     */
    public function getCommandesReport(Carbon $dateDebut, Carbon $dateFin, string $statut = null): array
    {
        try {
            $query = DB::table('commandes')
                ->whereBetween('created_at', [$dateDebut, $dateFin]);

            if ($statut) {
                $query->where('statut', $statut);
            }

            // Analyse par statut
            $commandesParStatut = DB::table('commandes')
                ->whereBetween('created_at', [$dateDebut, $dateFin])
                ->select([
                    'statut',
                    DB::raw('COUNT(*) as nombre_commandes'),
                    DB::raw('SUM(montant_total) as montant_total'),
                    DB::raw('AVG(montant_total) as panier_moyen')
                ])
                ->groupBy('statut')
                ->get();

            // Évolution quotidienne
            $evolutionQuotidienne = $query
                ->select([
                    DB::raw("TO_CHAR(created_at, 'YYYY-MM-DD') as date"),
                    DB::raw('COUNT(*) as nombre_commandes'),
                    DB::raw('SUM(montant_total) as montant_total'),
                    DB::raw('AVG(montant_total) as panier_moyen')
                ])
                ->groupBy(DB::raw("TO_CHAR(created_at, 'YYYY-MM-DD')"))
                ->orderBy('date')
                ->get();

            // Commandes urgentes et en retard
            $commandesUrgentes = DB::table('commandes')
                ->whereIn('priorite', ['urgente', 'tres_urgente'])
                ->whereBetween('created_at', [$dateDebut, $dateFin])
                ->count();

            $commandesEnRetard = DB::table('commandes')
                ->where('date_livraison_prevue', '<', now())
                ->whereNotIn('statut', ['livree', 'annulee'])
                ->whereBetween('created_at', [$dateDebut, $dateFin])
                ->count();

            // Mode de livraison
            $modesLivraison = DB::table('commandes')
                ->whereBetween('created_at', [$dateDebut, $dateFin])
                ->select([
                    'mode_livraison',
                    DB::raw('COUNT(*) as nombre_commandes'),
                    DB::raw('SUM(montant_total) as montant_total')
                ])
                ->groupBy('mode_livraison')
                ->get();

            return [
                'commandes_par_statut' => $commandesParStatut->toArray(),
                'evolution_quotidienne' => $evolutionQuotidienne->toArray(),
                'modes_livraison' => $modesLivraison->toArray(),
                'statistiques' => [
                    'total_commandes' => $commandesParStatut->sum('nombre_commandes'),
                    'montant_total' => $commandesParStatut->sum('montant_total'),
                    'panier_moyen_global' => $commandesParStatut->isNotEmpty() ? $commandesParStatut->avg('panier_moyen') : 0,
                    'commandes_urgentes' => $commandesUrgentes,
                    'commandes_en_retard' => $commandesEnRetard
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Erreur rapport commandes', ['error' => $e->getMessage()]);
            return [
                'commandes_par_statut' => [],
                'evolution_quotidienne' => [],
                'modes_livraison' => [],
                'statistiques' => [
                    'total_commandes' => 0,
                    'montant_total' => 0,
                    'panier_moyen_global' => 0,
                    'commandes_urgentes' => 0,
                    'commandes_en_retard' => 0
                ]
            ];
        }
    }

    /**
     * Formatter les données pour les graphiques
     */
    private function formatForChart($data): array
    {
        return [
            'labels' => $data->pluck('periode')->toArray(),
            'chiffre_affaires' => $data->pluck('chiffre_affaires')->toArray(),
            'nombre_commandes' => $data->pluck('nombre_commandes')->toArray(),
            'panier_moyen' => $data->pluck('panier_moyen')->toArray()
        ];
    }

    /**
     * Comparer deux rapports
     */
    public function compareRapports(array $data1, array $data2, string $type): array
    {
        return [
            'periode1' => $data1,
            'periode2' => $data2,
            'differences' => [
                'note' => 'Comparaison des deux périodes'
            ]
        ];
    }

    /**
     * Planifier un rapport
     */
    public function planifierRapport(array $data): object
    {
        return (object) [
            'id' => 1,
            'type' => $data['type'],
            'frequence' => $data['frequence'],
            'email' => $data['email'],
            'actif' => $data['actif'] ?? true,
            'created_at' => now()
        ];
    }
    /**
 * Rapport d'analytics web
 */
/**
 * Rapport d'analytics basé sur des données réelles
 */
/**
 * Rapport d'analytics basé sur des données réelles du système
 */
public function getAnalyticsReport(Carbon $dateDebut, Carbon $dateFin): array
{
    try {
        // Métriques basées sur les commandes et clients réels
        $totalCommandes = DB::table('commandes')
            ->whereBetween('created_at', [$dateDebut, $dateFin])
            ->count();

        $nouveauxClients = DB::table('clients')
            ->whereBetween('created_at', [$dateDebut, $dateFin])
            ->count();

        $clientsActifs = DB::table('commandes')
            ->whereBetween('created_at', [$dateDebut, $dateFin])
            ->distinct('client_id')
            ->count();

        // Total des pages produits vues (si vous trackez nombre_vues)
        $totalVuesProduits = DB::table('produits')->sum('nombre_vues') ?? 0;

        // Sessions estimées (basées sur les paniers créés)
        $sessionsEstimees = DB::table('paniers')
            ->whereBetween('created_at', [$dateDebut, $dateFin])
            ->count();

        // Taux de conversion réel (commandes / sessions)
        $tauxConversion = $sessionsEstimees > 0 ? round(($totalCommandes / $sessionsEstimees) * 100, 2) : 0;

        // Analyse des sources de commandes (si vous avez le champ 'source')
        $sourcesCommandes = DB::table('commandes')
            ->select(['source', DB::raw('COUNT(*) as nombre')])
            ->whereBetween('created_at', [$dateDebut, $dateFin])
            ->groupBy('source')
            ->get()
            ->map(function($source) use ($totalCommandes) {
                return [
                    'source' => $source->source ?? 'Direct',
                    'commandes' => $source->nombre,
                    'pourcentage' => $totalCommandes > 0 ? round(($source->nombre / $totalCommandes) * 100, 2) : 0
                ];
            });

        // Pages les plus consultées (basées sur les vues produits)
        $pagesPopulaires = DB::table('produits')
            ->select(['nom', 'nombre_vues'])
            ->where('nombre_vues', '>', 0)
            ->orderBy('nombre_vues', 'desc')
            ->limit(10)
            ->get();

        // Evolution quotidienne des activités
        $evolutionQuotidienne = DB::table('commandes')
            ->select([
                DB::raw("TO_CHAR(created_at, 'YYYY-MM-DD') as date"),
                DB::raw('COUNT(*) as commandes'),
                DB::raw('COUNT(DISTINCT client_id) as clients_actifs')
            ])
            ->whereBetween('created_at', [$dateDebut, $dateFin])
            ->groupBy(DB::raw("TO_CHAR(created_at, 'YYYY-MM-DD')"))
            ->orderBy('date')
            ->get();

        // Analyse des paniers (comportement utilisateur)
        $analyseComportement = $this->getAnalyseComportement($dateDebut, $dateFin);

        // Durée moyenne estimée (basée sur l'heure de création des paniers vs commandes)
        $dureeSession = $this->calculerDureeMoyenneSession($dateDebut, $dateFin);

        return [
            // Métriques principales basées sur les vraies données
            'sessions_estimees' => $sessionsEstimees,
            'nouveaux_clients' => $nouveauxClients,
            'clients_actifs' => $clientsActifs,
            'total_commandes' => $totalCommandes,
            'pages_vues' => $totalVuesProduits,
            'taux_conversion' => $tauxConversion,
            'duree_moyenne_session' => $dureeSession, // en minutes
            
            // Analyses comportementales
            'sources_trafic' => $sourcesCommandes->toArray(),
            'pages_populaires' => $pagesPopulaires->toArray(),
            'evolution_quotidienne' => $evolutionQuotidienne->toArray(),
            'analyse_comportement' => $analyseComportement,
            
            // Informations sur la période
            'periode' => [
                'debut' => $dateDebut->format('d/m/Y'),
                'fin' => $dateFin->format('d/m/Y'),
                'jours' => $dateDebut->diffInDays($dateFin) + 1
            ]
        ];

    } catch (\Exception $e) {
        Log::error('Erreur analytics report', ['error' => $e->getMessage()]);
        return [
            'sessions_estimees' => 0,
            'nouveaux_clients' => 0,
            'clients_actifs' => 0,
            'total_commandes' => 0,
            'pages_vues' => 0,
            'taux_conversion' => 0,
            'duree_moyenne_session' => 0,
            'sources_trafic' => [],
            'pages_populaires' => [],
            'evolution_quotidienne' => [],
            'analyse_comportement' => []
        ];
    }
}

/**
 * Analyser le comportement utilisateur basé sur les paniers
 */
private function getAnalyseComportement(Carbon $dateDebut, Carbon $dateFin): array
{
    // Paniers abandonnés vs transformés
    $totalPaniers = DB::table('paniers')
        ->whereBetween('created_at', [$dateDebut, $dateFin])
        ->count();

    $paniersTransformes = DB::table('paniers')
        ->whereNotNull('commande_id')
        ->whereBetween('created_at', [$dateDebut, $dateFin])
        ->count();

    $tauxAbandon = $totalPaniers > 0 ? round((($totalPaniers - $paniersTransformes) / $totalPaniers) * 100, 2) : 0;

    // Analyse des articles par panier
    $articlesParPanier = DB::table('paniers')
        ->whereBetween('created_at', [$dateDebut, $dateFin])
        ->avg('nombre_articles') ?? 0;

    return [
        'total_paniers' => $totalPaniers,
        'paniers_transformes' => $paniersTransformes,
        'taux_abandon' => $tauxAbandon,
        'articles_moyen_panier' => round($articlesParPanier, 1)
    ];
}

/**
 * Calculer la durée moyenne de session estimée
 */
private function calculerDureeMoyenneSession(Carbon $dateDebut, Carbon $dateFin): int
{
    // Estimer basé sur l'écart entre création panier et commande
    $dureesMoyennes = DB::table('paniers as p')
        ->join('commandes as c', 'p.commande_id', '=', 'c.id')
        ->whereBetween('p.created_at', [$dateDebut, $dateFin])
        ->select([
            DB::raw('EXTRACT(EPOCH FROM (c.created_at - p.created_at))/60 as duree_minutes')
        ])
        ->get();

    return $dureesMoyennes->isNotEmpty() ? (int) $dureesMoyennes->avg('duree_minutes') : 15; // 15 min par défaut
}
/**
 * Performance produits avancée
 */
public function getPerformanceProduitsReport(Carbon $dateDebut, Carbon $dateFin): array
{
    try {
        // Performance basée sur les COMMANDES, pas les paniers
        $produitsPerformance = DB::table('produits as p')
            ->leftJoin('articles_commande as ac', 'p.id', '=', 'ac.produit_id')
            ->leftJoin('commandes as c', 'ac.commande_id', '=', 'c.id')
            ->whereBetween('c.created_at', [$dateDebut, $dateFin])
            ->whereIn('c.statut', ['confirmee', 'en_preparation', 'prete', 'livree']) // Commandes validées
            ->select([
                'p.nom',
                'p.nombre_vues',
                DB::raw('COALESCE(SUM(ac.quantite), 0) as ventes'),
                DB::raw('CASE WHEN p.nombre_vues > 0 THEN CAST(ROUND(CAST((COALESCE(SUM(ac.quantite), 0)::numeric / p.nombre_vues::numeric) * 100 AS numeric), 2) AS float) ELSE 0 END as taux_conversion')
            ])
            ->groupBy('p.id', 'p.nom', 'p.nombre_vues')
            ->orderBy('ventes', 'desc')
            ->limit(15)
            ->get();

        // Analyse des commandes (pas paniers)
        $totalCommandes = DB::table('commandes')
            ->whereBetween('created_at', [$dateDebut, $dateFin])
            ->count();
            
        $commandesValidees = DB::table('commandes')
            ->whereIn('statut', ['confirmee', 'en_preparation', 'prete', 'livree'])
            ->whereBetween('created_at', [$dateDebut, $dateFin])
            ->count();
            
        $commandesPayees = DB::table('commandes as c')
            ->join('paiements as p', 'c.id', '=', 'p.commande_id')
            ->where('p.statut', 'valide')
            ->whereBetween('c.created_at', [$dateDebut, $dateFin])
            ->count();

        // Si vous voulez analyser les paniers aussi
        $analyseRealePaniers = $this->analyserPaniersVsCommandes($dateDebut, $dateFin);

        // Top couleurs les plus achetées
        $topCouleurs = DB::table('articles_commande as ac')
            ->join('commandes as c', 'ac.commande_id', '=', 'c.id')
            ->whereIn('c.statut', ['confirmee', 'en_preparation', 'prete', 'livree'])
            ->whereBetween('c.created_at', [$dateDebut, $dateFin])
            ->whereNotNull('ac.couleur_choisie')
            ->where('ac.couleur_choisie', '!=', '')
            ->select([
                'ac.couleur_choisie as couleur',
                DB::raw('SUM(ac.quantite) as total_vendus'),
                DB::raw('COUNT(DISTINCT ac.commande_id) as nb_commandes')
            ])
            ->groupBy('ac.couleur_choisie')
            ->orderBy('total_vendus', 'desc')
            ->limit(10)
            ->get()
            ->toArray();

        // Top tailles les plus achetées (hors 'unique')
        $topTailles = DB::table('articles_commande as ac')
            ->join('commandes as c', 'ac.commande_id', '=', 'c.id')
            ->whereIn('c.statut', ['confirmee', 'en_preparation', 'prete', 'livree'])
            ->whereBetween('c.created_at', [$dateDebut, $dateFin])
            ->whereNotNull('ac.taille_choisie')
            ->where('ac.taille_choisie', '!=', '')
            ->where('ac.taille_choisie', '!=', 'unique')
            ->select([
                'ac.taille_choisie as taille',
                DB::raw('SUM(ac.quantite) as total_vendus'),
                DB::raw('COUNT(DISTINCT ac.commande_id) as nb_commandes')
            ])
            ->groupBy('ac.taille_choisie')
            ->orderBy('total_vendus', 'desc')
            ->limit(10)
            ->get()
            ->toArray();

        return [
            'produits_performance' => $produitsPerformance->toArray(),
            'analyse_commandes' => [
                'total_commandes' => $totalCommandes,
                'commandes_validees' => $commandesValidees,
                'commandes_payees' => $commandesPayees,
                'taux_validation' => $totalCommandes > 0 ? round(($commandesValidees / $totalCommandes) * 100, 2) : 0,
                'taux_paiement' => $commandesValidees > 0 ? round(($commandesPayees / $commandesValidees) * 100, 2) : 0
            ],
            'analyse_paniers' => $analyseRealePaniers,
            'top_couleurs' => $topCouleurs,
            'top_tailles' => $topTailles,
        ];

    } catch (\Exception $e) {
        Log::error('Erreur performance produits report', ['error' => $e->getMessage()]);
        return [
            'produits_performance' => [],
            'analyse_commandes' => [
                'total_commandes' => 0,
                'commandes_validees' => 0,
                'commandes_payees' => 0,
                'taux_validation' => 0,
                'taux_paiement' => 0
            ],
            'analyse_paniers' => [],
            'top_couleurs' => [],
            'top_tailles' => [],
        ];
    }
}
private function analyserPaniersVsCommandes(Carbon $dateDebut, Carbon $dateFin): array
{
    $totalPaniers = DB::table('paniers')
        ->whereBetween('created_at', [$dateDebut, $dateFin])
        ->count();

    $paniersTransformes = DB::table('paniers')
        ->whereNotNull('commande_id')
        ->whereBetween('created_at', [$dateDebut, $dateFin])
        ->count();

    return [
        'total_paniers' => $totalPaniers,
        'paniers_transformes' => $paniersTransformes,
        'taux_transformation' => $totalPaniers > 0 ? round(($paniersTransformes / $totalPaniers) * 100, 2) : 0,
        'taux_abandon' => $totalPaniers > 0 ? round((($totalPaniers - $paniersTransformes) / $totalPaniers) * 100, 2) : 0
    ];
}

}
