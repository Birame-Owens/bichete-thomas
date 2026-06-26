<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use App\Models\MessagesWhatsapp;

/**
 * Service pour gérer les webhooks WhatsApp (statut de livraison)
 */
class WhatsAppWebhookService
{
    /**
     * Traiter un webhook Twilio pour WhatsApp
     * 
     * Webhook format:
     * {
     *   "SmsMessageSid": "...",
     *   "MessageStatus": "delivered|failed|read|sent",
     *   "To": "whatsapp:+221...",
     *   "From": "whatsapp:+221...",
     *   "MessageSid": "..."
     * }
     */
    public static function handleWebhook(array $data)
    {
        try {
            $sid = $data['SmsMessageSid'] ?? $data['MessageSid'] ?? null;
            $status = $data['MessageStatus'] ?? 'unknown';
            $toNumber = str_replace('whatsapp:', '', $data['To'] ?? '');
            
            if (!$sid) {
                Log::warning('Webhook WhatsApp: SID manquant', $data);
                return false;
            }
            
            // Trouver le message avec ce SID
            $message = MessagesWhatsapp::where('reference_externe', $sid)->first();
            
            if (!$message) {
                Log::warning('Message WhatsApp non trouvé', ['sid' => $sid]);
                return false;
            }
            
            // Mettre à jour le statut
            $message->update([
                'statut' => self::mapStatus($status),
                'webhook_received_at' => now(),
            ]);
            
            Log::info('Statut WhatsApp mis à jour', [
                'sid' => $sid,
                'to' => $toNumber,
                'status' => $status,
                'mapped_status' => self::mapStatus($status),
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Erreur webhook WhatsApp', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            return false;
        }
    }
    
    /**
     * Mapper les statuts Twilio vers nos statuts
     */
    private static function mapStatus(string $twilioStatus): string
    {
        return match($twilioStatus) {
            'sent' => 'envoye',
            'delivered' => 'livre',
            'read' => 'lu',
            'failed' => 'echec',
            default => 'inconnu',
        };
    }
    
    /**
     * Obtenir les stats des messages WhatsApp
     */
    public static function getStats()
    {
        return [
            'total' => MessagesWhatsapp::count(),
            'envoye' => MessagesWhatsapp::where('statut', 'envoye')->count(),
            'livre' => MessagesWhatsapp::where('statut', 'livre')->count(),
            'lu' => MessagesWhatsapp::where('statut', 'lu')->count(),
            'echec' => MessagesWhatsapp::where('statut', 'echec')->count(),
            'delivery_rate' => self::calculateDeliveryRate(),
            'read_rate' => self::calculateReadRate(),
        ];
    }
    
    /**
     * Calculer le taux de livraison
     */
    private static function calculateDeliveryRate(): float
    {
        $total = MessagesWhatsapp::where('created_at', '>', now()->subDays(7))->count();
        $delivered = MessagesWhatsapp::where('statut', 'livre')
            ->where('created_at', '>', now()->subDays(7))
            ->count();
        
        return $total > 0 ? round(($delivered / $total) * 100, 2) : 0;
    }
    
    /**
     * Calculer le taux de lecture
     */
    private static function calculateReadRate(): float
    {
        $total = MessagesWhatsapp::where('created_at', '>', now()->subDays(7))->count();
        $read = MessagesWhatsapp::where('statut', 'lu')
            ->where('created_at', '>', now()->subDays(7))
            ->count();
        
        return $total > 0 ? round(($read / $total) * 100, 2) : 0;
    }
}
