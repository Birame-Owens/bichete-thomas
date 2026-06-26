<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('avis_clients', function (Blueprint $table) {
            $table->id();
            
            // Relations
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('produit_id')->constrained('produits');
            $table->foreignId('commande_id')->nullable()->constrained('commandes'); // Commande liée
            
            // Contenu de l'avis
            $table->string('titre')->nullable(); // Titre de l'avis
            $table->text('commentaire'); // Commentaire détaillé
            $table->integer('note_globale'); // Note sur 5
            
            // Notes détaillées (optionnelles)
            $table->integer('note_qualite')->nullable(); // Qualité sur 5
            $table->integer('note_taille')->nullable(); // Taille sur 5
            $table->integer('note_couleur')->nullable(); // Couleur sur 5
            $table->integer('note_livraison')->nullable(); // Livraison sur 5
            $table->integer('note_service')->nullable(); // Service client sur 5
            
            // Informations client
            $table->string('nom_affiche')->nullable(); // Nom à afficher (peut être différent)
            $table->boolean('recommande_produit')->default(true); // Recommande le produit
            $table->boolean('recommande_boutique')->default(true); // Recommande la boutique
            
            // Statut et modération (IMPORTANT pour votre amie)
            $table->enum('statut', [
                'en_attente',       // En attente de modération
                'approuve',         // Approuvé et visible
                'rejete',           // Rejeté
                'signale',          // Signalé par d'autres clients
                'archive'           // Archivé
            ])->default('en_attente');
            
            // Informations de modération
            $table->text('raison_rejet')->nullable(); // Pourquoi rejeté
            $table->timestamp('date_moderation')->nullable(); // Quand modéré
            $table->string('modere_par')->nullable(); // Qui a modéré
            
            // Affichage et visibilité
            $table->boolean('est_visible')->default(false); // Visible sur le site
            $table->boolean('est_mis_en_avant')->default(false); // Mis en avant
            $table->integer('ordre_affichage')->default(0); // Ordre d'affichage
            
            // Interactions
            $table->integer('nombre_likes')->default(0); // Pouces levés
            $table->integer('nombre_dislikes')->default(0); // Pouces baissés
            $table->boolean('avis_verifie')->default(false); // Achat vérifié
            
            // Métadonnées
            $table->string('adresse_ip')->nullable(); // IP du client
            $table->text('user_agent')->nullable(); // Navigateur utilisé
            $table->json('photos_avis')->nullable(); // Photos jointes à l'avis
            
            // Réponse de la boutique
            $table->text('reponse_boutique')->nullable(); // Réponse de votre amie
            $table->timestamp('date_reponse')->nullable();
            $table->string('repondu_par')->nullable(); // Qui a répondu
            
            $table->timestamps();
            $table->softDeletes();
            
            // Index pour performance
            $table->index(['produit_id', 'est_visible', 'statut']);
            $table->index(['client_id', 'created_at']);
            $table->index(['note_globale', 'est_visible']);
            $table->index(['statut', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('avis_clients');
    }
};