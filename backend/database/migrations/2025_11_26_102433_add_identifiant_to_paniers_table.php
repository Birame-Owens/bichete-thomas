<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paniers', function (Blueprint $table) {
            // Ajouter colonne identifiant unique (user_123 ou guest_abc)
            $table->string('identifiant')->unique()->nullable()->after('id');
        });
        
        // Remplir identifiant pour les paniers existants
        DB::statement("
            UPDATE paniers 
            SET identifiant = CASE
                WHEN client_id IS NOT NULL THEN CONCAT('user_', client_id)
                WHEN session_id IS NOT NULL THEN CONCAT('guest_', session_id)
                ELSE CONCAT('unknown_', id)
            END
            WHERE identifiant IS NULL
        ");
        
        // Rendre NOT NULL après remplissage
        Schema::table('paniers', function (Blueprint $table) {
            $table->string('identifiant')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('paniers', function (Blueprint $table) {
            $table->dropColumn('identifiant');
        });
    }
};
