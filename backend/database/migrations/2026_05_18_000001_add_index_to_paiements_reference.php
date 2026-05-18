<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table): void {
            // La colonne `reference` stocke l'order_id NabooPay (ex. "BT-42-XYZABC12").
            // Elle est requêtée à chaque webhook entrant et à chaque retour client
            // depuis NabooPay → sans index, PostgreSQL fait un full scan de la table
            // paiements à chaque paiement en ligne.
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table): void {
            $table->dropIndex(['reference']);
        });
    }
};
