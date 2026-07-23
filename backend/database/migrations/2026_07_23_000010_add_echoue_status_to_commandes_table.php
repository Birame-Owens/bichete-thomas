<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQL Postgres brut : inapplicable sur SQLite (tests). Sans objet sur
        // une base fraiche de toute facon (l'enum de creer_table_commandes
        // contient deja 'echoue') — conserve pour les bases historiques.
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Ajouter le statut 'echoue' à l'enum statut des commandes
        DB::statement("ALTER TABLE commandes DROP CONSTRAINT IF EXISTS commandes_statut_check");
        DB::statement("ALTER TABLE commandes ALTER COLUMN statut TYPE VARCHAR(255)");
        DB::statement("ALTER TABLE commandes ADD CONSTRAINT commandes_statut_check CHECK (statut IN ('en_attente', 'confirmee', 'en_preparation', 'en_production', 'prete', 'en_livraison', 'livree', 'annulee', 'retournee', 'echoue'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE commandes DROP CONSTRAINT IF EXISTS commandes_statut_check");
        DB::statement("ALTER TABLE commandes ADD CONSTRAINT commandes_statut_check CHECK (statut IN ('en_attente', 'confirmee', 'en_production', 'prete', 'livree', 'annulee', 'retournee'))");
    }
};
