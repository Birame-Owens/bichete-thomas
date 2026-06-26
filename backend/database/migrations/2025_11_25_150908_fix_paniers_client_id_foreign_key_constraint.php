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
        // Utiliser une requête SQL brute pour éviter les problèmes de nommage
        DB::statement('ALTER TABLE paniers DROP CONSTRAINT IF EXISTS paniers_client_id_foreign');
        
        Schema::table('paniers', function (Blueprint $table) {
            // S'assurer que la colonne est nullable
            $table->integer('client_id')->nullable()->change();
            
            // Recréer la contrainte avec SET NULL
            $table->foreign('client_id')
                  ->references('id')
                  ->on('clients')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE paniers DROP CONSTRAINT IF EXISTS paniers_client_id_foreign');
        
        Schema::table('paniers', function (Blueprint $table) {
            $table->integer('client_id')->nullable(false)->change();
            
            $table->foreign('client_id')
                  ->references('id')
                  ->on('clients')
                  ->onDelete('cascade');
        });
    }
};
