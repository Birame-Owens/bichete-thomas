<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            // Tailles disponibles par couleur : {"Rouge":["S","M","L"],"Bleu":["XL","XXL"]}
            $table->json('couleur_tailles')->nullable()->after('couleurs_disponibles');
        });
    }

    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn('couleur_tailles');
        });
    }
};
