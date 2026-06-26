<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('images_produits', function (Blueprint $table) {
            // Couleur à laquelle cette image est associée (ex: "Rouge", "Bleu")
            // Doit correspondre à une valeur dans produits.couleurs_disponibles
            $table->string('couleur_associee', 50)->nullable()->after('couleur_dominante');

            $table->index(['produit_id', 'couleur_associee']);
        });
    }

    public function down(): void
    {
        Schema::table('images_produits', function (Blueprint $table) {
            $table->dropIndex(['produit_id', 'couleur_associee']);
            $table->dropColumn('couleur_associee');
        });
    }
};
