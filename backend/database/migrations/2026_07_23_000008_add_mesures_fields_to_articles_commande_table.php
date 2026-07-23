<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles_commande', function (Blueprint $table) {
            $table->boolean('utilise_mesures_client')->default(false)->after('mesures_client');
            $table->json('ajustements_mesures')->nullable()->after('utilise_mesures_client');
        });
    }

    public function down(): void
    {
        Schema::table('articles_commande', function (Blueprint $table) {
            $table->dropColumn(['utilise_mesures_client', 'ajustements_mesures']);
        });
    }
};