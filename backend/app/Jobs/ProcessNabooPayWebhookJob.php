<?php

namespace App\Jobs;

use App\Services\Client\NabooPayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessNabooPayWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $backoff = 30;

    public function __construct(
        private readonly string $orderId,
        private readonly array $payload
    ) {
        $this->onQueue('payments');
    }

    public function handle(NabooPayService $nabooPayService): void
    {
        $nabooPayService->handleTransactionStatus($this->orderId, $this->payload);
    }
}
