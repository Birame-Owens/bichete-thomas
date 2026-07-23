<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Colonnes de variantes utilisees par ProduitController (type de produit +
// matrice couleur/taille avec stock et seuil par variante) : elles avaient
// ete ajoutees a la main dans la base du projet d'origine sans migration.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produits', function (Blueprint $table): void {
            $table->string('type_variante')->default('vetement'); // vetement|chaussure|parfum|aucun
            $table->json('couleur_tailles')->nullable();          // {"Rouge": ["S","M"], ...}
            $table->json('couleur_tailles_stock')->nullable();    // {"Rouge": {"S": 10}, ...}
            $table->json('couleur_tailles_seuil')->nullable();    // {"Rouge": {"S": 2}, ...}
        });

        Schema::table('images_produits', function (Blueprint $table): void {
            $table->string('couleur_associee')->nullable(); // Photo liee a une couleur/senteur
        });
    }

    public function down(): void
    {
        Schema::table('images_produits', function (Blueprint $table): void {
            $table->dropColumn('couleur_associee');
        });
        Schema::table('produits', function (Blueprint $table): void {
            $table->dropColumn(['type_variante', 'couleur_tailles', 'couleur_tailles_stock', 'couleur_tailles_seuil']);
        });
    }
};
