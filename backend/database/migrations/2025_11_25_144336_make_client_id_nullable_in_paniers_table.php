<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('paniers', function (Blueprint $table) {
            // Supprimer la contrainte de clé étrangère d'abord
            $table->dropForeign(['client_id']);
            
            // Rendre client_id nullable
            $table->integer('client_id')->nullable()->change();
            
            // Recréer la contrainte de clé étrangère avec onDelete cascade
            $table->foreign('client_id')
                  ->references('id')
                  ->on('clients')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paniers', function (Blueprint $table) {
            // Supprimer la contrainte flexible
            $table->dropForeign(['client_id']);
            
            // Remettre NOT NULL
            $table->integer('client_id')->nullable(false)->change();
            
            // Recréer la contrainte stricte
            $table->foreign('client_id')
                  ->references('id')
                  ->on('clients')
                  ->onDelete('cascade');
        });
    }
};
