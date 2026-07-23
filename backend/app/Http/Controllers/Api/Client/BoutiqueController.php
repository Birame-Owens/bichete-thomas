<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Produit;
use App\Support\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Catalogue public de la boutique (phase 2 ecommerce).
 *
 * Lecture seule : uniquement les produits visibles dont la categorie est
 * active. Meme convention que CatalogueController (coiffures) : une reponse
 * unique {categories, produits, settings} mise en cache 5 min, invalidee
 * par ProduitController::clearClientProductCaches a chaque ecriture admin.
 */
class BoutiqueController extends Controller
{
    public const CACHE_KEY = 'client:boutique:catalogue';

    public function index(Request $request): JsonResponse
    {
        $data = Cache::remember(self::CACHE_KEY, 300, fn () => $this->buildBoutiqueData());

        return response()->json(['data' => $data]);
    }

    public function show(string $slug): JsonResponse
    {
        $produit = Produit::with([
            'category',
            'images_produits' => fn ($q) => $q->where('est_visible', true)->orderBy('ordre_affichage'),
            'avis_clients' => fn ($q) => $q->where('statut', 'approuve')->latest()->take(5),
        ])
            ->where('slug', $slug)
            ->where('est_visible', true)
            ->whereHas('category', fn ($q) => $q->where('est_active', true))
            ->first();

        if (! $produit) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        return response()->json(['data' => $this->formatProduit($produit, detailed: true)]);
    }

    private function buildBoutiqueData(): array
    {
        $categories = Category::where('est_active', true)
            ->withCount(['produits' => fn ($q) => $q->where('est_visible', true)])
            ->orderBy('ordre_affichage')
            ->get()
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'nom' => $c->nom,
                'slug' => $c->slug,
                'parent_id' => $c->parent_id,
                'image' => $c->image ? asset('storage/' . $c->image) : null,
                'produits_count' => $c->produits_count,
            ])
            // ->all() : le cache Redis ne doit stocker que des tableaux purs,
            // les Collections serialisees ne survivent pas toujours (__PHP_Incomplete_Class)
            ->values()
            ->all();

        $produits = Produit::with(['category', 'images_produits' => fn ($q) => $q
                ->where('est_visible', true)->orderBy('ordre_affichage')])
            ->where('est_visible', true)
            ->whereHas('category', fn ($q) => $q->where('est_active', true))
            ->orderByDesc('est_populaire')
            ->orderBy('ordre_affichage')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (Produit $p) => $this->formatProduit($p))
            ->values()
            ->all();

        return [
            'categories' => $categories,
            'produits' => $produits,
            'settings' => [
                'devise' => SystemSettings::get('devise', 'FCFA'),
                'telephone_whatsapp' => SystemSettings::get('telephone_whatsapp', '221778153939'),
            ],
        ];
    }

    private function formatProduit(Produit $produit, bool $detailed = false): array
    {
        $enPromo = $this->isPromoActive($produit);

        $data = [
            'id' => $produit->id,
            'nom' => $produit->nom,
            'slug' => $produit->slug,
            'description_courte' => $produit->description_courte,
            'prix' => (float) $produit->prix,
            'prix_promo' => $enPromo ? (float) $produit->prix_promo : null,
            'prix_actuel' => $enPromo ? (float) $produit->prix_promo : (float) $produit->prix,
            'en_promo' => $enPromo,
            'image' => $produit->image_principale ? asset('storage/' . $produit->image_principale) : null,
            'categorie' => $produit->category ? [
                'id' => $produit->category->id,
                'nom' => $produit->category->nom,
                'slug' => $produit->category->slug,
                'parent_id' => $produit->category->parent_id,
            ] : null,
            'est_nouveaute' => (bool) $produit->est_nouveaute,
            'est_populaire' => (bool) $produit->est_populaire,
            'en_stock' => $this->isInStock($produit),
            'type_variante' => $produit->type_variante ?? 'vetement',
            // true si le produit exige un choix de variante avant achat
            'a_variantes' => ! empty(json_decode((string) $produit->couleur_tailles, true)),
            'note_moyenne' => $produit->note_moyenne !== null ? (float) $produit->note_moyenne : null,
            'nombre_avis' => (int) $produit->nombre_avis,
        ];

        if ($detailed) {
            $data += [
                'description' => $produit->description,
                'fait_sur_mesure' => (bool) $produit->fait_sur_mesure,
                'delai_production_jours' => $produit->delai_production_jours,
                'couleur_tailles' => $produit->couleur_tailles ? json_decode($produit->couleur_tailles, true) : null,
                'couleur_tailles_stock' => $produit->couleur_tailles_stock ? json_decode($produit->couleur_tailles_stock, true) : null,
                'images' => $produit->images_produits->map(fn ($image) => [
                    'id' => $image->id,
                    'url' => asset('storage/' . ($image->chemin_moyen ?: $image->chemin_original)),
                    'url_miniature' => $image->chemin_miniature ? asset('storage/' . $image->chemin_miniature) : null,
                    'alt_text' => $image->alt_text,
                    'couleur_associee' => $image->couleur_associee,
                ])->values(),
                'avis' => $produit->avis_clients->map(fn ($avis) => [
                    'id' => $avis->id,
                    'client_nom' => $avis->nom_affiche ?: 'Cliente',
                    'note' => $avis->note_globale,
                    'commentaire' => $avis->commentaire,
                    'date' => $avis->created_at->format('d/m/Y'),
                ])->values(),
            ];
        }

        return $data;
    }

    private function isPromoActive(Produit $produit): bool
    {
        if ($produit->prix_promo === null) {
            return false;
        }

        $now = now();

        if ($produit->debut_promo && $now->lt($produit->debut_promo)) {
            return false;
        }

        return ! ($produit->fin_promo && $now->gt($produit->fin_promo));
    }

    private function isInStock(Produit $produit): bool
    {
        if (! $produit->gestion_stock) {
            return true;
        }

        if ($produit->couleur_tailles_stock) {
            $stockData = json_decode($produit->couleur_tailles_stock, true) ?? [];
            foreach ($stockData as $tailles) {
                foreach ($tailles as $qty) {
                    if ((int) $qty > 0) {
                        return true;
                    }
                }
            }

            return false;
        }

        return (int) ($produit->stock_disponible ?? 0) > 0;
    }
}
