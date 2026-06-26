<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mesures_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            
            // Mesures en centimètres
            $table->decimal('epaule', 5, 2)->nullable();
            $table->decimal('poitrine', 5, 2)->nullable();
            $table->decimal('taille', 5, 2)->nullable();
            $table->decimal('longueur_robe', 5, 2)->nullable();
            $table->decimal('tour_bras', 5, 2)->nullable();
            $table->decimal('tour_cuisses', 5, 2)->nullable();
            $table->decimal('longueur_jupe', 5, 2)->nullable();
            $table->decimal('ceinture', 5, 2)->nullable();
            $table->decimal('tour_fesses', 5, 2)->nullable();
            $table->decimal('buste', 5, 2)->nullable();
            $table->decimal('longueur_manches_longues', 5, 2)->nullable();
            $table->decimal('longueur_manches_courtes', 5, 2)->nullable();
            $table->decimal('longueur_short', 5, 2)->nullable();
            $table->decimal('cou', 5, 2)->nullable();
            $table->decimal('longueur_taille_basse', 5, 2)->nullable();
            
            // Informations complémentaires
            $table->text('notes_mesures')->nullable();
            $table->date('date_prise_mesures')->nullable();
            $table->boolean('mesures_valides')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Index pour recherche rapide
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mesures_clients');
    }
};