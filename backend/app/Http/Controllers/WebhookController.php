<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppWebhookService;
use App\Services\EmailTrackerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Webhooks pour recevoir les statuts des services externes
 * Testable en local avec curl/postman
 */
class WebhookController extends Controller
{
    /**
     * Webhook Twilio pour les statuts WhatsApp
     * POST /api/webhooks/whatsapp
     * 
     * Exemple curl en local:
     * curl -X POST http://192.168.1.11:8000/api/webhooks/whatsapp \
     *   -H "Content-Type: application/x-www-form-urlencoded" \
     *   -d "SmsMessageSid=SMxxxxxxxxx&MessageStatus=delivered&To=whatsapp:%2B221771234567"
     */
    public function whatsappWebhook(Request $request): JsonResponse
    {
        $data = $request->all();
        
        $result = WhatsAppWebhookService::handleWebhook($data);
        
        return response()->json([
            'success' => $result,
            'message' => $result ? 'Webhook traité' : 'Erreur traitement webhook',
        ]);
    }
    
    /**
     * Webhook pour les bounces d'emails (simule SendGrid/Mailgun)
     * POST /api/webhooks/email-bounce
     * 
     * Exemple curl en local:
     * curl -X POST http://192.168.1.11:8000/api/webhooks/email-bounce \
     *   -H "Content-Type: application/json" \
     *   -d '{
     *     "email": "test@example.com",
     *     "reason": "Mailbox does not exist",
     *     "timestamp": "2025-02-05T10:30:00Z"
     *   }'
     */
    public function emailBounceWebhook(Request $request): JsonResponse
    {
        $email = $request->input('email');
        $reason = $request->input('reason', 'unknown');
        
        $result = EmailTrackerService::trackBounce($email, $reason);
        
        return response()->json([
            'success' => $result,
            'message' => $result ? 'Bounce enregistré' : 'Erreur',
        ]);
    }
    
    /**
     * Webhook pour les ouvertures d'emails (pixel de tracking)
     * GET /api/webhooks/email-open/{token}
     * 
     * Exemple curl:
     * curl "http://192.168.1.11:8000/api/webhooks/email-open/TOKEN123"
     */
    public function emailOpenWebhook(Request $request, string $token): JsonResponse
    {
        $ipAddress = $request->ip();
        $userAgent = $request->header('User-Agent', 'unknown');
        
        $result = EmailTrackerService::trackOpen($token, $ipAddress, $userAgent);
        
        // Retourner un pixel 1x1 transparent (pour email)
        if ($result) {
            return response()->json(['tracked' => true]);
        }
        
        return response()->json(['tracked' => false], 404);
    }
    
    /**
     * Webhook pour les clics dans les emails
     * GET /api/webhooks/email-click/{token}
     * 
     * Exemple curl:
     * curl "http://192.168.1.11:8000/api/webhooks/email-click/TOKEN123?redirect=https://example.com"
     */
    public function emailClickWebhook(Request $request, string $token)
    {
        $redirectUrl = $request->input('redirect', 'https://ndeya-shop.com');
        
        $result = EmailTrackerService::trackClick($token);
        
        if ($result) {
            return redirect($redirectUrl);
        }
        
        return response()->json(['error' => 'Token not found'], 404);
    }
    
    /**
     * Tester les webhooks en local
     * GET /api/webhooks/test
     */
    public function test(): JsonResponse
    {
        return response()->json([
            'webhooks_disponibles' => [
                [
                    'nom' => 'WhatsApp Status',
                    'endpoint' => 'POST /api/webhooks/whatsapp',
                    'exemple' => 'curl -X POST http://192.168.1.11:8000/api/webhooks/whatsapp -H "Content-Type: application/x-www-form-urlencoded" -d "SmsMessageSid=SMS123&MessageStatus=delivered"',
                ],
                [
                    'nom' => 'Email Bounce',
                    'endpoint' => 'POST /api/webhooks/email-bounce',
                    'exemple' => 'curl -X POST http://192.168.1.11:8000/api/webhooks/email-bounce -H "Content-Type: application/json" -d \'{"email":"test@example.com","reason":"Invalid"}\'',
                ],
                [
                    'nom' => 'Email Open (Pixel)',
                    'endpoint' => 'GET /api/webhooks/email-open/{token}',
                    'exemple' => 'curl "http://192.168.1.11:8000/api/webhooks/email-open/TOKEN123"',
                ],
                [
                    'nom' => 'Email Click',
                    'endpoint' => 'GET /api/webhooks/email-click/{token}',
                    'exemple' => 'curl "http://192.168.1.11:8000/api/webhooks/email-click/TOKEN123?redirect=https://example.com"',
                ],
            ],
        ]);
    }
}
