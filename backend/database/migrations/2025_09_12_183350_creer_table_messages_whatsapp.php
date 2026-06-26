<?php
// ================================================================
// 📝 MIGRATION 11: creer_table_messages_whatsapp
// ================================================================
// Fichier: 2025_09_12_183xxx_creer_table_messages_whatsapp.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('messages_whatsapp', function (Blueprint $table) {
            $table->id();
            
            // Destinataire
            $table->string('numero_destinataire'); // +221771397393
            $table->string('nom_destinataire')->nullable();
            
            // Contenu du message
            $table->text('message'); // Contenu du message
            $table->enum('type_message', [
                'confirmation_commande',    // Confirmation de commande
                'statut_production',        // Mise à jour production
                'pret_livraison',          // Prêt à livrer
                'rappel_paiement',         // Rappel paiement
                'promotion',               // Message promotionnel
                'remerciement',            // Message de remerciement
                'sav',                     // Service après-vente
                'autre'                    // Autre type
            ]);
            
            // Relations (optionnelles)
            $table->foreignId('commande_id')->nullable()->constrained('commandes');
            $table->foreignId('client_id')->nullable()->constrained('clients');
            
            // Statut d'envoi
            $table->enum('statut', [
                'en_attente',       // En attente d'envoi
                'envoye',          // Envoyé avec succès
                'livre',           // Livré (reçu par le destinataire)
                'lu',              // Lu par le destinataire
                'echoue',          // Échec d'envoi
                'annule'           // Envoi annulé
            ])->default('en_attente');
            
            // Informations techniques (Twilio/WhatsApp Business API)
            $table->string('message_id_api')->nullable(); // ID retourné par l'API
            $table->json('reponse_api')->nullable(); // Réponse complète de l'API
            $table->text('erreur_api')->nullable(); // Message d'erreur si échec
            
            // Programmation d'envoi
            $table->timestamp('date_programmee')->nullable(); // Envoi programmé
            $table->timestamp('date_envoi_reelle')->nullable(); // Quand envoyé réellement
            $table->timestamp('date_livraison')->nullable(); // Quand livré
            $table->timestamp('date_lecture')->nullable(); // Quand lu
            
            // Gestion admin (pour votre amie)
            $table->boolean('est_automatique')->default(false); // Message automatique ou manuel
            $table->string('envoye_par')->nullable(); // Qui a envoyé (nom admin)
            $table->text('notes_admin')->nullable(); // Notes privées
            
            $table->timestamps();
            
            // Index pour performance
            $table->index(['numero_destinataire', 'created_at']);
            $table->index(['statut', 'date_programmee']);
            $table->index(['commande_id', 'type_message']);
            $table->index('message_id_api');
        });
    }

    public function down()
    {
        Schema::dropIfExists('messages_whatsapp');
    }
};