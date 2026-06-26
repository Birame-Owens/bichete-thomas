<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            
            // Informations de base
            $table->string('nom'); // "Promo Ramadan 2024"
            $table->string('code')->unique()->nullable(); // "RAMADAN2024" (optionnel)
            $table->text('description');
            $table->string('image')->nullable(); // Image de la promotion
            
            // Type de promotion (SIMPLE pour votre amie)
            $table->enum('type_promotion', [
                'pourcentage',      // 20% de réduction
                'montant_fixe',     // 5000 XOF de réduction
                'livraison_gratuite', // Livraison offerte
                'cadeau',           // Produit cadeau
                'deux_pour_un'      // 2 pour 1
            ]);
            
            // Valeur de la promotion
            $table->decimal('valeur', 10, 2); // 20 (pour 20%) ou 5000 (pour 5000 XOF)
            $table->decimal('montant_minimum', 10, 2)->nullable(); // Montant minimum de commande
            $table->decimal('reduction_maximum', 10, 2)->nullable(); // Réduction maximum
            
            // Période de validité
            $table->timestamp('date_debut');
            $table->timestamp('date_fin');
            $table->boolean('est_active')->default(true);
            
            // Limitations d'usage
            $table->integer('utilisation_maximum')->nullable(); // Nombre max d'utilisations
            $table->integer('utilisation_par_client')->default(1); // Usage par client
            $table->integer('nombre_utilisations')->default(0); // Nombre actuel d'utilisations
            
            // Ciblage (MARKETING pour votre amie)
            $table->enum('cible_client', ['tous', 'nouveaux', 'reguliers', 'vip'])->default('tous');
            $table->json('categories_eligibles')->nullable(); // Catégories concernées
            $table->json('produits_eligibles')->nullable(); // Produits spécifiques
            
            // Conditions spéciales
            $table->boolean('cumul_avec_autres')->default(false); // Cumulable avec autres promos
            $table->boolean('premiere_commande_seulement')->default(false); // Première commande uniquement
            $table->json('jours_semaine_valides')->nullable(); // Jours de validité
            
            // Affichage et communication
            $table->boolean('afficher_site')->default(true); // Afficher sur le site
            $table->boolean('envoyer_whatsapp')->default(false); // Envoyer par WhatsApp
            $table->boolean('envoyer_email')->default(false); // Envoyer par email
            $table->string('couleur_affichage')->nullable(); // Couleur du badge promo
            
            // Statistiques
            $table->decimal('chiffre_affaires_genere', 12, 2)->default(0);
            $table->integer('nombre_commandes')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Index pour performance
            $table->index(['est_active', 'date_debut', 'date_fin']);
            $table->index('code');
            $table->index(['cible_client', 'est_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('promotions');
    }
};