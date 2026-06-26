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

        // articles_commande : stats par couleur et taille
        if (Schema::hasTable('articles_commande')) {
            DB::statement("CREATE INDEX IF NOT EXISTS articles_commande_couleur_idx ON articles_commande (couleur_choisie) WHERE couleur_choisie IS NOT NULL");
            DB::statement("CREATE INDEX IF NOT EXISTS articles_commande_taille_idx ON articles_commande (taille_choisie) WHERE taille_choisie IS NOT NULL AND taille_choisie <> 'unique'");
            DB::statement("CREATE INDEX IF NOT EXISTS articles_commande_commande_couleur_idx ON articles_commande (commande_id, couleur_choisie)");
            DB::statement("CREATE INDEX IF NOT EXISTS articles_commande_commande_taille_idx ON articles_commande (commande_id, taille_choisie)");
        }

        // commandes : filtres par statut + date (requêtes rapports)
        if (Schema::hasTable('commandes')) {
            DB::statement("CREATE INDEX IF NOT EXISTS commandes_statut_date_idx ON commandes (statut, created_at DESC) WHERE deleted_at IS NULL");
            DB::statement("CREATE INDEX IF NOT EXISTS commandes_client_statut_idx ON commandes (client_id, statut) WHERE deleted_at IS NULL");
        }

        // produits : slug lookup (clé de routage principale)
        if (Schema::hasTable('produits')) {
            DB::statement("CREATE INDEX IF NOT EXISTS produits_slug_idx ON produits (slug) WHERE deleted_at IS NULL");
            DB::statement("CREATE INDEX IF NOT EXISTS produits_vues_idx ON produits (nombre_vues DESC) WHERE est_visible = true AND deleted_at IS NULL");
        }

        // paniers : lookup par identifiant de session
        if (Schema::hasTable('paniers')) {
            DB::statement("CREATE INDEX IF NOT EXISTS paniers_identifiant_idx ON paniers (identifiant)");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ([
            'paniers_identifiant_idx',
            'produits_vues_idx',
            'produits_slug_idx',
            'commandes_client_statut_idx',
            'commandes_statut_date_idx',
            'articles_commande_commande_taille_idx',
            'articles_commande_commande_couleur_idx',
            'articles_commande_taille_idx',
            'articles_commande_couleur_idx',
        ] as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }
    }
};
