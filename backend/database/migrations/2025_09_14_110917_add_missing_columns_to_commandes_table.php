<?php
// Créez cette migration avec : php artisan make:migration add_missing_columns_to_commandes_table

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('commandes', function (Blueprint $table) {
            // Ajouter les colonnes manquantes si elles n'existent pas
            if (!Schema::hasColumn('commandes', 'date_fin_prevue')) {
                $table->timestamp('date_fin_prevue')->nullable();
            }
            
            if (!Schema::hasColumn('commandes', 'date_debut_production')) {
                $table->timestamp('date_debut_production')->nullable();
            }
            
            if (!Schema::hasColumn('commandes', 'date_fin_production')) {
                $table->timestamp('date_fin_production')->nullable();
            }
            
            if (!Schema::hasColumn('commandes', 'date_confirmation')) {
                $table->timestamp('date_confirmation')->nullable();
            }
            
            if (!Schema::hasColumn('commandes', 'date_livraison_prevue')) {
                $table->timestamp('date_livraison_prevue')->nullable();
            }
            
            if (!Schema::hasColumn('commandes', 'date_livraison_reelle')) {
                $table->timestamp('date_livraison_reelle')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->dropColumn([
                'date_fin_prevue',
                'date_debut_production', 
                'date_fin_production',
                'date_confirmation',
                'date_livraison_prevue',
                'date_livraison_reelle'
            ]);
        });
    }
};