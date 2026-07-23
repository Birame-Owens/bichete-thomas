<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Marqueur d'idempotence du decrement de stock : le modele Commande et le
// service CommandeService::confirmCommandeStock la referencaient deja mais la
// colonne n'avait jamais ete creee (oubli lors de l'import du module).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commandes', function (Blueprint $table): void {
            $table->timestamp('stock_decremented_at')->nullable()->after('date_livraison_reelle');
        });
    }

    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table): void {
            $table->dropColumn('stock_decremented_at');
        });
    }
};
