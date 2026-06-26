<?php
// ================================================================
// 📝 MIGRATION: creer_table_factures (VERSION COMPLÈTE)
// ================================================================
// Fichier: 2025_09_12_183307_creer_table_factures.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('factures', function (Blueprint $table) {
            $table->id();
            
            // Relations principales
            $table->foreignId('commande_id')->constrained('commandes')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('clients');
            
            // Identification et numérotation
            $table->string('numero_facture')->unique(); // FACT-2024-001
            $table->string('numero_commande_ref'); // Référence commande affichée
            $table->enum('type_facture', ['facture', 'proforma', 'avoir', 'devis'])->default('facture');
            
            // Informations client au moment de la facture (SNAPSHOT)
            $table->string('client_nom');
            $table->string('client_prenom');
            $table->string('client_telephone');
            $table->string('client_email')->nullable();
            $table->text('client_adresse_complete')->nullable();
            $table->string('client_ville')->default('Dakar');
            
            // Informations boutique (NDEYA SHOP)
            $table->string('boutique_nom')->default('NDEYA SHOP');
            $table->string('boutique_slogan')->default('Boutique de mode sénégalaise moderne');
            $table->text('boutique_adresse')->default('Dakar, Sénégal');
            $table->string('boutique_telephone')->default('+221 77 139 73 93');
            $table->string('boutique_email')->default('diopbirame8@gmail.com');
            $table->string('boutique_site_web')->default('www.ndeya-shop.com');
            $table->string('boutique_ninea')->nullable(); // Numéro NINEA si disponible
            $table->string('boutique_rc')->nullable(); // Registre du commerce
            
            // Montants financiers détaillés (en XOF)
            $table->decimal('sous_total_ht', 12, 2); // Sous-total hors taxe
            $table->decimal('montant_remise', 10, 2)->default(0); // Remise totale
            $table->decimal('pourcentage_remise', 5, 2)->default(0); // % de remise
            $table->decimal('frais_livraison', 8, 2)->default(2000); // Frais de livraison
            $table->decimal('montant_tva', 10, 2)->default(0); // TVA 18%
            $table->decimal('taux_tva', 5, 2)->default(18.00); // Taux TVA applicable
            $table->decimal('autres_frais', 8, 2)->default(0); // Autres frais éventuels
            $table->decimal('montant_total_ht', 12, 2); // Total HT
            $table->decimal('montant_total_ttc', 12, 2); // Total TTC final
            
            // Détails des articles (SNAPSHOT COMPLET)
            $table->json('articles_facture'); // Tous les détails des articles
            /*
            Format JSON articles_facture:
            [
                {
                    "produit_id": 1,
                    "nom": "Robe Wax Moderne",
                    "description": "Belle robe en wax...",
                    "quantite": 2,
                    "prix_unitaire_ht": 25000,
                    "prix_total_ht": 50000,
                    "taille": "M",
                    "couleur": "Rouge",
                    "personnalisations": "Broderie nom"
                }
            ]
            */
            
            // Dates importantes pour suivi
            $table->date('date_emission'); // Date d'émission
            $table->date('date_echeance')->nullable(); // Date limite de paiement
            $table->date('date_livraison')->nullable(); // Date de livraison prévue
            $table->timestamp('date_paiement_complet')->nullable(); // Quand entièrement payée
            $table->timestamp('date_envoi_client')->nullable(); // Quand envoyée au client
            
            // Statut facture (WORKFLOW SIMPLE pour votre amie)
            $table->enum('statut', [
                'brouillon',            // En cours de préparation
                'finalisee',           // Finalisée mais pas envoyée
                'envoyee',             // Envoyée au client
                'payee_partiellement', // Partiellement payée
                'payee_totalement',    // Totalement payée
                'en_retard',           // En retard de paiement
                'annulee',             // Facture annulée
                'litigieuse'           // Litige en cours
            ])->default('brouillon');
            
            // Gestion des paiements
            $table->decimal('montant_paye', 12, 2)->default(0); // Montant déjà payé
            $table->decimal('montant_restant_du', 12, 2)->default(0); // Reste à payer
            $table->json('historique_paiements')->nullable(); // Historique des paiements
            /*
            Format JSON historique_paiements:
            [
                {
                    "date": "2024-09-12",
                    "montant": 50000,
                    "methode": "wave",
                    "reference": "WAV-123456"
                }
            ]
            */
            
            // Modes d'envoi et communication
            $table->boolean('envoyee_email')->default(false);
            $table->boolean('envoyee_whatsapp')->default(false);
            $table->boolean('envoyee_sms')->default(false);
            $table->boolean('remise_en_main_propre')->default(false);
            $table->timestamp('derniere_relance')->nullable(); // Dernière relance envoyée
            $table->integer('nombre_relances')->default(0); // Nombre de relances
            
            // Génération et stockage des fichiers
            $table->string('chemin_pdf')->nullable(); // Chemin du PDF généré
            $table->string('nom_fichier_pdf')->nullable(); // Nom du fichier PDF
            $table->integer('taille_fichier_octets')->nullable(); // Taille du fichier
            $table->string('hash_contenu')->nullable(); // Hash pour vérifier l'intégrité
            $table->boolean('pdf_genere')->default(false); // PDF généré avec succès
            
            // Personnalisation et template
            $table->string('template_utilise')->default('standard'); // Template de facture
            $table->string('langue_facture')->default('fr'); // Langue de la facture
            $table->json('options_affichage')->nullable(); // Options d'affichage
            
            // Textes et mentions personnalisables
            $table->text('message_client')->nullable(); // Message personnalisé au client
            $table->text('conditions_paiement')->nullable(); // Conditions de paiement
            $table->text('mentions_legales')->nullable(); // Mentions légales
            $table->text('notes_internes')->nullable(); // Notes privées admin
            $table->text('instructions_paiement')->nullable(); // Instructions pour payer
            
            // Promotions et codes promo appliqués
            $table->string('code_promo_utilise')->nullable(); // Code promo utilisé
            $table->json('promotions_appliquees')->nullable(); // Détail des promotions
            
            // Gestion des avoirs et retours
            $table->boolean('est_avoir')->default(false); // C'est un avoir
            $table->foreignId('facture_origine_id')->nullable()->constrained('factures'); // Facture d'origine si avoir
            $table->decimal('montant_avoir_total', 12, 2)->default(0); // Montant de l'avoir
            $table->text('motif_avoir')->nullable(); // Raison de l'avoir
            $table->date('date_avoir')->nullable(); // Date de l'avoir
            
            // Livraison et expédition
            $table->text('adresse_livraison_complete')->nullable(); // Adresse de livraison
            $table->string('transporteur')->nullable(); // Transporteur utilisé
            $table->string('numero_suivi')->nullable(); // Numéro de suivi
            $table->decimal('poids_total_grammes', 8, 2)->nullable(); // Poids du colis
            
            // Statistiques et analytics
            $table->integer('nombre_consultations')->default(0); // Fois consultée
            $table->timestamp('derniere_consultation')->nullable(); // Dernière consultation
            $table->string('consulte_depuis_ip')->nullable(); // IP de consultation
            
            // Workflow et validations
            $table->boolean('validee_par_admin')->default(false); // Validée par admin
            $table->string('validee_par_nom')->nullable(); // Nom de qui a validé
            $table->timestamp('date_validation')->nullable(); // Date de validation
            $table->text('commentaires_validation')->nullable(); // Commentaires validation
            
            // Intégration comptable future
            $table->string('numero_comptable')->nullable(); // Numéro comptabilité
            $table->boolean('exportee_comptabilite')->default(false); // Exportée en compta
            $table->timestamp('date_export_compta')->nullable(); // Date export compta
            
            $table->timestamps();
            $table->softDeletes(); // Pour l'historique
            
            // Index pour performance et recherche rapide
            $table->index(['numero_facture']);
            $table->index(['commande_id', 'statut']);
            $table->index(['client_id', 'date_emission']);
            $table->index(['statut', 'date_echeance']);
            $table->index(['date_emission', 'statut']);
            $table->index(['type_facture', 'statut']);
            $table->index(['montant_total_ttc', 'statut']);
            $table->fullText(['numero_facture', 'client_nom', 'client_prenom']); // Recherche textuelle
        });
    }

    public function down()
    {
        Schema::dropIfExists('factures');
    }
};