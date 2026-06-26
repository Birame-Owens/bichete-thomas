<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        if (Schema::hasTable('produits')) {
            DB::statement("CREATE INDEX IF NOT EXISTS produits_nom_trgm_idx ON produits USING gin (nom gin_trgm_ops)");
            DB::statement("CREATE INDEX IF NOT EXISTS produits_description_trgm_idx ON produits USING gin (description gin_trgm_ops)");
            DB::statement("CREATE INDEX IF NOT EXISTS produits_tags_text_trgm_idx ON produits USING gin ((tags::text) gin_trgm_ops)");
            DB::statement("CREATE INDEX IF NOT EXISTS produits_visible_popular_home_idx ON produits (nombre_ventes DESC, note_moyenne DESC) WHERE est_visible = true AND est_populaire = true AND deleted_at IS NULL");
            DB::statement("CREATE INDEX IF NOT EXISTS produits_visible_new_home_idx ON produits (created_at DESC) WHERE est_visible = true AND est_nouveaute = true AND deleted_at IS NULL");
            DB::statement("CREATE INDEX IF NOT EXISTS produits_visible_category_recent_idx ON produits (categorie_id, created_at DESC) WHERE est_visible = true AND deleted_at IS NULL");
            DB::statement("CREATE INDEX IF NOT EXISTS produits_visible_rating_idx ON produits (note_moyenne DESC, nombre_avis DESC) WHERE est_visible = true AND deleted_at IS NULL");
            DB::statement("CREATE INDEX IF NOT EXISTS produits_visible_sales_idx ON produits (nombre_ventes DESC) WHERE est_visible = true AND deleted_at IS NULL");
        }

        if (Schema::hasTable('categories')) {
            DB::statement("CREATE INDEX IF NOT EXISTS categories_nom_trgm_idx ON categories USING gin (nom gin_trgm_ops)");
            DB::statement("CREATE INDEX IF NOT EXISTS categories_active_order_idx ON categories (ordre_affichage ASC) WHERE est_active = true");
        }

        if (Schema::hasTable('avis_clients')) {
            DB::statement("CREATE INDEX IF NOT EXISTS avis_clients_home_visible_idx ON avis_clients (est_mis_en_avant DESC, ordre_affichage ASC, created_at DESC) WHERE statut = 'approuve' AND est_visible = true AND note_globale >= 4 AND deleted_at IS NULL");
            DB::statement("CREATE INDEX IF NOT EXISTS avis_clients_unique_review_lookup_idx ON avis_clients (client_id, commande_id, produit_id) WHERE deleted_at IS NULL");
        }

        if (Schema::hasTable('commandes')) {
            DB::statement("CREATE INDEX IF NOT EXISTS commandes_client_recent_idx ON commandes (client_id, created_at DESC) WHERE deleted_at IS NULL");
        }

        if (Schema::hasTable('paiements')) {
            DB::statement("CREATE INDEX IF NOT EXISTS paiements_commande_recent_idx ON paiements (commande_id, created_at DESC)");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ([
            'paiements_commande_recent_idx',
            'commandes_client_recent_idx',
            'avis_clients_unique_review_lookup_idx',
            'avis_clients_home_visible_idx',
            'categories_active_order_idx',
            'categories_nom_trgm_idx',
            'produits_visible_sales_idx',
            'produits_visible_rating_idx',
            'produits_visible_category_recent_idx',
            'produits_visible_new_home_idx',
            'produits_visible_popular_home_idx',
            'produits_tags_text_trgm_idx',
            'produits_description_trgm_idx',
            'produits_nom_trgm_idx',
        ] as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }
    }
};
