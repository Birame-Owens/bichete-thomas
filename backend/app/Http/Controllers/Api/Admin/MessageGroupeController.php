<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Jobs\SendGroupMessageJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class MessageGroupeController extends Controller
{
    /**
     * Obtenir les groupes de clients disponibles
     */
    public function getClientGroups(): JsonResponse
    {
        try {
            // Calculer les stats des clients VIP avec une requête séparée
            $vipClientIds = \DB::table('commandes')
                ->select('client_id')
                ->selectRaw('SUM(montant_total) as total')
                ->whereNull('deleted_at')
                ->groupBy('client_id')
                ->havingRaw('SUM(montant_total) >= 100000')
                ->pluck('client_id');

            $stats = [
                'all' => Client::count(),
                'with_orders' => Client::has('commandes')->count(),
                'without_orders' => Client::doesntHave('commandes')->count(),
                'vip' => count($vipClientIds),
                'inactive' => Client::whereDoesntHave('commandes', function($q) {
                    $q->where('created_at', '>=', now()->subMonths(3));
                })->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'groups' => [
                        ['id' => 'all', 'name' => 'Tous les clients', 'count' => $stats['all']],
                        ['id' => 'with_orders', 'name' => 'Clients avec commandes', 'count' => $stats['with_orders']],
                        ['id' => 'without_orders', 'name' => 'Clients sans commande', 'count' => $stats['without_orders']],
                        ['id' => 'vip', 'name' => 'Clients VIP (>100k FCFA)', 'count' => $stats['vip']],
                        ['id' => 'inactive', 'name' => 'Clients inactifs (3+ mois)', 'count' => $stats['inactive']],
                    ],
                    'stats' => $stats
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur chargement groupes clients', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des groupes'
            ], 500);
        }
    }

    /**
     * Obtenir les clients d'un groupe spécifique
     */
    public function getGroupClients(Request $request): JsonResponse
    {
        try {
            $groupId = $request->query('group_id', 'all');
            
            $query = Client::query();

            switch ($groupId) {
                case 'with_orders':
                    $query->has('commandes');
                    break;
                case 'without_orders':
                    $query->doesntHave('commandes');
                    break;
                case 'vip':
                    $query->whereHas('commandes', function($q) {
                        $q->selectRaw('client_id, SUM(montant_total) as total')
                          ->groupBy('client_id')
                          ->having('total', '>=', 100000);
                    });
                    break;
                case 'inactive':
                    $query->whereDoesntHave('commandes', function($q) {
                        $q->where('created_at', '>=', now()->subMonths(3));
                    });
                    break;
                case 'all':
                default:
                    // Tous les clients
                    break;
            }

            $clients = $query->select('id', 'nom', 'prenom', 'email', 'telephone', 'created_at')
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'clients' => $clients,
                    'count' => $clients->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur chargement clients groupe', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des clients'
            ], 500);
        }
    }

    /**
     * Envoyer un message groupé
     */
    public function sendGroupMessage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|string',
            'channel' => 'required|in:email,whatsapp,both',
            'subject' => 'required_if:channel,email,both|string|max:255',
            'message' => 'required|string|max:5000',
            'client_ids' => 'nullable|array',
            'client_ids.*' => 'exists:clients,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();
            
            // Récupérer les clients
            $clients = $this->getTargetClients($data['group_id'], $data['client_ids'] ?? null);

            if ($clients->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun client trouvé pour ce groupe'
                ], 400);
            }

            // Dispatcher le job pour envoi en masse
            SendGroupMessageJob::dispatch([
                'clients' => $clients->toArray(),
                'channel' => $data['channel'],
                'subject' => $data['subject'] ?? 'Message de NDEYA SHOP',
                'message' => $data['message'],
                'admin_id' => $request->user()->id
            ])->onQueue('emails');

            Log::info('📧 Message groupé dispatché', [
                'group_id' => $data['group_id'],
                'channel' => $data['channel'],
                'recipients_count' => $clients->count(),
                'admin_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'message' => "Message envoyé à {$clients->count()} client(s)",
                'data' => [
                    'recipients_count' => $clients->count(),
                    'channel' => $data['channel']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur envoi message groupé', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du message'
            ], 500);
        }
    }

    /**
     * Récupérer les clients cibles
     */
    private function getTargetClients(string $groupId, ?array $clientIds)
    {
        // Si des IDs spécifiques sont fournis, les utiliser
        if ($clientIds && !empty($clientIds)) {
            return Client::whereIn('id', $clientIds)->get();
        }

        // Sinon, utiliser le groupe
        $query = Client::query();

        switch ($groupId) {
            case 'with_orders':
                $query->has('commandes');
                break;
            case 'without_orders':
                $query->doesntHave('commandes');
                break;
            case 'vip':
                // Récupérer les IDs des clients VIP via une sous-requête
                $vipClientIds = \DB::table('commandes')
                    ->select('client_id')
                    ->whereNull('deleted_at')
                    ->groupBy('client_id')
                    ->havingRaw('SUM(montant_total) >= 100000')
                    ->pluck('client_id');
                
                $query->whereIn('id', $vipClientIds);
                break;
            case 'inactive':
                $query->whereDoesntHave('commandes', function($q) {
                    $q->where('created_at', '>=', now()->subMonths(3));
                });
                break;
            case 'all':
            default:
                // Tous les clients
                break;
        }

        return $query->get();
    }
}
