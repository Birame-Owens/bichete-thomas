<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('paniers', function (Blueprint $table) {
            $table->id();
            
            // Identification du panier
            $table->string('session_id')->nullable(); // Pour visiteurs non connectés
            $table->foreignId('client_id')->nullable()->constrained('clients'); // Pour clients connectés
            
            // Informations panier
            $table->decimal('sous_total', 12, 2)->default(0); // Sous-total du panier
            $table->integer('nombre_articles')->default(0); // Nombre d'articles
            
            // Gestion des réservations temporaires (IMPORTANT)
            $table->timestamp('date_reservation')->nullable(); // Quand les articles ont été réservés
            $table->timestamp('date_expiration')->nullable(); // Quand la réservation expire
            $table->boolean('est_reserve')->default(false); // Panier avec réservation active
            
            // Statut du panier
            $table->enum('statut', [
                'actif',           // Panier en cours d'utilisation
                'abandonne',       // Panier abandonné
                'transforme',      // Transformé en commande
                'expire',          // Réservation expirée
                'fusionne'         // Fusionné avec un autre panier
            ])->default('actif');
            
            // Informations de conversion
            $table->foreignId('commande_id')->nullable()->constrained('commandes'); // Si transformé en commande
            $table->timestamp('date_transformation')->nullable();
            
            // Données de session et navigation
            $table->string('adresse_ip')->nullable(); // IP du visiteur
            $table->text('user_agent')->nullable(); // Navigateur utilisé
            $table->json('donnees_navigation')->nullable(); // Pages visitées, etc.
            
            // Marketing et abandons
            $table->boolean('email_abandon_envoye')->default(false); // Email de relance envoyé
            $table->boolean('whatsapp_abandon_envoye')->default(false); // WhatsApp de relance envoyé
            $table->timestamp('derniere_activite')->nullable(); // Dernière modification
            
            $table->timestamps();
            
            // Index pour performance
            $table->index(['client_id', 'statut']);
            $table->index(['session_id', 'statut']);
            $table->index(['est_reserve', 'date_expiration']);
            $table->index('derniere_activite');
        });
    }

    public function down()
    {
        Schema::dropIfExists('paniers');
    }
};

// ================================================================
// 📝 MIGRATION 13: creer_table_articles_panier
// ================================================================
// Fichier: 2025_09_12_183215_creer_table_articles_panier.php

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