<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function indexExists($table, $indexName)
    {
        $connection = Schema::getConnection();
        $schemaName = $connection->getConfig('schema') ?: 'public';
        
        $result = DB::select(
            "SELECT 1 FROM pg_indexes WHERE schemaname = ? AND tablename = ? AND indexname = ?",
            [$schemaName, $table, $indexName]
        );
        
        return count($result) > 0;
    }

    private function createIndexIfNotExists($table, $columns, $type = 'index')
    {
        // Check if table exists first
        if (!Schema::hasTable($table)) {
            return;
        }
        
        $indexName = is_array($columns) 
            ? $table . '_' . implode('_', $columns) . '_index'
            : $table . '_' . $columns . '_index';
            
        if (!$this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $table) use ($columns, $type) {
                if ($type === 'fulltext') {
                    $table->fullText($columns);
                } else {
                    $table->index($columns);
                }
            });
        }
    }

    public function up()
    {
        // ========== TABLE PRODUITS ==========
        $this->createIndexIfNotExists('produits', 'slug');
        $this->createIndexIfNotExists('produits', 'est_visible');
        $this->createIndexIfNotExists('produits', 'categorie_id');
        $this->createIndexIfNotExists('produits', 'est_nouveaute');
        $this->createIndexIfNotExists('produits', 'est_populaire');
        $this->createIndexIfNotExists('produits', ['est_visible', 'categorie_id']);
        $this->createIndexIfNotExists('produits', ['est_visible', 'prix']);
        $this->createIndexIfNotExists('produits', ['est_visible', 'created_at']);
        $this->createIndexIfNotExists('produits', ['est_visible', 'nombre_vues']);
        $this->createIndexIfNotExists('produits', ['est_visible', 'note_moyenne']);
        $this->createIndexIfNotExists('produits', 'nom');
        
        // Full-text search pour PostgreSQL (CORRIGÉ - sans le champ tags qui est JSON)
        if (Schema::hasTable('produits') && !$this->indexExists('produits', 'produits_search_idx')) {
            DB::statement("CREATE INDEX produits_search_idx ON produits USING gin(to_tsvector('french', coalesce(nom,'') || ' ' || coalesce(description,'')))");
        }

        // ========== TABLE CATEGORIES ==========
        $this->createIndexIfNotExists('categories', 'slug');
        $this->createIndexIfNotExists('categories', 'est_active');
        $this->createIndexIfNotExists('categories', 'parent_id');
        $this->createIndexIfNotExists('categories', 'ordre_affichage');
        $this->createIndexIfNotExists('categories', ['est_active', 'ordre_affichage']);

        // ========== TABLE IMAGES_PRODUITS ==========
        $this->createIndexIfNotExists('images_produits', 'produit_id');
        $this->createIndexIfNotExists('images_produits', 'est_principale');
        $this->createIndexIfNotExists('images_produits', ['produit_id', 'ordre_affichage']);
        $this->createIndexIfNotExists('images_produits', ['produit_id', 'est_principale']);

        // ========== TABLE PROMOTIONS ==========
        $this->createIndexIfNotExists('promotions', 'code');
        $this->createIndexIfNotExists('promotions', 'est_active');
        $this->createIndexIfNotExists('promotions', 'afficher_site');
        $this->createIndexIfNotExists('promotions', ['est_active', 'date_debut', 'date_fin']);
        $this->createIndexIfNotExists('promotions', ['est_active', 'afficher_site']);

        // ========== TABLE AVIS_CLIENTS ==========
        $this->createIndexIfNotExists('avis_clients', 'produit_id');
        $this->createIndexIfNotExists('avis_clients', 'client_id');
        $this->createIndexIfNotExists('avis_clients', 'statut');
        $this->createIndexIfNotExists('avis_clients', 'est_visible');
        $this->createIndexIfNotExists('avis_clients', 'est_mis_en_avant');
        $this->createIndexIfNotExists('avis_clients', ['produit_id', 'statut']);
        $this->createIndexIfNotExists('avis_clients', ['est_visible', 'est_mis_en_avant']);

        // ========== TABLE COMMANDES ==========
        $this->createIndexIfNotExists('commandes', 'client_id');
        $this->createIndexIfNotExists('commandes', 'statut');
        $this->createIndexIfNotExists('commandes', 'numero_commande');
        $this->createIndexIfNotExists('commandes', ['client_id', 'statut']);
        $this->createIndexIfNotExists('commandes', ['statut', 'created_at']);

        // ========== TABLE CLIENTS ==========
        $this->createIndexIfNotExists('clients', 'email');
        $this->createIndexIfNotExists('clients', 'telephone');
        $this->createIndexIfNotExists('clients', 'type_client');
    }

    public function down()
    {
        $indexes = [
            'produits_search_idx',
            'produits_nom_index',
            'produits_est_visible_note_moyenne_index',
            'produits_est_visible_nombre_vues_index',
            'produits_est_visible_created_at_index',
            'produits_est_visible_prix_index',
            'produits_est_visible_categorie_id_index',
            'produits_est_populaire_index',
            'produits_est_nouveaute_index',
            'categories_est_active_ordre_affichage_index',
            'categories_ordre_affichage_index',
            'categories_parent_id_index',
            'images_produits_produit_id_est_principale_index',
            'images_produits_produit_id_ordre_affichage_index',
            'images_produits_est_principale_index',
            'promotions_est_active_afficher_site_index',
            'promotions_est_active_date_debut_date_fin_index',
            'promotions_afficher_site_index',
            'avis_clients_est_visible_est_mis_en_avant_index',
            'avis_clients_produit_id_statut_index',
            'avis_clients_est_mis_en_avant_index',
            'commandes_statut_created_at_index',
            'commandes_client_id_statut_index',
        ];

        foreach ($indexes as $indexName) {
            DB::statement("DROP INDEX IF EXISTS {$indexName}");
        }
    }
};