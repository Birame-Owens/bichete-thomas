<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi de messages WhatsApp via Twilio (prioritaire) ou Meta Cloud API (fallback).
 *
 * Utilise par PaymentReceiptNotificationService (recu de paiement) et
 * SendMagicLinkNotification (lien de connexion). Centralise ici pour eviter
 * la duplication et permettre d ajouter un troisieme provider sans toucher
 * aux consommateurs.
 */
class WhatsappService
{
    /**
     * Envoie un message texte libre. Retourne true si au moins un provider a reussi.
     */
    public function send(string $to, string $message, string $context = 'whatsapp'): bool
    {
        $normalized = $this->normalizePhone($to);

        if (! $normalized) {
            return false;
        }

        if ($this->sendViaTwilio($normalized, $message, $context)) {
            return true;
        }

        return $this->sendViaCloudApi($normalized, $message, $context);
    }

    /**
     * Envoie via un template WhatsApp Cloud API (pour les messages structures).
     * Retourne false et tombe en fallback texte si le template n est pas configure.
     *
     * @param array<string, mixed> $templateComponents
     */
    public function sendTemplate(string $to, string $templateName, array $templateComponents, string $context = 'whatsapp'): bool
    {
        $normalized = $this->normalizePhone($to);

        if (! $normalized) {
            return false;
        }

        $token = config('services.whatsapp.access_token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');

        if (! $token || ! $phoneNumberId) {
            return false;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $normalized,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => config('services.whatsapp.template_language', 'fr')],
                'components' => $templateComponents,
            ],
        ];

        try {
            $response = Http::withToken((string) $token)
                ->acceptJson()
                ->post(rtrim((string) config('services.whatsapp.base_url'), '/') . "/{$phoneNumberId}/messages", $payload);

            if ($response->failed()) {
                Log::warning("WhatsApp Cloud template failed [{$context}]", [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return false;
            }

            return true;
        } catch (ConnectionException|\Throwable $e) {
            Log::warning("WhatsApp Cloud template exception [{$context}]", ['message' => $e->getMessage()]);

            return false;
        }
    }

    // -----------------------------------------------------------------
    // Providers
    // -----------------------------------------------------------------

    private function sendViaTwilio(string $to, string $message, string $context): bool
    {
        $accountSid = config('services.twilio.account_sid');
        $authToken = config('services.twilio.auth_token');
        $from = $this->twilioAddress(config('services.twilio.whatsapp_from'));
        $toAddress = $this->twilioAddress($to);

        if (! $accountSid || ! $authToken || ! $from || ! $toAddress) {
            return false;
        }

        try {
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages", [
                    'From' => $from,
                    'To' => $toAddress,
                    'Body' => $message,
                ]);

            if ($response->failed()) {
                Log::warning("Twilio WhatsApp failed [{$context}]", [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return false;
            }

            return true;
        } catch (ConnectionException|\Throwable $e) {
            Log::warning("Twilio WhatsApp exception [{$context}]", ['message' => $e->getMessage()]);

            return false;
        }
    }

    private function sendViaCloudApi(string $to, string $message, string $context): bool
    {
        $token = config('services.whatsapp.access_token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');

        if (! $token || ! $phoneNumberId) {
            return false;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => ['preview_url' => true, 'body' => $message],
        ];

        try {
            $response = Http::withToken((string) $token)
                ->acceptJson()
                ->post(rtrim((string) config('services.whatsapp.base_url'), '/') . "/{$phoneNumberId}/messages", $payload);

            if ($response->failed()) {
                Log::warning("WhatsApp Cloud API failed [{$context}]", [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return false;
            }

            return true;
        } catch (ConnectionException|\Throwable $e) {
            Log::warning("WhatsApp Cloud API exception [{$context}]", ['message' => $e->getMessage()]);

            return false;
        }
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    public function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // Retourne sans le '+' : format attendu par Twilio et Cloud API.
        if (str_starts_with($digits, '221')) {
            return $digits;
        }

        if (strlen($digits) === 9) {
            return '221' . $digits;
        }

        return $digits;
    }

    private function twilioAddress(mixed $phone): ?string
    {
        $raw = trim((string) $phone);

        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, 'whatsapp:')) {
            return $raw;
        }

        $normalized = $this->normalizePhone($raw);

        return $normalized ? 'whatsapp:+' . $normalized : null;
    }
}
