<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('parametres_systeme')->updateOrInsert(
            ['cle' => 'jours_fermeture'],
            [
                'valeur' => json_encode(['value' => []]),
                'type' => 'json',
                'description' => 'Liste des jours ou le salon est ferme.',
                'modifiable' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('parametres_systeme')
            ->where('cle', 'jours_fermeture')
            ->delete();
    }
};
