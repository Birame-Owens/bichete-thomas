<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Client;
use App\Models\Produit;
use App\Models\Category;
use App\Models\Commande;
use App\Models\ArticlesCommande;
use App\Models\AvisClient;
use App\Models\Paiement;
use App\Models\Stock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        echo "🚀 Génération des données de démonstration...\n";

        try {
            // 1. CRÉER DES CATÉGORIES
            $this->createCategories();
            
            // 2. CRÉER DES PRODUITS (avec les VRAIES colonnes)
            $this->createProduits();
            
            // 3. CRÉER DES CLIENTS
            $this->createClients();
            
            // 4. CRÉER DES COMMANDES
            $this->createCommandes();
            
            // 5. CRÉER DES PAIEMENTS
            $this->createPaiements();
            
            // 6. CRÉER DES STOCKS pour le DashboardService
            $this->createStocks();
            
            // 7. CRÉER DES AVIS
            $this->createAvis();

            echo "✅ Données de démonstration générées avec succès !\n";
            echo "📊 Dashboard prêt avec des données réalistes\n";
            
        } catch (\Exception $e) {
            echo "❌ Erreur lors de la génération : " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    private function createCategories()
    {
        $categories = [
            [
                'nom' => 'Robes Traditionnelles',
                'description' => 'Robes africaines authentiques',
                'slug' => 'robes-traditionnelles',
                'est_active' => true,
                'ordre_affichage' => 1,
            ],
            [
                'nom' => 'Costumes Hommes',
                'description' => 'Costumes sur mesure pour hommes',
                'slug' => 'costumes-hommes',
                'est_active' => true,
                'ordre_affichage' => 2,
            ],
            [
                'nom' => 'Accessoires',
                'description' => 'Bijoux et accessoires de mode',
                'slug' => 'accessoires',
                'est_active' => true,
                'ordre_affichage' => 3,
            ],
            [
                'nom' => 'Tenues Enfants',
                'description' => 'Vêtements pour enfants',
                'slug' => 'tenues-enfants',
                'est_active' => true,
                'ordre_affichage' => 4,
            ]
        ];

        foreach ($categories as $cat) {
            Category::updateOrCreate(['slug' => $cat['slug']], $cat);
        }

        echo "📁 Catégories créées\n";
    }

    private function createProduits()
    {
        $categories = Category::all();
        
        // PRODUITS avec les colonnes EXACTES de votre table
        $produits = [
            [
                'nom' => 'Robe Boubou Grand Boubou',
                'slug' => 'robe-boubou-grand-boubou',
                'description' => 'Magnifique robe traditionnelle en wax authentique avec broderies artisanales. Parfaite pour les grandes occasions et cérémonies. Disponible en plusieurs tailles.',
                'description_courte' => 'Robe traditionnelle en wax avec broderies',
                'image_principale' => 'robe-boubou.jpg',
                'categorie_id' => $categories->where('slug', 'robes-traditionnelles')->first()->id,
                'prix' => 45000.00,
                'prix_promo' => 39000.00,
                'debut_promo' => Carbon::now()->subDays(5),
                'fin_promo' => Carbon::now()->addDays(25),
                'stock_disponible' => 12,
                'seuil_alerte' => 3,
                'gestion_stock' => true,
                'fait_sur_mesure' => true,
                'delai_production_jours' => 7,
                'cout_production' => 25000.00,
                'tailles_disponibles' => json_encode(['S', 'M', 'L', 'XL', 'XXL']),
                'couleurs_disponibles' => json_encode(['Rouge', 'Bleu', 'Vert', 'Jaune']),
                'est_visible' => true,
                'est_populaire' => true,
                'est_nouveaute' => false,
                'ordre_affichage' => 1,
                'nombre_vues' => 234,
                'nombre_ventes' => 45,
                'note_moyenne' => 4.8,
                'nombre_avis' => 12,
                'created_at' => Carbon::now()->subDays(10),
            ],
            [
                'nom' => 'Kaftan Élégant Brodé',
                'slug' => 'kaftan-elegant-brode',
                'description' => 'Kaftan moderne avec broderies dorées pour cérémonies et événements spéciaux. Coupe élégante et confortable.',
                'description_courte' => 'Kaftan avec broderies dorées',
                'image_principale' => 'kaftan-elegant.jpg',
                'categorie_id' => $categories->where('slug', 'robes-traditionnelles')->first()->id,
                'prix' => 55000.00,
                'stock_disponible' => 8,
                'seuil_alerte' => 2,
                'gestion_stock' => true,
                'fait_sur_mesure' => true,
                'delai_production_jours' => 10,
                'cout_production' => 30000.00,
                'tailles_disponibles' => json_encode(['M', 'L', 'XL']),
                'couleurs_disponibles' => json_encode(['Doré', 'Blanc', 'Noir']),
                'est_visible' => true,
                'est_populaire' => false,
                'est_nouveaute' => true,
                'ordre_affichage' => 2,
                'nombre_vues' => 156,
                'nombre_ventes' => 23,
                'note_moyenne' => 4.6,
                'nombre_avis' => 8,
                'created_at' => Carbon::now()->subDays(8),
            ],
            [
                'nom' => 'Robe Cérémonie Deluxe',
                'slug' => 'robe-ceremonie-deluxe',
                'description' => 'Robe de cérémonie haut de gamme avec perles et finitions exceptionnelles. Pour vos événements les plus prestigieux.',
                'description_courte' => 'Robe de cérémonie avec perles',
                'image_principale' => 'robe-ceremonie.jpg',
                'categorie_id' => $categories->where('slug', 'robes-traditionnelles')->first()->id,
                'prix' => 95000.00,
                'stock_disponible' => 1, // STOCK CRITIQUE pour dashboard
                'seuil_alerte' => 3,
                'gestion_stock' => true,
                'fait_sur_mesure' => true,
                'delai_production_jours' => 21,
                'cout_production' => 50000.00,
                'tailles_disponibles' => json_encode(['S', 'M', 'L']),
                'couleurs_disponibles' => json_encode(['Or', 'Argent']),
                'est_visible' => true,
                'est_populaire' => false,
                'est_nouveaute' => false,
                'ordre_affichage' => 10,
                'nombre_vues' => 89,
                'nombre_ventes' => 5,
                'note_moyenne' => 5.0,
                'nombre_avis' => 3,
                'created_at' => Carbon::now()->subDays(5),
            ],
            [
                'nom' => 'Costume Grand Boubou Homme',
                'slug' => 'costume-grand-boubou-homme',
                'description' => 'Costume traditionnel pour homme, coupe moderne et élégante. Idéal pour mariages et cérémonies importantes.',
                'description_courte' => 'Costume traditionnel homme moderne',
                'image_principale' => 'costume-homme.jpg',
                'categorie_id' => $categories->where('slug', 'costumes-hommes')->first()->id,
                'prix' => 75000.00,
                'prix_promo' => 65000.00,
                'debut_promo' => Carbon::now()->subDays(3),
                'fin_promo' => Carbon::now()->addDays(27),
                'stock_disponible' => 15,
                'seuil_alerte' => 5,
                'gestion_stock' => true,
                'fait_sur_mesure' => true,
                'delai_production_jours' => 14,
                'cout_production' => 40000.00,
                'tailles_disponibles' => json_encode(['M', 'L', 'XL', 'XXL']),
                'couleurs_disponibles' => json_encode(['Blanc', 'Beige', 'Bleu marine']),
                'est_visible' => true,
                'est_populaire' => true,
                'est_nouveaute' => false,
                'ordre_affichage' => 1,
                'nombre_vues' => 312,
                'nombre_ventes' => 67,
                'note_moyenne' => 4.9,
                'nombre_avis' => 18,
                'created_at' => Carbon::now()->subDays(12),
            ],
            [
                'nom' => 'Ensemble Dashiki Premium',
                'slug' => 'ensemble-dashiki-premium',
                'description' => 'Ensemble dashiki haut de gamme avec motifs authentiques. Confortable et élégant pour toutes occasions.',
                'description_courte' => 'Ensemble dashiki authentique',
                'image_principale' => 'dashiki-premium.jpg',
                'categorie_id' => $categories->where('slug', 'costumes-hommes')->first()->id,
                'prix' => 35000.00,
                'stock_disponible' => 20,
                'seuil_alerte' => 3,
                'gestion_stock' => true,
                'fait_sur_mesure' => false,
                'delai_production_jours' => 5,
                'cout_production' => 18000.00,
                'tailles_disponibles' => json_encode(['S', 'M', 'L', 'XL']),
                'couleurs_disponibles' => json_encode(['Multicolore', 'Rouge/Noir', 'Vert/Jaune']),
                'est_visible' => true,
                'est_populaire' => false,
                'est_nouveaute' => false,
                'ordre_affichage' => 3,
                'nombre_vues' => 189,
                'nombre_ventes' => 34,
                'note_moyenne' => 4.4,
                'nombre_avis' => 9,
                'created_at' => Carbon::now()->subDays(7),
            ]
        ];

        foreach ($produits as $produit) {
            Produit::updateOrCreate(['slug' => $produit['slug']], $produit);
        }

        echo "👗 Produits créés avec les vraies colonnes\n";
    }

    private function createClients()
    {
        $clients = [
            [
                'nom' => 'Diallo',
                'prenom' => 'Aminata',
                'telephone' => '+221771111111',
                'email' => 'aminata.diallo@email.com',
                'genre' => 'femme',
                'date_naissance' => '1985-06-15',
                'adresse_principale' => 'HLM Grand Yoff, Dakar',
                'quartier' => 'Grand Yoff',
                'ville' => 'Dakar',
                'nombre_commandes' => 5,
                'total_depense' => 185000.00,
                'panier_moyen' => 37000.00,
                'derniere_commande' => Carbon::now()->subDays(3),
                'type_client' => 'regulier',
                'created_at' => Carbon::now()->subDays(15),
            ],
            [
                'nom' => 'Seck',
                'prenom' => 'Omar',
                'telephone' => '+221772222222',
                'email' => 'omar.seck@email.com',
                'genre' => 'homme',
                'date_naissance' => '1978-12-03',
                'adresse_principale' => 'Liberté 6, Dakar',
                'quartier' => 'Liberté 6',
                'ville' => 'Dakar',
                'nombre_commandes' => 8,
                'total_depense' => 320000.00,
                'panier_moyen' => 40000.00,
                'derniere_commande' => Carbon::now()->subDays(1),
                'type_client' => 'vip',
                'created_at' => Carbon::now()->subDays(12),
            ],
            [
                'nom' => 'Kane',
                'prenom' => 'Fatimata',
                'telephone' => '+221773333333',
                'email' => 'fatimata.kane@email.com',
                'genre' => 'femme',
                'date_naissance' => '1992-03-22',
                'adresse_principale' => 'Ouakam, Dakar',
                'quartier' => 'Ouakam',
                'ville' => 'Dakar',
                'nombre_commandes' => 3,
                'total_depense' => 95000.00,
                'panier_moyen' => 31666.67,
                'derniere_commande' => Carbon::now()->subWeeks(2),
                'type_client' => 'occasionnel',
                'created_at' => Carbon::now()->subDays(8),
            ]
        ];

        foreach ($clients as $client) {
            Client::updateOrCreate(['telephone' => $client['telephone']], $client);
        }

        echo "👥 Clients créés\n";
    }

    private function createCommandes()
    {
        $clients = Client::all();
        $produits = Produit::all();
        
        $commandes = [
            [
                'client_id' => $clients->first()->id,
                'numero_commande' => 'VIV-' . str_pad(1, 6, '0', STR_PAD_LEFT),
                'statut' => 'confirmee',
                'sous_total' => 39000.00,
                'frais_livraison' => 2000.00,
                'montant_total' => 41000.00,
                'adresse_livraison' => 'HLM Grand Yoff, Dakar',
                'telephone_livraison' => '+221771111111',
                'nom_destinataire' => 'Aminata Diallo',
                'mode_livraison' => 'domicile',
                'source' => 'whatsapp',
                'priorite' => 'normale',
                'created_at' => Carbon::now()->subDays(5),
                'date_confirmation' => Carbon::now()->subDays(5),
                'date_debut_production' => Carbon::now()->subDays(4),
                'date_fin_production' => Carbon::now()->subDays(1),
                'date_livraison_prevue' => Carbon::now()->addDays(2),
            ],
            [
                'client_id' => $clients->skip(1)->first()->id,
                'numero_commande' => 'VIV-' . str_pad(2, 6, '0', STR_PAD_LEFT),
                'statut' => 'en_production',
                'sous_total' => 65000.00,
                'frais_livraison' => 3000.00,
                'montant_total' => 68000.00,
                'adresse_livraison' => 'Liberté 6, Dakar',
                'telephone_livraison' => '+221772222222',
                'nom_destinataire' => 'Omar Seck',
                'mode_livraison' => 'domicile',
                'source' => 'site_web',
                'priorite' => 'urgente',
                'created_at' => Carbon::now()->subDays(3),
                'date_confirmation' => Carbon::now()->subDays(3),
                'date_debut_production' => Carbon::now()->subDays(2),
                'date_livraison_prevue' => Carbon::now()->addDays(5),
            ],
            [
                'client_id' => $clients->skip(2)->first()->id,
                'numero_commande' => 'VIV-' . str_pad(3, 6, '0', STR_PAD_LEFT),
                'statut' => 'en_attente',
                'sous_total' => 35000.00,
                'frais_livraison' => 2000.00,
                'montant_total' => 37000.00,
                'adresse_livraison' => 'Ouakam, Dakar',
                'telephone_livraison' => '+221773333333',
                'nom_destinataire' => 'Fatimata Kane',
                'mode_livraison' => 'domicile',
                'source' => 'whatsapp',
                'priorite' => 'normale',
                'created_at' => Carbon::now()->subHours(6),
            ]
        ];

        foreach ($commandes as $commandeData) {
            $commande = Commande::create($commandeData);
            
            // Articles de commande selon le DashboardService
            if (DB::getSchemaBuilder()->hasTable('articles_commande')) {
                if ($commande->numero_commande === 'VIV-000001') {
                    ArticlesCommande::create([
                        'commande_id' => $commande->id,
                        'produit_id' => $produits->where('nom', 'Robe Boubou Grand Boubou')->first()->id,
                        'nom_produit' => 'Robe Boubou Grand Boubou',
                        'prix_unitaire' => 39000.00,
                        'quantite' => 1,
                        'prix_total_article' => 39000.00,
                        'statut_production' => 'termine'
                    ]);
                }
            }
        }

        echo "🛒 Commandes créées\n";
    }

    private function createPaiements()
    {
        if (!DB::getSchemaBuilder()->hasTable('paiements')) {
            echo "⚠️ Table paiements n'existe pas\n";
            return;
        }

        $commandes = Commande::whereIn('statut', ['confirmee', 'en_production'])->get();
        
        foreach ($commandes as $commande) {
            Paiement::create([
                'commande_id' => $commande->id,
                'client_id' => $commande->client_id,
                'montant' => $commande->montant_total,
                'reference_paiement' => 'PAY-' . strtoupper(uniqid()),
                'methode_paiement' => ['wave', 'orange_money', 'especes'][rand(0, 2)],
                'statut' => 'valide',
                'transaction_id' => 'TXN-' . rand(100000, 999999),
                'date_initiation' => $commande->created_at,
                'date_validation' => $commande->created_at->addMinutes(5),
                'created_at' => $commande->created_at->addMinutes(5),
            ]);
        }

        echo "💳 Paiements créés\n";
    }

    private function createStocks()
    {
        if (!DB::getSchemaBuilder()->hasTable('stocks')) {
            echo "⚠️ Table stocks n'existe pas\n";
            return;
        }

        $produits = Produit::all();
        
        foreach ($produits as $produit) {
            try {
                // Stock initial correspondant au stock_disponible
                Stock::create([
                    'produit_id' => $produit->id,
                    'type_mouvement' => 'entree_achat',
                    'quantite' => $produit->stock_disponible,
                    'unite' => 'piece',
                    'quantite_avant' => 0,
                    'quantite_apres' => $produit->stock_disponible,
                    'prix_unitaire' => $produit->prix * 0.6, // Prix d'achat estimé
                    'valeur_totale' => $produit->stock_disponible * ($produit->prix * 0.6),
                    'devise' => 'XOF',
                    'reference_mouvement' => 'STOCK-INIT-' . $produit->id,
                    'motif' => 'Stock initial du produit',
                    'description_detaillee' => 'Ajout du stock initial lors de la création',
                    'effectue_par_nom' => 'Système',
                    'methode_saisie' => 'automatique',
                    'mouvement_valide' => true,
                    'created_at' => $produit->created_at,
                ]);
                
                echo "  ✓ Stock créé pour {$produit->nom}: {$produit->stock_disponible} unités\n";
                
            } catch (\Exception $e) {
                echo "  ⚠️ Erreur stock {$produit->nom}: " . $e->getMessage() . "\n";
            }
        }

        echo "📦 Stocks créés selon le DashboardService\n";
    }

    private function createAvis()
    {
        $clients = Client::all();
        $produits = Produit::all();
        $commandes = Commande::where('statut', 'confirmee')->get();

        $avis = [
            [
                'client_id' => $clients->first()->id,
                'produit_id' => $produits->where('nom', 'Robe Boubou Grand Boubou')->first()->id,
                'commande_id' => $commandes->first()->id ?? null,
                'titre' => 'Magnifique robe !',
                'commentaire' => 'La qualité est exceptionnelle, les finitions parfaites. Je recommande vivement cette boutique !',
                'note_globale' => 5,
                'note_qualite' => 5,
                'note_taille' => 5,
                'note_couleur' => 5,
                'recommande_produit' => true,
                'recommande_boutique' => true,
                'statut' => 'approuve',
                'est_visible' => true,
                'avis_verifie' => true,
                'created_at' => Carbon::now()->subDays(2),
            ],
            [
                'client_id' => $clients->skip(1)->first()->id,
                'produit_id' => $produits->where('nom', 'Costume Grand Boubou Homme')->first()->id,
                'titre' => 'Très satisfait du service',
                'commentaire' => 'Livraison rapide et produit conforme à mes attentes. Excellent travail !',
                'note_globale' => 4,
                'note_qualite' => 4,
                'note_taille' => 5,
                'recommande_produit' => true,
                'recommande_boutique' => true,
                'statut' => 'approuve',
                'est_visible' => true,
                'avis_verifie' => true,
                'created_at' => Carbon::now()->subDays(1),
            ]
        ];

        foreach ($avis as $avisData) {
            AvisClient::create($avisData);
        }

        echo "⭐ Avis clients créés\n";
    }
}