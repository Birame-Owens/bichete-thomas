<?php

namespace App\Jobs;

use App\Mail\SignalementAdminMail;
use App\Models\Signalement;
use App\Models\User;
use App\Services\WhatsappService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSignalementNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(public readonly int $signalementId)
    {
    }

    public function handle(WhatsappService $whatsapp): void
    {
        $signalement = Signalement::query()->with('gerante')->find($this->signalementId);

        if (! $signalement) {
            Log::warning('SendSignalementNotification : signalement introuvable', [
                'signalement_id' => $this->signalementId,
            ]);

            return;
        }

        $admins = User::query()->where('role', 'admin')->get();

        foreach ($admins as $admin) {
            // Email
            if ($admin->email) {
                try {
                    Mail::to($admin->email)->send(new SignalementAdminMail($signalement));
                } catch (\Throwable $e) {
                    Log::warning('SendSignalementNotification : email echec', [
                        'admin_id' => $admin->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }

            // WhatsApp
            if ($admin->telephone ?? null) {
                $urgenceLabel = $signalement->urgence === 'urgente' ? '🔴 URGENT' : '🟡 Normal';
                $typeLabel    = match ($signalement->type) {
                    'produit'   => 'Produit / Fourniture',
                    'materiel'  => 'Equipement / Materiel',
                    default     => 'Autre',
                };
                $geranteName  = $signalement->gerante?->name ?? 'La gerante';

                $message = "📢 *Nouveau signalement — Bichette Thomas*\n\n"
                    . "*{$urgenceLabel}*\n"
                    . "Categorie : {$typeLabel}\n"
                    . "Objet : {$signalement->titre}\n"
                    . ($signalement->description ? "Details : {$signalement->description}\n" : '')
                    . "\nEnvoye par {$geranteName}.";

                $whatsapp->send($admin->telephone, $message, 'signalement');
            }
        }
    }
}
