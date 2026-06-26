<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RapportRequest;
use App\Services\Admin\RapportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\RapportExport;
use Barryvdh\DomPDF\Facade\Pdf;

class RapportController extends Controller
{
    protected RapportService $rapportService;

    public function __construct(RapportService $rapportService)
    {
        $this->rapportService = $rapportService;
    }

    /**
     * Liste des rapports disponibles
     */
    public function index(): JsonResponse
    {
        try {
            $rapports = [
                [
                    'id' => 'ventes',
                    'nom' => 'Rapport des Ventes',
                    'description' => 'Analyse des ventes par période avec graphiques',
                    'icone' => 'TrendingUp',
                    'couleur' => 'green'
                ],
                [
                    'id' => 'produits',
                    'nom' => 'Rapport des Produits',
                    'description' => 'Produits les plus vendus et analyse par catégorie',
                    'icone' => 'Package',
                    'couleur' => 'blue'
                ],
                [
                    'id' => 'clients',
                    'nom' => 'Rapport des Clients',
                    'description' => 'Analyse de la clientèle et segmentation',
                    'icone' => 'Users',
                    'couleur' => 'purple'
                ],
                [
                    'id' => 'financier',
                    'nom' => 'Rapport Financier',
                    'description' => 'Chiffre d\'affaires, paiements et créances',
                    'icone' => 'DollarSign',
                    'couleur' => 'emerald'
                ],
                [
                    'id' => 'commandes',
                    'nom' => 'Rapport des Commandes',
                    'description' => 'Analyse des commandes par statut et période',
                    'icone' => 'ShoppingCart',
                    'couleur' => 'indigo'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'rapports' => $rapports,
                    'periodes_disponibles' => [
                        '7_jours' => 'Les 7 derniers jours',
                        '30_jours' => 'Les 30 derniers jours',
                        'mois_actuel' => 'Mois actuel',
                        'mois_precedent' => 'Mois précédent',
                        'trimestre_actuel' => 'Trimestre actuel',
                        'annee_actuelle' => 'Année actuelle',
                        'personnalise' => 'Période personnalisée'
                    ],
                    'formats_export' => [
                        'excel' => 'Microsoft Excel (.xlsx)',
                        'csv' => 'CSV (.csv)',
                        'pdf' => 'PDF (.pdf)'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur liste rapports', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des rapports'
            ], 500);
        }
    }

    /**
     * Dashboard avec KPIs principaux
     */
    public function dashboard(): JsonResponse
    {
        try {
            $kpis = $this->rapportService->getDashboardKPIs();

            return response()->json([
                'success' => true,
                'data' => $kpis,
                'meta' => [
                    'type' => 'dashboard',
                    'generated_at' => now()->toISOString(),
                    'periode_reference' => '30_derniers_jours'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur dashboard KPIs', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du dashboard'
            ], 500);
        }
    }

    /**
     * Obtenir les alertes basées sur les KPIs
     */
    public function alertes(): JsonResponse
    {
        try {
            $alertes = $this->rapportService->getAlertes();

            return response()->json([
                'success' => true,
                'data' => $alertes,
                'meta' => [
                    'total_alertes' => count($alertes),
                    'alertes_critiques' => count(array_filter($alertes, fn($a) => $a['niveau'] === 'critique')),
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur alertes', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des alertes'
            ], 500);
        }
    }

    /**
     * Analyser les tendances
     */
    public function tendances(Request $request): JsonResponse
    {
        try {
            $type = $request->input('type', 'ventes');
            $periode = $request->input('periode', '12_mois');

            $dates = $this->getPeriodeDates($periode, $request->only(['date_debut', 'date_fin']));
            $tendances = $this->rapportService->analyserTendances($type, $dates['debut'], $dates['fin']);

            return response()->json([
                'success' => true,
                'data' => $tendances,
                'meta' => [
                    'type' => 'tendances_' . $type,
                    'periode' => $dates
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur analyse tendances', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'analyse des tendances'
            ], 500);
        }
    }

    /**
     * Générer le rapport des ventes
     */
    public function ventes(RapportRequest $request): JsonResponse
    {
        try {
            $periode = $this->getPeriodeDates($request->input('periode', '30_jours'), $request->only(['date_debut', 'date_fin']));
            $groupBy = $request->input('group_by', 'day');

            $rapport = $this->rapportService->getVentesReport(
                $periode['debut'],
                $periode['fin'],
                $groupBy
            );

            return response()->json([
                'success' => true,
                'data' => $rapport,
                'meta' => [
                    'type' => 'ventes',
                    'periode' => $periode,
                    'group_by' => $groupBy,
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur rapport ventes', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du rapport des ventes'
            ], 500);
        }
    }

    /**
     * Générer le rapport des produits
     */
    public function produits(RapportRequest $request): JsonResponse
    {
        try {
            $periode = $this->getPeriodeDates($request->input('periode', '30_jours'), $request->only(['date_debut', 'date_fin']));
            $limit = $request->input('limit', 20);

            $rapport = $this->rapportService->getProduitsReport(
                $periode['debut'],
                $periode['fin'],
                $limit
            );

            return response()->json([
                'success' => true,
                'data' => $rapport,
                'meta' => [
                    'type' => 'produits',
                    'periode' => $periode,
                    'limit' => $limit
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur rapport produits', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du rapport des produits'
            ], 500);
        }
    }

    /**
     * Générer le rapport des clients
     */
    public function clients(RapportRequest $request): JsonResponse
    {
        try {
            $periode = $this->getPeriodeDates($request->input('periode', '30_jours'), $request->only(['date_debut', 'date_fin']));

            $rapport = $this->rapportService->getClientsReport(
                $periode['debut'],
                $periode['fin']
            );

            return response()->json([
                'success' => true,
                'data' => $rapport,
                'meta' => [
                    'type' => 'clients',
                    'periode' => $periode
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur rapport clients', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du rapport des clients'
            ], 500);
        }
    }

    /**
     * Générer le rapport financier
     */
    public function financier(RapportRequest $request): JsonResponse
    {
        try {
            $periode = $this->getPeriodeDates($request->input('periode', '30_jours'), $request->only(['date_debut', 'date_fin']));

            $rapport = $this->rapportService->getFinancierReport(
                $periode['debut'],
                $periode['fin']
            );

            return response()->json([
                'success' => true,
                'data' => $rapport,
                'meta' => [
                    'type' => 'financier',
                    'periode' => $periode
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur rapport financier', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du rapport financier'
            ], 500);
        }
    }

    /**
     * Générer le rapport des commandes
     */
    public function commandes(RapportRequest $request): JsonResponse
    {
        try {
            $periode = $this->getPeriodeDates($request->input('periode', '30_jours'), $request->only(['date_debut', 'date_fin']));
            $statut = $request->input('statut_commande');

            $rapport = $this->rapportService->getCommandesReport(
                $periode['debut'],
                $periode['fin'],
                $statut
            );

            return response()->json([
                'success' => true,
                'data' => $rapport,
                'meta' => [
                    'type' => 'commandes',
                    'periode' => $periode,
                    'statut_filtre' => $statut
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur rapport commandes', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du rapport des commandes'
            ], 500);
        }
    }

    /**
     * Exporter un rapport — retourne le fichier en streaming direct
     */
    public function export(Request $request)
    {
        try {
            $validated = $request->validate([
                'type' => 'required|in:ventes,produits,clients,financier,commandes,performance-produits,analytics',
                'format' => 'required|in:excel,csv,pdf',
                'periode' => 'nullable|string',
                'date_debut' => 'nullable|date',
                'date_fin' => 'nullable|date|after_or_equal:date_debut'
            ]);

            $type = $validated['type'];
            $format = $validated['format'];
            $periode = $this->getPeriodeDates($validated['periode'] ?? '30_jours', $validated);

            $data = match($type) {
                'ventes'               => $this->rapportService->getVentesReport($periode['debut'], $periode['fin']),
                'produits'             => $this->rapportService->getProduitsReport($periode['debut'], $periode['fin']),
                'clients'              => $this->rapportService->getClientsReport($periode['debut'], $periode['fin']),
                'financier'            => $this->rapportService->getFinancierReport($periode['debut'], $periode['fin']),
                'commandes'            => $this->rapportService->getCommandesReport($periode['debut'], $periode['fin']),
                'performance-produits' => $this->rapportService->getPerformanceProduitsReport($periode['debut'], $periode['fin']),
                'analytics'            => $this->rapportService->getAnalyticsReport($periode['debut'], $periode['fin']),
            };

            $filename = "rapport_{$type}_" . now()->format('Y-m-d');

            Log::info('Rapport exporté', ['type' => $type, 'format' => $format, 'user_id' => auth()->id()]);

            if ($format === 'excel') {
                return Excel::download(new RapportExport($data, $type), "{$filename}.xlsx");
            }

            if ($format === 'csv') {
                return Excel::download(new RapportExport($data, $type), "{$filename}.csv", \Maatwebsite\Excel\Excel::CSV);
            }

            // PDF
            $pdf = Pdf::loadView('pdfs.rapport', compact('data', 'type'));
            return $pdf->download("{$filename}.pdf");

        } catch (\Exception $e) {
            Log::error('Erreur export rapport', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);
            return response()->json(['success' => false, 'message' => 'Erreur lors de l\'export du rapport'], 500);
        }
    }

    /**
     * Rapport comparatif (évolutions)
     */
    public function comparatif(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'type' => 'required|in:ventes,produits,clients,financier,commandes',
                'periode1' => 'required|string',
                'periode2' => 'required|string',
                'date1_debut' => 'nullable|date',
                'date1_fin' => 'nullable|date',
                'date2_debut' => 'nullable|date',
                'date2_fin' => 'nullable|date'
            ]);

            $type = $validated['type'];
            $periode1 = $this->getPeriodeDates($validated['periode1'], [
                'date_debut' => $validated['date1_debut'] ?? null,
                'date_fin' => $validated['date1_fin'] ?? null
            ]);
            $periode2 = $this->getPeriodeDates($validated['periode2'], [
                'date_debut' => $validated['date2_debut'] ?? null,
                'date_fin' => $validated['date2_fin'] ?? null
            ]);

            $data1 = match($type) {
                'ventes' => $this->rapportService->getVentesReport($periode1['debut'], $periode1['fin']),
                'produits' => $this->rapportService->getProduitsReport($periode1['debut'], $periode1['fin']),
                'clients' => $this->rapportService->getClientsReport($periode1['debut'], $periode1['fin']),
                'financier' => $this->rapportService->getFinancierReport($periode1['debut'], $periode1['fin']),
                'commandes' => $this->rapportService->getCommandesReport($periode1['debut'], $periode1['fin'])
            };

            $data2 = match($type) {
                'ventes' => $this->rapportService->getVentesReport($periode2['debut'], $periode2['fin']),
                'produits' => $this->rapportService->getProduitsReport($periode2['debut'], $periode2['fin']),
                'clients' => $this->rapportService->getClientsReport($periode2['debut'], $periode2['fin']),
                'financier' => $this->rapportService->getFinancierReport($periode2['debut'], $periode2['fin']),
                'commandes' => $this->rapportService->getCommandesReport($periode2['debut'], $periode2['fin'])
            };

            $comparaison = $this->rapportService->compareRapports($data1, $data2, $type);

            return response()->json([
                'success' => true,
                'data' => [
                    'periode1' => $data1,
                    'periode2' => $data2,
                    'comparaison' => $comparaison
                ],
                'meta' => [
                    'type' => 'comparatif_' . $type,
                    'periode1' => $periode1,
                    'periode2' => $periode2
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur rapport comparatif', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du rapport comparatif'
            ], 500);
        }
    }

    /**
     * Planifier l'envoi automatique d'un rapport
     */
    public function planifier(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'type' => 'required|in:ventes,produits,clients,financier,commandes',
                'format' => 'required|in:excel,csv,pdf',
                'frequence' => 'required|in:quotidien,hebdomadaire,mensuel',
                'email' => 'required|email',
                'heure_envoi' => 'required|date_format:H:i',
                'jour_semaine' => 'nullable|integer|between:1,7',
                'jour_mois' => 'nullable|integer|between:1,31',
                'actif' => 'boolean'
            ]);

            $planification = $this->rapportService->planifierRapport($validated);

            Log::info('Rapport planifié', [
                'planification_id' => $planification->id,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $planification,
                'message' => 'Rapport planifié avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur planification rapport', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la planification du rapport'
            ], 500);
        }
    }

    /**
     * Convertir période en dates
     */
    private function getPeriodeDates(string $periode, array $customDates = []): array
    {
        return match($periode) {
            '7_jours' => [
                'debut' => now()->subDays(7)->startOfDay(),
                'fin' => now()->endOfDay(),
                'label' => 'Les 7 derniers jours'
            ],
            '30_jours' => [
                'debut' => now()->subDays(30)->startOfDay(),
                'fin' => now()->endOfDay(),
                'label' => 'Les 30 derniers jours'
            ],
            'mois_actuel' => [
                'debut' => now()->startOfMonth(),
                'fin' => now()->endOfMonth(),
                'label' => 'Mois actuel'
            ],
            'mois_precedent' => [
                'debut' => now()->subMonth()->startOfMonth(),
                'fin' => now()->subMonth()->endOfMonth(),
                'label' => 'Mois précédent'
            ],
            'trimestre_actuel' => [
                'debut' => now()->startOfQuarter(),
                'fin' => now()->endOfQuarter(),
                'label' => 'Trimestre actuel'
            ],
            'annee_actuelle' => [
                'debut' => now()->startOfYear(),
                'fin' => now()->endOfYear(),
                'label' => 'Année actuelle'
            ],
            '12_mois' => [
                'debut' => now()->subYear()->startOfDay(),
                'fin' => now()->endOfDay(),
                'label' => 'Les 12 derniers mois'
            ],
            'personnalise' => [
                'debut' => Carbon::parse($customDates['date_debut'] ?? now()->subDays(30))->startOfDay(),
                'fin' => Carbon::parse($customDates['date_fin'] ?? now())->endOfDay(),
                'label' => 'Période personnalisée'
            ],
            default => [
                'debut' => now()->subDays(30)->startOfDay(),
                'fin' => now()->endOfDay(),
                'label' => 'Période par défaut'
            ]
        };
    }

    /**
     * Générer un rapport PDF
     */
    private function generatePdfReport(array $data, string $type, string $filename): string
    {
        // Simulation - à implémenter avec votre package PDF préféré
        $path = storage_path("app/public/exports/{$filename}.pdf");
        file_put_contents($path, 'PDF Report Content');
        return asset("storage/exports/{$filename}.pdf");
    }

    /**
     * Obtenir la taille d'un fichier
     */
    private function getFileSize(string $path): string
    {
        $filePath = str_replace(asset('storage/'), storage_path('app/public/'), $path);
        
        if (file_exists($filePath)) {
            $bytes = filesize($filePath);
            return $this->formatBytes($bytes);
        }
        
        return 'N/A';
    }

    /**
     * Formater la taille en bytes
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    /**
 * Rapport d'analytics web
 */
public function analytics(RapportRequest $request): JsonResponse
{
    try {
        $periode = $this->getPeriodeDates($request->input('periode', '30_jours'), $request->only(['date_debut', 'date_fin']));
        $rapport = $this->rapportService->getAnalyticsReport($periode['debut'], $periode['fin']);

        return response()->json([
            'success' => true,
            'data' => $rapport,
            'meta' => ['type' => 'analytics', 'periode' => $periode]
        ]);
    } catch (\Exception $e) {
        Log::error('Erreur rapport analytics', ['error' => $e->getMessage()]);
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la génération du rapport analytics'
        ], 500);
    }
}

/**
 * Rapport de performance produits
 */
public function performanceProduits(RapportRequest $request): JsonResponse
{
    try {
        $periode = $this->getPeriodeDates($request->input('periode', '30_jours'), $request->only(['date_debut', 'date_fin']));
        $rapport = $this->rapportService->getPerformanceProduitsReport($periode['debut'], $periode['fin']);

        return response()->json([
            'success' => true,
            'data' => $rapport,
            'meta' => ['type' => 'performance_produits', 'periode' => $periode]
        ]);
    } catch (\Exception $e) {
        Log::error('Erreur rapport performance produits', ['error' => $e->getMessage()]);
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la génération du rapport performance produits'
        ], 500);
    }
}
}