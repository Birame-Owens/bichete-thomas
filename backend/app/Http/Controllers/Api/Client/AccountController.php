<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Commande;
use App\Models\Facture;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AccountController extends Controller
{
    /**
     * Obtenir les commandes du client
     */
    public function getOrders(Request $request): JsonResponse
    {
        try {
            $client = $this->getAuthenticatedClient();

            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client non authentifié',
                ], 401);
            }

            $orders = Commande::where('client_id', $client->id)
                ->with(['articles.produit.images_produits', 'paiements', 'factures'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $orders,
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur récupération commandes client', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des commandes',
            ], 500);
        }
    }

    /**
     * Obtenir les détails d'une commande
     */
    public function getOrderDetails(Request $request, string $orderId): JsonResponse
    {
        try {
            $client = $this->getAuthenticatedClient();

            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client non authentifié',
                ], 401);
            }

            $order = Commande::where('id', $orderId)
                ->where('client_id', $client->id)
                ->with([
                    'articles.produit.images_produits',
                    'avis_clients',
                    'paiements',
                    'factures',
                    'client',
                ])
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commande non trouvée',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $order,
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur récupération détails commande', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement de la commande',
            ], 500);
        }
    }

    /**
     * Obtenir les factures du client
     */
    public function getInvoices(Request $request): JsonResponse
    {
        try {
            $client = $this->getAuthenticatedClient();

            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client non authentifié',
                ], 401);
            }

            $invoices = Facture::where('client_id', $client->id)
                ->with('commande')
                ->orderBy('date_facture', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $invoices,
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur récupération factures client', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des factures',
            ], 500);
        }
    }

    /**
     * Télécharger une facture PDF
     */
    public function downloadInvoice(Request $request, string $invoiceId)
    {
        try {
            $client = $this->getAuthenticatedClient();

            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client non authentifié',
                ], 401);
            }

            $invoice = Facture::where('id', $invoiceId)
                ->where('client_id', $client->id)
                ->first();

            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facture non trouvée',
                ], 404);
            }

            // Vérifier si le fichier existe
            if (!$invoice->chemin_fichier || !Storage::disk('public')->exists($invoice->chemin_fichier)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fichier de facture non disponible',
                ], 404);
            }

            // Télécharger le fichier
            return Storage::disk('public')->download(
                $invoice->chemin_fichier,
                "facture-{$invoice->numero_facture}.pdf"
            );

        } catch (\Exception $e) {
            \Log::error('Erreur téléchargement facture', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du téléchargement de la facture',
            ], 500);
        }
    }

    /**
     * Obtenir le profil du client
     */
    public function getProfile(Request $request): JsonResponse
    {
        try {
            $client = $this->getAuthenticatedClient();

            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client non authentifié',
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => $client,
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur récupération profil client', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du profil',
            ], 500);
        }
    }

    /**
     * Obtenir le client authentifié
     * Utilise uniquement Sanctum pour l'authentification
     */
    private function getAuthenticatedClient(): ?Client
    {
        // Utiliser l'utilisateur authentifié via Sanctum
        $user = request()->user() ?? auth('sanctum')->user() ?? Auth::user();
        
        if (!$user) {
            return null;
        }

        // Récupérer le client associé à l'utilisateur
        return Client::where('user_id', $user->id)->first();
    }
}
