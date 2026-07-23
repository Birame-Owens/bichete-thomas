<?php
// ================================================================
// 📝 MIGRATION 3: creer_table_produits (CŒUR DU SYSTÈME)
// ================================================================
// Fichier: database/migrations/xxxx_xx_xx_xxxxxx_creer_table_produits.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('produits', function (Blueprint $table) {
            $table->id();
            
            // Informations de base
            $table->string('nom'); // Nom du produit
            $table->string('slug')->unique(); // URL-friendly
            $table->text('description'); // Description détaillée
            $table->text('description_courte')->nullable(); // Pour liste produits
            
            // Images
            $table->string('image_principale'); // Photo principale obligatoire
            
            // Prix (en XOF - Francs CFA)
            $table->decimal('prix', 10, 2); // Prix de base
            $table->decimal('prix_promo', 10, 2)->nullable(); // Prix promotion
            $table->date('debut_promo')->nullable(); // Début promotion
            $table->date('fin_promo')->nullable(); // Fin promotion
            
            // Relation catégorie
            $table->foreignId('categorie_id')->constrained('categories')->onDelete('cascade');
            
            // Gestion stock SIMPLE pour votre amie
            $table->integer('stock_disponible')->default(0); // Stock actuel
            $table->integer('seuil_alerte')->default(5); // Alerte stock bas
            $table->boolean('gestion_stock')->default(true); // Activer/désactiver gestion stock
            
            // Types de production
            $table->boolean('fait_sur_mesure')->default(false); // Sur mesure ou stock
            $table->integer('delai_production_jours')->nullable(); // Délai si sur mesure
            $table->decimal('cout_production', 8, 2)->nullable(); // Coût de production
            
            // Variantes produit (SIMPLE)
            $table->json('tailles_disponibles')->nullable(); // ["S", "M", "L", "XL", "XXL"]
            $table->json('couleurs_disponibles')->nullable(); // ["Rouge", "Bleu", "Vert"]
            $table->json('materiaux_necessaires')->nullable(); // Tissus et quantités
            
            // Gestion affichage (INTERFACE ADMIN SIMPLE)
            $table->boolean('est_visible')->default(true); // Visible sur le site
            $table->boolean('est_populaire')->default(false); // Produit mis en avant
            $table->boolean('est_nouveaute')->default(false); // Nouveau produit
            $table->integer('ordre_affichage')->default(0); // Ordre d'affichage
            
            // Statistiques pour votre amie
            $table->integer('nombre_vues')->default(0); // Vues produit
            $table->integer('nombre_ventes')->default(0); // Ventes totales
            $table->decimal('note_moyenne', 3, 2)->default(5.00); // Note sur 5
            $table->integer('nombre_avis')->default(0); // Nombre d'avis
            
            // SEO et référencement
            $table->string('meta_titre')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('tags')->nullable(); // Tags pour recherche
            
            $table->timestamps();
            $table->softDeletes(); // Historique des produits
            
            // Index pour performance et recherche
            $table->index(['categorie_id', 'est_visible']);
            $table->index(['est_populaire', 'ordre_affichage']);
            $table->index('slug');
            $table->fullText(['nom', 'description']); // Recherche textuelle
        });
    }

    public function down()
    {
        Schema::dropIfExists('produits');
    }
};