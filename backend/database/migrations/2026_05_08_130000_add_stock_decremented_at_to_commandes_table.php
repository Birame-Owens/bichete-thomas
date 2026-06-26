<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->timestamp('stock_decremented_at')->nullable()->after('date_confirmation');
        });

        DB::table('commandes')
            ->whereIn('statut', ['confirmee', 'en_preparation', 'prete', 'en_livraison', 'livree'])
            ->update(['stock_decremented_at' => now()]);

        $pendingArticles = DB::table('articles_commande as ac')
            ->join('commandes as c', 'c.id', '=', 'ac.commande_id')
            ->join('produits as p', 'p.id', '=', 'ac.produit_id')
            ->where('c.statut', 'en_attente')
            ->where('p.gestion_stock', true)
            ->select('ac.produit_id', DB::raw('SUM(ac.quantite) as total_quantite'))
            ->groupBy('ac.produit_id')
            ->get();

        foreach ($pendingArticles as $article) {
            DB::table('produits')
                ->where('id', $article->produit_id)
                ->increment('stock_disponible', (int) $article->total_quantite);
        }

        $pendingSiteArticles = DB::table('articles_commande as ac')
            ->join('commandes as c', 'c.id', '=', 'ac.commande_id')
            ->where('c.statut', 'en_attente')
            ->where('c.source', 'site_web')
            ->select('ac.produit_id', DB::raw('SUM(ac.quantite) as total_quantite'))
            ->groupBy('ac.produit_id')
            ->get();

        foreach ($pendingSiteArticles as $article) {
            DB::table('produits')
                ->where('id', $article->produit_id)
                ->update([
                    'nombre_ventes' => DB::raw('GREATEST(nombre_ventes - ' . (int) $article->total_quantite . ', 0)'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->dropColumn('stock_decremented_at');
        });
    }
};
