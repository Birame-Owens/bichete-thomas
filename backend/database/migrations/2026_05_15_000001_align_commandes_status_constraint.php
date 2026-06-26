<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commandes') || DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE commandes DROP CONSTRAINT IF EXISTS commandes_statut_check');
        DB::statement(
            "ALTER TABLE commandes ADD CONSTRAINT commandes_statut_check CHECK (statut IN (" .
            "'en_attente', 'confirmee', 'en_preparation', 'en_production', 'prete', " .
            "'en_livraison', 'livree', 'annulee', 'retournee', 'echoue'" .
            '))'
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('commandes') || DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("UPDATE commandes SET statut = 'en_production' WHERE statut = 'en_preparation'");
        DB::statement("UPDATE commandes SET statut = 'prete' WHERE statut = 'en_livraison'");
        DB::statement('ALTER TABLE commandes DROP CONSTRAINT IF EXISTS commandes_statut_check');
        DB::statement(
            "ALTER TABLE commandes ADD CONSTRAINT commandes_statut_check CHECK (statut IN (" .
            "'en_attente', 'confirmee', 'en_production', 'prete', 'livree', 'annulee', 'retournee', 'echoue'" .
            '))'
        );
    }
};
