<?php

namespace Database\Seeders;

use App\Models\CategorieDepense;
use App\Models\ParametreSysteme;
use App\Models\RegleFidelite;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminRole = Role::query()->updateOrCreate(
            ['nom' => 'admin'],
            ['description' => 'Administrateur de la plateforme']
        );

        $geranteRole = Role::query()->updateOrCreate(
            ['nom' => 'gerante'],
            ['description' => 'Gerante du salon']
        );

        User::query()->updateOrCreate([
            'email' => env('ADMIN_EMAIL', 'admin@bichette-thomas.test'),
        ], [
            'role_id' => $adminRole->id,
            'name' => env('ADMIN_NAME', 'Administratrice'),
            'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
            'email_verified_at' => now(),
        ]);

        User::query()->updateOrCreate([
            'email' => env('GERANTE_EMAIL', 'gerante@bichette-thomas.test'),
        ], [
            'role_id' => $geranteRole->id,
            'name' => env('GERANTE_NAME', 'Gerante'),
            'password' => Hash::make(env('GERANTE_PASSWORD', 'password')),
            'email_verified_at' => now(),
        ]);

        foreach ($this->defaultSystemSettings() as $setting) {
            ParametreSysteme::query()->updateOrCreate(
                ['cle' => $setting['cle']],
                $setting
            );
        }

        foreach ($this->defaultExpenseCategories() as $category) {
            CategorieDepense::query()->updateOrCreate(
                ['nom' => $category['nom']],
                $category
            );
        }

        foreach ($this->defaultLoyaltyRules() as $rule) {
            RegleFidelite::query()->updateOrCreate(
                ['nom' => $rule['nom']],
                $rule
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultSystemSettings(): array
    {
        return [
            [
                'cle' => 'montant_acompte_defaut',
                'valeur' => ['value' => 5000],
                'type' => 'decimal',
                'description' => 'Montant fixe d acompte propose par defaut.',
                'modifiable' => true,
            ],
            [
                'cle' => 'pourcentage_acompte',
                'valeur' => ['value' => 30],
                'type' => 'decimal',
                'description' => 'Pourcentage d acompte applique sur le montant total.',
                'modifiable' => true,
            ],
            [
                'cle' => 'heure_ouverture',
                'valeur' => ['value' => '09:00'],
                'type' => 'time',
                'description' => 'Heure d ouverture du salon.',
                'modifiable' => true,
            ],
            [
                'cle' => 'heure_fermeture',
                'valeur' => ['value' => '19:00'],
                'type' => 'time',
                'description' => 'Heure de fermeture du salon.',
                'modifiable' => true,
            ],
            [
                'cle' => 'telephone_whatsapp',
                'valeur' => ['value' => '+221 77 000 00 00'],
                'type' => 'string',
                'description' => 'Numero WhatsApp utilise pour les confirmations et rappels.',
                'modifiable' => true,
            ],
            [
                'cle' => 'devise',
                'valeur' => ['value' => 'FCFA'],
                'type' => 'string',
                'description' => 'Devise appliquee aux prix et acomptes.',
                'modifiable' => true,
            ],
            [
                'cle' => 'delai_annulation_heures',
                'valeur' => ['value' => 24],
                'type' => 'integer',
                'description' => 'Delai minimum avant rendez-vous pour annuler sans blocage.',
                'modifiable' => true,
            ],
            [
                'cle' => 'seuil_retard_minutes',
                'valeur' => ['value' => 15],
                'type' => 'integer',
                'description' => 'Nombre de minutes apres lequel un client est considere en retard.',
                'modifiable' => true,
            ],
            [
                'cle' => 'seuil_absence_minutes',
                'valeur' => ['value' => 30],
                'type' => 'integer',
                'description' => 'Nombre de minutes apres lequel le retard devient une absence.',
                'modifiable' => true,
            ],
            [
                'cle' => 'limite_reservations_par_jour',
                'valeur' => ['value' => 15],
                'type' => 'integer',
                'description' => 'Nombre maximum de reservations que le salon peut prendre par jour.',
                'modifiable' => true,
            ],
            [
                'cle' => 'limite_reservations_par_creneau',
                'valeur' => ['value' => 3],
                'type' => 'integer',
                'description' => 'Nombre maximum de reservations autorisees sur la meme heure de debut.',
                'modifiable' => true,
            ],
            [
                'cle' => 'nombre_reservations_fidelite',
                'valeur' => ['value' => 9],
                'type' => 'integer',
                'description' => 'Nombre de reservations terminees avant recompense fidelite.',
                'modifiable' => true,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultExpenseCategories(): array
    {
        return [
            ['nom' => 'loyer', 'description' => 'Charges de location du salon', 'actif' => true],
            ['nom' => 'electricite', 'description' => 'Factures et recharges electricite', 'actif' => true],
            ['nom' => 'internet', 'description' => 'Connexion internet et telephone', 'actif' => true],
            ['nom' => 'meches', 'description' => 'Achat de meches et extensions', 'actif' => true],
            ['nom' => 'produits', 'description' => 'Produits de soin et consommables', 'actif' => true],
            ['nom' => 'transport', 'description' => 'Frais de transport et livraison', 'actif' => true],
            ['nom' => 'salaire', 'description' => 'Salaires et avances', 'actif' => true],
            ['nom' => 'materiel', 'description' => 'Materiel et equipements du salon', 'actif' => true],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultLoyaltyRules(): array
    {
        return [
            [
                'nom' => 'Reduction 10e reservation',
                'nombre_reservations_requis' => 9,
                'type_recompense' => 'pourcentage',
                'valeur_recompense' => 10,
                'actif' => true,
            ],
        ];
    }
}
