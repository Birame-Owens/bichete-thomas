<?php
// ================================================================
// 📝 MIGRATION 2: creer_table_categories
// ================================================================
// Fichier: database/migrations/xxxx_xx_xx_xxxxxx_creer_table_categories.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            
            // Informations de base
            $table->string('nom'); // Femme, Homme, Enfant, Accessoires
            $table->string('slug')->unique(); // femme, homme, enfant
            $table->text('description')->nullable();
            $table->string('image')->nullable(); // Image de la catégorie
            
            // Hiérarchie (pour sous-catégories futures)
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('categories')->onDelete('set null');
            
            // Gestion affichage pour votre amie
            $table->integer('ordre_affichage')->default(0); // Pour ordonner les catégories
            $table->boolean('est_active')->default(true); // Activer/désactiver facilement
            $table->boolean('est_populaire')->default(false); // Mise en avant
            
            // SEO et métadonnées
            $table->string('couleur_theme')->nullable(); // #FF5733 pour personnaliser l'interface
            $table->json('meta_donnees')->nullable(); // Données SEO flexibles
            
            $table->timestamps();
            $table->softDeletes(); // Pour ne pas perdre l'historique
            
            // Index pour performance
            $table->index(['est_active', 'ordre_affichage']);
            $table->index('slug');
        });
    }

    public function down()
    {
        Schema::dropIfExists('categories');
    }
};