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
        Schema::table('articles_panier', function (Blueprint $table) {
            $table->string('taille')->nullable()->after('quantite');
            $table->string('couleur')->nullable()->after('taille');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles_panier', function (Blueprint $table) {
            $table->dropColumn(['taille', 'couleur']);
        });
    }
};
