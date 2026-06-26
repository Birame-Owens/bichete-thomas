<?php
// ================================================================
// 📝 MIGRATION 5: creer_table_tailleurs
// ================================================================
// Fichier: 2025_09_12_183155_creer_table_tailleurs.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tailleurs', function (Blueprint $table) {
            $table->id();
            
            // Informations personnelles
            $table->string('nom');
            $table->string('prenom');
            $table->string('telephone')->unique();
            $table->string('email')->nullable();
            $table->text('adresse')->nullable();
            
            // Compétences et spécialités
            $table->json('specialites'); // ["femme", "homme", "enfant", "retouches"]
            $table->enum('niveau_competence', ['debutant', 'intermediaire', 'expert'])->default('intermediaire');
            $table->text('description_competences')->nullable();
            
            // Gestion des coûts (IMPORTANT pour votre amie)
            $table->decimal('tarif_journalier', 8, 2); // Salaire par jour
            $table->decimal('tarif_piece', 8, 2)->nullable(); // Ou par pièce
            $table->enum('mode_paiement', ['journalier', 'piece', 'mixte'])->default('journalier');
            
            // Disponibilité
            $table->json('jours_travail')->default('["lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi"]');
            $table->time('heure_debut')->default('08:00');
            $table->time('heure_fin')->default('18:00');
            $table->boolean('est_disponible')->default(true);
            
            // Historique emploi
            $table->date('date_embauche');
            $table->date('date_fin_contrat')->nullable();
            $table->enum('statut_emploi', ['actif', 'conge', 'arret', 'termine'])->default('actif');
            
            // Statistiques performance (DASHBOARD)
            $table->integer('pieces_completees')->default(0);
            $table->integer('commandes_en_cours')->default(0);
            $table->decimal('temps_moyen_piece', 5, 2)->default(0); // Heures par pièce
            $table->decimal('evaluation_moyenne', 3, 2)->default(5.00); // Note sur 5
            $table->integer('nombre_evaluations')->default(0);
            
            // Gestion admin (NOTES pour votre amie)
            $table->text('notes_admin')->nullable(); // Notes privées
            $table->enum('performance', ['excellent', 'bon', 'moyen', 'faible'])->default('bon');
            $table->boolean('peut_formation')->default(true); // Peut former d'autres tailleurs
            
            // Relation avec utilisateur (optionnel)
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Index pour performance
            $table->index(['est_disponible', 'statut_emploi']);
            $table->index('telephone');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tailleurs');
    }
};