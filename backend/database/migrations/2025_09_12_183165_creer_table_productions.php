<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('productions', function (Blueprint $table) {
            $table->id();
            
            // Relations
            $table->foreignId('commande_id')->constrained('commandes');
            $table->foreignId('article_commande_id')->constrained('articles_commande');
            $table->foreignId('tailleur_id')->constrained('tailleurs');
            
            // Informations de production
            $table->string('numero_production')->unique(); // PROD-2024-001
            $table->text('instructions')->nullable(); // Instructions spéciales
            $table->json('mesures_client')->nullable(); // Mesures si sur-mesure
            
            // Statut de production (WORKFLOW SIMPLE)
            $table->enum('statut', [
                'planifiee',        // Production planifiée
                'materiaux_prepares', // Matériaux préparés
                'en_cours',         // En cours de confection
                'en_pause',         // Mise en pause
                'controle_qualite', // En contrôle qualité
                'terminee',         // Production terminée
                'livree_client',    // Livrée au client
                'retouchee'         // Retouches nécessaires
            ])->default('planifiee');
            
            // Dates importantes
            $table->timestamp('date_debut_prevue');
            $table->timestamp('date_fin_prevue');
            $table->timestamp('date_debut_reelle')->nullable();
            $table->timestamp('date_fin_reelle')->nullable();
            $table->integer('duree_prevue_heures'); // Durée prévue en heures
            $table->integer('duree_reelle_heures')->nullable(); // Durée réelle
            
            // Matériaux utilisés
            $table->json('tissus_utilises'); // {"tissu_id": 1, "quantite_metres": 2.5}
            $table->json('accessoires_utilises')->nullable(); // Boutons, fermetures, etc.
            $table->decimal('cout_materiaux', 10, 2)->default(0);
            
            // Coûts et tarification
            $table->decimal('cout_main_oeuvre', 10, 2); // Coût de la main d'œuvre
            $table->decimal('cout_total', 10, 2); // Coût total de production
            $table->decimal('prix_vente_final', 10, 2); // Prix de vente au client
            $table->decimal('marge_beneficiaire', 10, 2); // Marge réalisée
            
            // Qualité et contrôle
            $table->enum('niveau_difficulte', ['facile', 'moyen', 'difficile', 'expert'])->default('moyen');
            $table->boolean('controle_qualite_ok')->default(false);
            $table->text('notes_qualite')->nullable();
            $table->integer('note_qualite')->nullable(); // Note sur 5
            
            // Retouches et problèmes
            $table->boolean('retouches_necessaires')->default(false);
            $table->text('details_retouches')->nullable();
            $table->integer('temps_retouches_heures')->default(0);
            
            // Notes et commentaires
            $table->text('notes_tailleur')->nullable(); // Notes du tailleur
            $table->text('notes_admin')->nullable(); // Notes admin
            $table->text('problemes_rencontres')->nullable(); // Problèmes durant production
            
            $table->timestamps();
            
            // Index pour performance
            $table->index(['tailleur_id', 'statut']);
            $table->index(['commande_id', 'statut']);
            $table->index(['date_debut_prevue', 'date_fin_prevue']);
            $table->index('numero_production');
        });
    }

    public function down()
    {
        Schema::dropIfExists('productions');
    }
};
