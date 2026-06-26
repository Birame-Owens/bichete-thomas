<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('articles_panier', function (Blueprint $table) {
            $table->id();
            
            // Relations
            $table->foreignId('panier_id')->constrained('paniers')->onDelete('cascade');
            $table->foreignId('produit_id')->constrained('produits');
            
            // Détails de l'article
            $table->integer('quantite');
            $table->decimal('prix_unitaire', 10, 2); // Prix au moment de l'ajout
            $table->decimal('prix_total', 12, 2); // prix_unitaire * quantite
            
            // Variantes choisies
            $table->string('taille_choisie')->nullable();
            $table->string('couleur_choisie')->nullable();
            $table->json('options_choisies')->nullable(); // Options supplémentaires
            
            // Personnalisations
            $table->text('personnalisations')->nullable(); // Demandes spéciales
            $table->json('mesures_personnalisees')->nullable(); // Si sur-mesure
            
            // Gestion stock et réservation
            $table->boolean('est_reserve')->default(false); // Article réservé en stock
            $table->timestamp('date_reservation')->nullable();
            $table->timestamp('date_expiration_reservation')->nullable();
            
            // Informations de suivi
            $table->timestamp('date_ajout'); // Quand ajouté au panier
            $table->timestamp('derniere_modification')->nullable(); // Dernière modif (quantité, etc.)
            $table->integer('nombre_modifications')->default(0); // Combien de fois modifié
            
            $table->timestamps();
            
            // Index pour performance
            $table->index(['panier_id', 'produit_id']);
            $table->index(['est_reserve', 'date_expiration_reservation']);
            $table->index('date_ajout');
        });
    }

    public function down()
    {
        Schema::dropIfExists('articles_panier');
    }
};