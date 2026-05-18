<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('codes_promo', function (Blueprint $table): void {
            // Un seul code promo peut être affiché en popup sur la page cliente.
            // L'admin active le flag sur un code → la page cliente affiche le popup
            // au premier chargement (sessionStorage empêche l'affichage répété).
            $table->boolean('afficher_popup')->default(false)->after('actif');
            $table->index('afficher_popup');
        });
    }

    public function down(): void
    {
        Schema::table('codes_promo', function (Blueprint $table): void {
            $table->dropIndex(['afficher_popup']);
            $table->dropColumn('afficher_popup');
        });
    }
};
