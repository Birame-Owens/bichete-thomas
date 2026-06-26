<?php
// ================================================================
// 📝 MIGRATION: creer_table_stocks (GESTION COMPLÈTE)
// ================================================================
// Fichier: 2025_09_12_183328_creer_table_stocks.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            
            // Relations - Peut concerner produits OU tissus
            $table->foreignId('produit_id')->nullable()->constrained('produits')->onDelete('cascade');
            $table->foreignId('tissu_id')->nullable()->constrained('tissus')->onDelete('cascade');
            
            // Type de mouvement de stock (CLAIR pour votre amie)
            $table->enum('type_mouvement', [
                'entree_achat',         // Achat fournisseur
                'entree_retour_client', // Retour client
                'entree_production',    // Production terminée (produits finis)
                'entree_ajustement',    // Ajustement inventaire (correction)
                'sortie_vente',         // Vente client
                'sortie_production',    // Utilisé en production (tissus)
                'sortie_perte',         // Perte, vol, détérioration
                'sortie_don',           // Don ou échantillon
                'sortie_ajustement',    // Ajustement inventaire (correction)
                'reservation',          // Réservation temporaire (panier)
                'liberation_reservation' // Libération de réservation
            ]);
            
            // Quantités et unités
            $table->decimal('quantite', 10, 2); // Quantité (peut être négative pour sorties)
            $table->enum('unite', ['piece', 'metre', 'kg', 'lot'])->default('piece');
            $table->decimal('quantite_avant', 10, 2); // Stock avant le mouvement
            $table->decimal('quantite_apres', 10, 2); // Stock après le mouvement
            
            // Informations financières
            $table->decimal('prix_unitaire', 10, 2)->nullable(); // Prix unitaire du mouvement
            $table->decimal('valeur_totale', 12, 2)->nullable(); // Valeur totale du mouvement
            $table->string('devise')->default('XOF'); // Devise (XOF par défaut)
            
            // Références et traçabilité
            $table->string('reference_mouvement')->unique(); // STOCK-2024-001
            $table->foreignId('commande_id')->nullable()->constrained('commandes'); // Si lié à une commande
            $table->foreignId('production_id')->nullable()->constrained('productions'); // Si lié à une production
            $table->string('numero_facture_fournisseur')->nullable(); // Facture fournisseur
            $table->string('bon_livraison')->nullable(); // Numéro bon de livraison
            
            // Fournisseur (pour les entrées)
            $table->string('fournisseur_nom')->nullable();
            $table->string('fournisseur_telephone')->nullable();
            $table->text('fournisseur_adresse')->nullable();
            
            // Localisation et stockage
            $table->string('emplacement_stockage')->nullable(); // Rayonnage A-1, Zone B, etc.
            $table->string('lot_numero')->nullable(); // Numéro de lot fournisseur
            $table->date('date_peremption')->nullable(); // Date de péremption (si applicable)
            $table->date('date_achat')->nullable(); // Date d'achat fournisseur
            
            // Motif et description du mouvement
            $table->text('motif'); // Motif obligatoire du mouvement
            $table->text('description_detaillee')->nullable(); // Description détaillée
            $table->text('notes_admin')->nullable(); // Notes privées admin
            
            // Qui a effectué le mouvement
            $table->foreignId('user_id')->nullable()->constrained('users'); // Utilisateur responsable
            $table->string('effectue_par_nom'); // Nom de la personne (backup si pas d'user)
            $table->enum('methode_saisie', ['manuel', 'automatique', 'import', 'api'])->default('manuel');
            
            // Validation et contrôle
            $table->boolean('mouvement_valide')->default(true); // Mouvement validé
            $table->boolean('necessite_validation')->default(false); // Besoin validation admin
            $table->foreignId('valide_par_user_id')->nullable()->constrained('users'); // Qui a validé
            $table->timestamp('date_validation')->nullable(); // Quand validé
            
            // Réservations temporaires (IMPORTANT pour e-commerce)
            $table->boolean('est_reservation')->default(false); // C'est une réservation
            $table->timestamp('date_expiration_reservation')->nullable(); // Expiration réservation
            $table->foreignId('panier_id')->nullable()->constrained('paniers'); // Panier concerné
            $table->enum('statut_reservation', ['active', 'expiree', 'confirmee', 'annulee'])->nullable();
            
            // Informations qualité
            $table->enum('etat_produit', ['neuf', 'bon', 'moyen', 'defectueux', 'a_recycler'])->default('neuf');
            $table->text('notes_qualite')->nullable(); // Notes sur la qualité
            $table->json('defauts_constates')->nullable(); // Liste des défauts
            
            // Audit et conformité
            $table->boolean('controle_qualite_ok')->default(true); // Contrôle qualité passé
            $table->string('operateur_controle')->nullable(); // Qui a fait le contrôle
            $table->text('rapport_controle')->nullable(); // Rapport de contrôle
            
            // Informations techniques (pour les tissus)
            $table->decimal('largeur_metres', 5, 2)->nullable(); // Largeur du tissu
            $table->string('coloris')->nullable(); // Coloris spécifique
            $table->string('pattern')->nullable(); // Motif
            $table->string('composition')->nullable(); // Composition du tissu
            
            // Coûts et marges (BUSINESS)
            $table->decimal('cout_stockage', 8, 2)->default(0); // Coût de stockage
            $table->decimal('cout_transport', 8, 2)->default(0); // Coût de transport
            $table->decimal('autres_couts', 8, 2)->default(0); // Autres coûts
            $table->decimal('cout_total_unitaire', 10, 2)->nullable(); // Coût total unitaire
            
            // Prévisions et alertes
            $table->boolean('genere_alerte')->default(false); // A généré une alerte
            $table->enum('type_alerte', ['stock_bas', 'stock_zero', 'peremption', 'qualite'])->nullable();
            $table->boolean('alerte_envoyee')->default(false); // Alerte envoyée
            
            // Intégration et synchronisation
            $table->json('donnees_integration')->nullable(); // Données d'intégration externe
            $table->boolean('synchronise_comptabilite')->default(false); // Sync avec compta
            $table->timestamp('date_synchronisation')->nullable(); // Date de sync
            
            // Photos et documents
            $table->json('photos_mouvement')->nullable(); // Photos du mouvement
            $table->json('documents_joints')->nullable(); // Documents (factures, BL, etc.)
            
            $table->timestamps();
            $table->softDeletes(); // Pour l'audit et l'historique
            
            // Index pour performance et recherches
            $table->index(['produit_id', 'type_mouvement']);
            $table->index(['tissu_id', 'type_mouvement']);
            $table->index(['reference_mouvement']);
            $table->index(['commande_id', 'type_mouvement']);
            $table->index(['created_at', 'type_mouvement']);
            $table->index(['user_id', 'created_at']);
            $table->index(['est_reservation', 'date_expiration_reservation']);
            $table->index(['mouvement_valide', 'necessite_validation']);
            $table->index(['genere_alerte', 'type_alerte']);
            
            // Contraintes pour éviter les erreurs
            // Au moins un produit_id ou tissu_id doit être renseigné
           
        });
    }

    public function down()
    {
        Schema::dropIfExists('stocks');
    }
};