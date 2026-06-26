<?php
// ================================================================
// 📝 MIGRATION 8: creer_table_paiements
// ================================================================
// Fichier: 2025_09_12_183258_creer_table_paiements.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            
            // Relations
            $table->foreignId('commande_id')->constrained('commandes');
            $table->foreignId('client_id')->constrained('clients');
            
            // Informations paiement
            $table->decimal('montant', 12, 2);
            $table->string('reference_paiement')->unique(); // PAY-2024-001
            
            // Méthodes de paiement sénégalaises
            $table->enum('methode_paiement', [
                'wave',             // Wave Money
                'orange_money',     // Orange Money
                'free_money',       // Free Money
                'especes',          // Espèces à la livraison
                'virement',         // Virement bancaire
                'cheque',           // Chèque
                'carte_bancaire'    // Carte bancaire (futur)
            ]);
            
            // Statut du paiement (SIMPLE pour votre amie)
            $table->enum('statut', [
                'en_attente',       // En attente de paiement
                'en_cours',         // Paiement en cours de traitement
                'valide',           // Paiement confirmé
                'echoue',           // Paiement échoué
                'annule',           // Paiement annulé
                'rembourse',        // Paiement remboursé
                'partiel'           // Paiement partiel (acompte)
            ])->default('en_attente');
            
            // Informations techniques (APIs paiement)
            $table->string('transaction_id')->nullable(); // ID de Wave/Orange Money
            $table->string('numero_telephone')->nullable(); // Numéro qui a payé
            $table->json('donnees_api')->nullable(); // Réponse complète de l'API
            $table->text('message_retour')->nullable(); // Message de l'API
            
            // Dates importantes
            $table->timestamp('date_initiation')->nullable(); // Quand le paiement a été initié
            $table->timestamp('date_validation')->nullable(); // Quand il a été validé
            $table->timestamp('date_echeance')->nullable(); // Date limite de paiement
            
            // Gestion des acomptes et paiements multiples
            $table->boolean('est_acompte')->default(false);
            $table->decimal('montant_restant', 12, 2)->default(0);
            $table->foreignId('paiement_parent_id')->nullable()->constrained('paiements'); // Si complément d'acompte
            
            // Informations complémentaires
            $table->text('notes_admin')->nullable(); // Notes privées admin
            $table->text('commentaire_client')->nullable(); // Commentaire du client
            $table->string('code_autorisation')->nullable(); // Code d'autorisation bancaire
            
            // Gestion des remboursements
            $table->decimal('montant_rembourse', 12, 2)->default(0);
            $table->timestamp('date_remboursement')->nullable();
            $table->text('motif_remboursement')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Index pour performance et recherche
            $table->index(['commande_id', 'statut']);
            $table->index(['client_id', 'created_at']);
            $table->index('reference_paiement');
            $table->index(['methode_paiement', 'statut']);
            $table->index('transaction_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('paiements');
    }
};