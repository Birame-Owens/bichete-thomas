<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParametreSysteme;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationAvailabilityController extends Controller
{
    private const BLOCKING_STATUSES = [
        'en_attente',
        'confirmee',
        'acompte_paye',
        'en_cours',
        'terminee',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'interval_minutes' => ['nullable', 'integer', 'min:15', 'max:120'],
        ]);

        $date = Carbon::parse($data['date'])->toDateString();
        $interval = (int) ($data['interval_minutes'] ?? 60);
        $opening = (string) $this->settingValue('heure_ouverture', '09:00');
        $closing = (string) $this->settingValue('heure_fermeture', '19:00');
        $dailyLimit = max(1, (int) $this->settingValue('limite_reservations_par_jour', 15));
        $slotLimit = max(1, (int) $this->settingValue('limite_reservations_par_creneau', 3));

        $dailyCount = Reservation::query()
            ->whereDate('date_reservation', $date)
            ->whereIn('statut', self::BLOCKING_STATUSES)
            ->count();
        $dayFull = $dailyCount >= $dailyLimit;
        $slotCounts = Reservation::query()
            ->selectRaw("to_char(heure_debut, 'HH24:MI') as heure, count(*) as total")
            ->whereDate('date_reservation', $date)
            ->whereIn('statut', self::BLOCKING_STATUSES)
            ->groupBy('heure')
            ->pluck('total', 'heure');

        $startsAt = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$opening}");
        $endsAt = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$closing}");
        $slots = [];

        for ($cursor = $startsAt->copy(); $cursor->lt($endsAt); $cursor->addMinutes($interval)) {
            $hour = $cursor->format('H:i');
            $count = (int) ($slotCounts[$hour] ?? 0);
            $available = ! $dayFull && $count < $slotLimit;

            $slots[] = [
                'heure' => $hour,
                'reservations' => $count,
                'limite' => $slotLimit,
                'disponible' => $available,
                'raison' => $available ? null : ($dayFull ? 'jour_complet' : 'creneau_complet'),
            ];
        }

        return response()->json([
            'data' => [
                'date' => $date,
                'heure_ouverture' => $opening,
                'heure_fermeture' => $closing,
                'limite_reservations_par_jour' => $dailyLimit,
                'reservations_jour' => $dailyCount,
                'jour_complet' => $dayFull,
                'limite_reservations_par_creneau' => $slotLimit,
                'creneaux' => $slots,
            ],
        ]);
    }

    private function settingValue(string $key, mixed $default): mixed
    {
        $setting = ParametreSysteme::query()->where('cle', $key)->first();

        return $setting?->valeur['value'] ?? $default;
    }
}
