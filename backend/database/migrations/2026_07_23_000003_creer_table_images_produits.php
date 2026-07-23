
<?php
// ================================================================
// 📝 MIGRATION: creer_table_images_produits
// ================================================================
// Fichier: 2025_09_12_183112_creer_table_images_produits.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('images_produits', function (Blueprint $table) {
            $table->id();
            
            // Relation avec produit
            $table->foreignId('produit_id')->constrained('produits')->onDelete('cascade');
            
            // Informations image
            $table->string('nom_fichier'); // nom-produit-1.jpg
            $table->string('chemin_original'); // storage/produits/original/
            $table->string('chemin_miniature')->nullable(); // storage/produits/thumb/
            $table->string('chemin_moyen')->nullable(); // storage/produits/medium/
            
            // Métadonnées image
            $table->string('alt_text')->nullable(); // Pour SEO et accessibilité
            $table->string('titre')->nullable(); // Titre de l'image
            $table->text('description')->nullable(); // Description de l'image
            
            // Gestion affichage (SIMPLE pour votre amie)
            $table->integer('ordre_affichage')->default(0); // Ordre des images
            $table->boolean('est_principale')->default(false); // Image principale
            $table->boolean('est_visible')->default(true); // Visible sur le site
            
            // Informations techniques
            $table->string('format')->nullable(); // jpg, png, webp
            $table->integer('taille_octets')->nullable(); // Taille du fichier
            $table->integer('largeur')->nullable(); // Largeur en pixels
            $table->integer('hauteur')->nullable(); // Hauteur en pixels
            
            // Couleur dominante (pour interface)
            $table->string('couleur_dominante')->nullable(); // #FF5733
            
            $table->timestamps();
            
            // Index pour performance
            $table->index(['produit_id', 'ordre_affichage']);
            $table->index(['produit_id', 'est_principale']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('images_produits');
    }
};