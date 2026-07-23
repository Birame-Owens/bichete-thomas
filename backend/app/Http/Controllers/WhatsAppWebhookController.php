<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Webhook pour recevoir les notifications WhatsApp/Twilio
 */
class WhatsAppWebhookController extends Controller
{
    /**
     * Recevoir les mises à jour de statut WhatsApp
     * POST /api/webhooks/whatsapp
     */
    public function handleStatusUpdate(Request $request): JsonResponse
    {
        Log::info('WhatsApp webhook reçu', $request->all());
        
        $data = $request->all();
        
        // Twilio envoie: MessageSid, MessageStatus, To, From
        $messageSid = $data['MessageSid'] ?? null;
        $status = $data['MessageStatus'] ?? null; // sent, delivered, read, failed, undelivered
        $recipientNumber = $data['To'] ?? null;
        
        if (!$messageSid || !$status) {
            return response()->json(['error' => 'Invalid webhook data'], 400);
        }
        
        // Mettre à jour le statut dans la base de données
        $updated = DB::table('messages_whatsapp')
            ->where('reference_externe', $messageSid)
            ->update([
                'statut' => $this->mapTwilioStatus($status),
                'updated_at' => now(),
            ]);
        
        if ($updated) {
            Log::info('WhatsApp statut mis à jour', [
                'sid' => $messageSid,
                'status' => $status,
                'to' => $recipientNumber,
            ]);
        }
        
        return response()->json(['success' => true]);
    }
    
    /**
     * Tester le webhook localement
     * GET /api/webhooks/whatsapp/test
     */
    public function testWebhook(): JsonResponse
    {
        $testData = [
            'MessageSid' => 'SMxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            'MessageStatus' => 'delivered',
            'To' => 'whatsapp:+221781234567',
            'From' => 'whatsapp:+15551234567',
        ];
        
        Log::info('Test webhook WhatsApp', $testData);
        
        return response()->json([
            'message' => 'Test webhook reçu',
            'data' => $testData,
            'action' => 'Simuler ce webhook avec: ' . json_encode($testData),
        ]);
    }
    
    /**
     * Mapper les statuts Twilio aux nôtres
     */
    private function mapTwilioStatus(string $twilioStatus): string
    {
        return match($twilioStatus) {
            'sent' => 'envoye',
            'delivered' => 'delivered',
            'read' => 'read',
            'failed' => 'failed',
            'undelivered' => 'failed',
            default => 'unknown',
        };
    }
}
