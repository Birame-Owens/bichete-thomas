<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $settings = [
        'montant_acompte_defaut' => [
            'value' => 5000,
            'type' => 'decimal',
            'description' => 'Montant fixe d acompte propose par defaut.',
        ],
        'pourcentage_acompte' => [
            'value' => 30,
            'type' => 'decimal',
            'description' => 'Pourcentage d acompte applique sur le montant total.',
        ],
        'heure_ouverture' => [
            'value' => '09:00',
            'type' => 'time',
            'description' => 'Heure d ouverture du salon.',
        ],
        'heure_fermeture' => [
            'value' => '19:00',
            'type' => 'time',
            'description' => 'Heure de fermeture du salon.',
        ],
        'telephone_whatsapp' => [
            'value' => '+221 77 000 00 00',
            'type' => 'string',
            'description' => 'Numero WhatsApp utilise pour les confirmations et rappels.',
        ],
        'devise' => [
            'value' => 'FCFA',
            'type' => 'string',
            'description' => 'Devise appliquee aux prix et acomptes.',
        ],
        'delai_annulation_heures' => [
            'value' => 24,
            'type' => 'integer',
            'description' => 'Delai minimum avant rendez-vous pour annuler sans blocage.',
        ],
        'seuil_retard_minutes' => [
            'value' => 15,
            'type' => 'integer',
            'description' => 'Nombre de minutes apres lequel un client est considere en retard.',
        ],
        'seuil_absence_minutes' => [
            'value' => 30,
            'type' => 'integer',
            'description' => 'Nombre de minutes apres lequel le retard devient une absence.',
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
