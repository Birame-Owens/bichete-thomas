<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessNabooPayWebhookJob;
use App\Services\Client\NabooPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NabooPayController extends Controller
{
    public function __construct(private readonly NabooPayService $nabooPayService)
    {
    }

    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        $orderId = $payload['order_id'] ?? data_get($payload, 'data.order_id');

        if (!$this->nabooPayService->verifySignature(
            $request->getContent(),
            $request->header('X-Signature'),
            $payload
        )) {
            Log::warning('NabooPay webhook: signature invalide', [
                'order_id' => $orderId,
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        if (blank($orderId)) {
            return response()->json(['error' => 'Missing order_id'], 400);
        }

        ProcessNabooPayWebhookJob::dispatch($orderId, $payload);

        return response()->json(['success' => true]);
    }

    public function status(string $orderId): JsonResponse
    {
        $payload = $this->nabooPayService->fetchTransaction($orderId);
        $this->nabooPayService->handleTransactionStatus($orderId, $payload);

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $orderId,
                'status' => $payload['transaction_status']
                    ?? $payload['payment_status']
                    ?? $payload['status']
                    ?? data_get($payload, 'data.transaction_status')
                    ?? data_get($payload, 'data.status')
                    ?? 'pending',
            ],
        ]);
    }
}
