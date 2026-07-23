<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Le module ecommerce (Commande->paiements) reutilise la table paiements
// du salon. On ajoute une colonne commande_id nullable pour que la relation
// fonctionne sans toucher au schema existant des paiements de reservations.
// NB: l'ecriture de paiements ecommerce (markAsPaid) reste a harmoniser avec
// le schema salon (numero_recu, type, mode_paiement) — voir suivi phase 2.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table): void {
            $table->foreignId('commande_id')->nullable()->constrained('commandes')->nullOnDelete();
            $table->index(['commande_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table): void {
            $table->dropIndex(['commande_id', 'statut']);
            $table->dropConstrainedForeignId('commande_id');
        });
    }
};
