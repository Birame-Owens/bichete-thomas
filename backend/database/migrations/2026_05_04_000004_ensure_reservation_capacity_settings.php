<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $settings = [
        'limite_reservations_par_jour' => [
            'value' => 15,
            'type' => 'integer',
            'description' => 'Nombre maximum de reservations que le salon peut prendre par jour.',
        ],
        'limite_reservations_par_creneau' => [
            'value' => 3,
            'type' => 'integer',
            'description' => 'Nombre maximum de reservations autorisees sur la meme heure de debut.',
        ],
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->settings as $key => $setting) {
            $payload = [
                'valeur' => json_encode(['value' => $setting['value']]),
                'type' => $setting['type'],
                'description' => $setting['description'],
                'modifiable' => true,
                'updated_at' => $now,
            ];

            if (DB::table('parametres_systeme')->where('cle', $key)->exists()) {
                DB::table('parametres_systeme')->where('cle', $key)->update($payload);
                continue;
            }

            DB::table('parametres_systeme')->insert([
                'cle' => $key,
                ...$payload,
                'created_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('parametres_systeme')
            ->whereIn('cle', array_keys($this->settings))
            ->delete();
    }
};
