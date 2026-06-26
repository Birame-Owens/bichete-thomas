<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modifier la colonne methode_paiement pour ajouter 'wave' et 'orange_money'
        DB::statement("
            ALTER TABLE paiements 
            DROP CONSTRAINT IF EXISTS paiements_methode_paiement_check;
        ");

        DB::statement("
            ALTER TABLE paiements
            ADD CONSTRAINT paiements_methode_paiement_check 
            CHECK (methode_paiement IN ('carte_bancaire', 'virement', 'especes', 'mobile_money', 'wave', 'orange_money'));
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restaurer l'ancienne contrainte (sans wave et orange_money)
        DB::statement("
            ALTER TABLE paiements 
            DROP CONSTRAINT IF EXISTS paiements_methode_paiement_check;
        ");

        DB::statement("
            ALTER TABLE paiements
            ADD CONSTRAINT paiements_methode_paiement_check 
            CHECK (methode_paiement IN ('carte_bancaire', 'virement', 'especes', 'mobile_money'));
        ");
    }
};
