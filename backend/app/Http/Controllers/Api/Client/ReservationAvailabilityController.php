<?php

namespace App\Http\Controllers\Api\Client;

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
    private const CLOSED_DAYS = [
        'lundi',
        'mardi',
        'mercredi',
        'jeudi',
        'vendredi',
        'samedi',
        'dimanche',
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
        $jourFerme = $this->isClosedDay(Carbon::parse($date));

        if ($jourFerme) {
            return response()->json([
                'data' => [
                    'date' => $date,
                    'heure_ouverture' => $opening,
                    'heure_fermeture' => $closing,
                    'limite_reservations_par_jour' => $dailyLimit,
                    'reservations_jour' => 0,
                    'jour_complet' => false,
                    'jour_ferme' => true,
                    'limite_reservations_par_creneau' => $slotLimit,
                    'creneaux' => [],
                ],
            ]);
        }

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
        $now = now();
        $slots = [];

        for ($cursor = $startsAt->copy(); $cursor->lt($endsAt); $cursor->addMinutes($interval)) {
            $hour = $cursor->format('H:i');
            $count = (int) ($slotCounts[$hour] ?? 0);
            $pastSlot = $cursor->lt($now);
            $available = ! $pastSlot && ! $dayFull && $count < $slotLimit;

            $slots[] = [
                'heure' => $hour,
                'reservations' => $count,
                'limite' => $slotLimit,
                'disponible' => $available,
                'raison' => $available ? null : ($pastSlot ? 'heure_passee' : ($dayFull ? 'jour_complet' : 'creneau_complet')),
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
                'jour_ferme' => false,
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

    /**
     * @return array<int, string>
     */
    private function closedDays(): array
    {
        $days = $this->settingValue('jours_fermeture', []);

        return is_array($days) ? $days : [];
    }

    private function isClosedDay(Carbon $date): bool
    {
        $day = match ((int) $date->dayOfWeekIso) {
            1 => 'lundi',
            2 => 'mardi',
            3 => 'mercredi',
            4 => 'jeudi',
            5 => 'vendredi',
            6 => 'samedi',
            7 => 'dimanche',
            default => 'lundi',
        };

        return in_array($day, $this->closedDays(), true);
    }
}
