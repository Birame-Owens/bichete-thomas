<?php
// ================================================================
// 📝 MIGRATION 7: creer_table_articles_commande
// ================================================================
// Fichier: 2025_09_12_183247_creer_table_articles_commande.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('articles_commande', function (Blueprint $table) {
            $table->id();
            
            // Relations
            $table->foreignId('commande_id')->constrained('commandes')->onDelete('cascade');
            $table->foreignId('produit_id')->constrained('produits');
            
            // Informations produit au moment de la commande (HISTORIQUE)
            $table->string('nom_produit'); // Nom au moment de la commande
            $table->text('description_produit')->nullable(); // Description au moment
            $table->decimal('prix_unitaire', 10, 2); // Prix au moment de la commande
            $table->integer('quantite');
            $table->decimal('prix_total_article', 12, 2); // prix_unitaire * quantite
            
            // Variantes choisies par le client
            $table->string('taille_choisie')->nullable(); // S, M, L, XL
            $table->string('couleur_choisie')->nullable(); // Rouge, Bleu, etc.
            $table->json('options_supplementaires')->nullable(); // Broderie, etc.
            
            // Personnalisations (IMPORTANT pour sur-mesure)
            $table->text('demandes_personnalisation')->nullable(); // Demandes spéciales
            $table->json('mesures_client')->nullable(); // Mesures si sur-mesure
            $table->text('instructions_tailleur')->nullable(); // Instructions spéciales
            
            // Production et affectation
            $table->foreignId('tailleur_id')->nullable()->constrained('tailleurs');
            $table->enum('statut_production', [
                'en_attente',       // En attente d'affectation
                'affecte',          // Affecté à un tailleur
                'en_cours',         // En cours de production
                'termine',          // Production terminée
                'controle_qualite', // En contrôle qualité
                'pret',            // Prêt à livrer
                'en_retard'        // En retard sur délai
            ])->default('en_attente');
            
            // Dates de production
            $table->timestamp('date_affectation')->nullable();
            $table->timestamp('date_debut_production')->nullable();
            $table->timestamp('date_fin_prevue')->nullable();
            $table->timestamp('date_fin_reelle')->nullable();
            
            // Gestion des matériaux
            $table->json('tissus_utilises')->nullable(); // Tissus et quantités
            $table->decimal('cout_materiaux', 8, 2)->nullable();
            $table->decimal('temps_production_heures', 5, 2)->nullable();
            
            // Qualité et validation
            $table->boolean('controle_qualite_ok')->default(false);
            $table->text('notes_qualite')->nullable();
            $table->integer('note_client_article')->nullable(); // Note spécifique à cet article
            
            $table->timestamps();
            
            // Index pour performance
            $table->index(['commande_id', 'statut_production']);
            $table->index(['tailleur_id', 'statut_production']);
            $table->index(['produit_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('articles_commande');
    }
};