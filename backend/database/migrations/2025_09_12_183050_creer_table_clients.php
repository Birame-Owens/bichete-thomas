<?php
// ================================================================
// 📝 MIGRATION 4: creer_table_clients
// ================================================================
// Fichier: database/migrations/xxxx_xx_xx_xxxxxx_creer_table_clients.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            
            // Informations personnelles
            $table->string('nom');
            $table->string('prenom');
            $table->string('telephone')->unique(); // Principal moyen de contact au Sénégal
            $table->string('email')->nullable(); // Optionnel
            $table->enum('genre', ['homme', 'femme', 'autre'])->nullable();
            $table->date('date_naissance')->nullable();
            
            // Adresses (SIMPLE pour votre amie)
            $table->text('adresse_principale')->nullable();
            $table->string('quartier')->nullable(); // Important au Sénégal
            $table->string('ville')->default('Dakar');
            $table->text('indications_livraison')->nullable(); // Directions spéciales
            
            // Préférences client (BUSINESS INTELLIGENCE)
            $table->string('taille_habituelle')->nullable(); // S, M, L, XL
            $table->json('couleurs_preferees')->nullable(); // ["Rouge", "Bleu"]
            $table->json('styles_preferes')->nullable(); // Types de vêtements préférés
            $table->decimal('budget_moyen', 10, 2)->nullable(); // Budget habituel
            
            // Statistiques business (DASHBOARD ADMIN)
            $table->integer('nombre_commandes')->default(0);
            $table->decimal('total_depense', 12, 2)->default(0); // Total dépensé
            $table->decimal('panier_moyen', 10, 2)->default(0); // Panier moyen
            $table->timestamp('derniere_commande')->nullable();
            $table->timestamp('derniere_visite')->nullable();
            
            // Segmentation client (MARKETING)
            $table->enum('type_client', [
                'nouveau',          // Premier achat
                'occasionnel',      // 2-5 achats
                'regulier',         // 6+ achats
                'vip',             // Gros montants
                'inactif'          // Pas d'achat récent
            ])->default('nouveau');
            
            $table->integer('score_fidelite')->default(0); // Points fidélité
            
            // Communication et marketing
            $table->boolean('accepte_whatsapp')->default(true);
            $table->boolean('accepte_email')->default(true);
            $table->boolean('accepte_sms')->default(true);
            $table->boolean('accepte_promotions')->default(true);
            $table->json('canaux_preferes')->nullable(); // Canaux de communication préférés
            
            // Relation avec utilisateur (optionnel)
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Notes admin (IMPORTANT pour votre amie)
            $table->text('notes_privees')->nullable(); // Notes privées admin
            $table->enum('priorite', ['normale', 'importante', 'vip'])->default('normale');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Index pour performance
            $table->index('telephone');
            $table->index(['type_client', 'derniere_commande']);
            $table->index('email');
        });
    }

    public function down()
    {
        Schema::dropIfExists('clients');
    }
};