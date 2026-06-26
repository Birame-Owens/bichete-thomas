<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Produit;
use App\Models\AvisClient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\ShopSetting;

class SeoController extends Controller
{
    public function robots()
    {
        $sitemapUrl = $this->publicUrl('/sitemap.xml');
        $llmsUrl = $this->publicUrl('/llms.txt');
        $adminPath = '/' . (trim((string) env('ADMIN_PATH', 'ndeya-backoffice'), '/') ?: 'ndeya-backoffice');

        return response(
            "User-agent: *\n" .
            "Allow: /\n" .
            "Disallow: /admin\n" .
            "Disallow: {$adminPath}\n" .
            "Disallow: /api\n" .
            "Disallow: /checkout\n" .
            "Disallow: /panier\n" .
            "Disallow: /favoris\n" .
            "Disallow: /profil\n" .
            "Disallow: /compte\n\n" .
            "Sitemap: {$sitemapUrl}\n" .
            "LLMs: {$llmsUrl}\n",
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }

    public function sitemap()
    {
        $urls = collect([
            $this->urlEntry($this->publicUrl('/'), now(), 'daily', '1.0'),
            $this->urlEntry($this->publicUrl('/categories'), now(), 'daily', '0.9'),
        ]);

        Category::query()
            ->where('est_active', true)
            ->orderByDesc('updated_at')
            ->get(['slug', 'updated_at'])
            ->each(function (Category $category) use ($urls) {
                $urls->push($this->urlEntry(
                    $this->publicUrl("/categories/{$category->slug}"),
                    $category->updated_at,
                    'weekly',
                    '0.8'
                ));
            });

        Produit::query()
            ->where('est_visible', true)
            ->whereHas('category', fn ($q) => $q->where('est_active', true))
            ->orderByDesc('updated_at')
            ->get(['slug', 'updated_at'])
            ->each(function (Produit $produit) use ($urls) {
                $urls->push($this->urlEntry(
                    $this->publicUrl("/produits/{$produit->slug}"),
                    $produit->updated_at,
                    'weekly',
                    '0.9'
                ));
            });

        $xml = $this->renderSitemapXml($urls);

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function llms()
    {
        $products = Produit::query()
            ->where('est_visible', true)
            ->whereHas('category', fn ($q) => $q->where('est_active', true))
            ->with('category')
            ->orderByDesc('updated_at')
            ->limit(80)
            ->get();

        $lines = [
            '# ' . $this->shopName(),
            '',
            '> Catalogue public de produits pour les moteurs de recherche et les assistants IA.',
            '',
            'Site: ' . $this->publicUrl('/'),
            'Sitemap: ' . $this->publicUrl('/sitemap.xml'),
            'Products feed: ' . $this->publicUrl('/products-feed.json'),
            'Contact: ' . ShopSetting::getValue('boutique_email', config('app.contact_email', '')),
            'Location: ' . ShopSetting::getValue('boutique_adresse', 'Dakar, Senegal'),
            '',
            '## Boutique',
            '',
            $this->shopDescription(),
            '',
            '## Produits',
            '',
        ];

        foreach ($products as $product) {
            $description = $this->limitDescription($product->description_courte ?: $product->description);
            $price = number_format((float) ($product->prix_promo ?: $product->prix), 0, ',', ' ');
            $lines[] = '- ' . $product->nom . ' - ' . $price . ' XOF - ' . $this->publicUrl("/produits/{$product->slug}") . ($description ? ' - ' . $description : '');
        }

        return response(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function productFeed(): JsonResponse
    {
        $products = Produit::query()
            ->where('est_visible', true)
            ->whereHas('category', fn ($q) => $q->where('est_active', true))
            ->with(['category', 'images_produits' => fn ($q) => $q->where('est_visible', true)->orderBy('ordre_affichage')])
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();

        $payload = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $this->shopName() . ' - Catalogue produits',
            'url' => $this->publicUrl('/products-feed.json'),
            'dateModified' => now()->toAtomString(),
            'numberOfItems' => $products->count(),
            'itemListElement' => $products->values()->map(fn (Produit $product, int $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'url' => $this->publicUrl("/produits/{$product->slug}"),
                'item' => $this->productSchema($product, $this->productImage($product)),
            ])->values(),
        ];

        return response()->json($payload, 200, [
            'Content-Type' => 'application/ld+json; charset=UTF-8',
        ]);
    }

    public function clientApp(Request $request, string $path = '')
    {
        // $path vient de la route /seo-render/{path} (proxy bots). Vide = accueil.
        return response()
            ->view('client.client', ['seo' => $this->seoForPath(trim($path, '/'))])
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Cartes produits (nom + url + prix) pour les pages accueil/catégorie crawler.
     */
    private function productCards(int $limit, ?Category $category = null): array
    {
        $query = Produit::query()
            ->where('est_visible', true)
            ->whereHas('category', fn ($q) => $q->where('est_active', true))
            ->orderByDesc('updated_at');

        if ($category) {
            $query->where('categorie_id', $category->id);
        }

        return $query->limit($limit)->get()->map(fn (Produit $p) => [
            'nom' => $p->nom,
            'url' => $this->publicUrl("/produits/{$p->slug}"),
            'price' => number_format((float) ($p->prix_promo ?: $p->prix), 0, ',', ' ') . ' XOF',
        ])->all();
    }

    private function seoForPath(string $path): array
    {
        $defaults = $this->defaultSeo();

        if (preg_match('#^(?:produits|products)/([^/]+)$#', $path, $matches)) {
            $produit = Produit::with(['category', 'images_produits'])
                ->where('slug', $matches[1])
                ->where('est_visible', true)
                ->whereHas('category', fn ($q) => $q->where('est_active', true))
                ->first();

            if ($produit) {
                $image = $this->productImage($produit);

                $prix = $produit->prix_promo ?: $produit->prix;

                return array_merge($defaults, [
                    'title' => $produit->meta_titre ?: "{$produit->nom} | {$this->shopName()}",
                    'description' => $this->limitDescription($produit->meta_description ?: $produit->description_courte ?: $produit->description),
                    'keywords' => $this->keywordsFromProduct($produit),
                    'canonical' => $this->publicUrl("/produits/{$produit->slug}"),
                    'image' => $image,
                    'type' => 'product',
                    'schema' => $this->productSchema($produit, $image),
                    'heading' => $produit->nom,
                    'price' => number_format((float) $prix, 0, ',', ' ') . ' XOF',
                    'category_name' => $produit->category?->nom,
                    'body' => $this->limitDescription($produit->description ?: $produit->description_courte) ?: null,
                    'in_stock' => ($produit->stock_disponible > 0 || !$produit->gestion_stock),
                ]);
            }
        }

        if (preg_match('#^categories/([^/]+)$#', $path, $matches)) {
            $category = Category::where('slug', $matches[1])
                ->where('est_active', true)
                ->first();

            if ($category) {
                return array_merge($defaults, [
                    'title' => "{$category->nom} | {$this->shopName()}",
                    'description' => $this->limitDescription($category->description ?: "Decouvrez notre selection {$category->nom} chez {$this->shopName()}, livraison partout au Senegal."),
                    'keywords' => "{$category->nom}, {$this->shopName()}, boutique en ligne Senegal, Dakar, achat en ligne",
                    'canonical' => $this->publicUrl("/categories/{$category->slug}"),
                    'image' => $this->absoluteAsset($category->image) ?: $defaults['image'],
                    'heading' => $category->nom,
                    'body' => $this->limitDescription($category->description) ?: null,
                    'products' => $this->productCards(48, $category),
                ]);
            }
        }

        $pageSeo = [
            'categories' => [
                'title' => 'Categories | ' . $this->shopName(),
                'description' => 'Explorez tout le catalogue ' . $this->shopName() . ' : montres, chaussures, sacs, accessoires et plus. Commandez en ligne, livraison au Senegal.',
                'canonical' => $this->publicUrl('/categories'),
            ],
        ];

        $seo = array_merge($defaults, $pageSeo[$path] ?? []);

        // Accueil et page catégories : lister des produits (contenu + liens internes).
        if ($path === '' || $path === 'categories') {
            $seo['products'] = $this->productCards(48);
        }

        return $seo;
    }

    private function defaultSeo(): array
    {
        $description = $this->shopDescription();
        $shopName = $this->shopName();

        return [
            'title' => $shopName . ' | Boutique en ligne au Senegal',
            'description' => $description,
            'keywords' => $shopName . ', boutique en ligne Senegal, achat en ligne Dakar, livraison Senegal, paiement securise',
            'canonical' => $this->publicUrl('/'),
            'image' => asset('assets/images/ndeya.jpg'),
            'type' => 'website',
            'schema' => $this->organizationSchema(),
            'heading' => $shopName,
            'body' => $description,
        ];
    }

    private function urlEntry(string $loc, $lastmod, string $changefreq, string $priority): array
    {
        return [
            'loc' => $loc,
            'lastmod' => optional($lastmod)->toAtomString() ?: now()->toAtomString(),
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];
    }

    private function renderSitemapXml($urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= "    <url>\n";
            $xml .= '        <loc>' . e($url['loc']) . "</loc>\n";
            $xml .= '        <lastmod>' . e($url['lastmod']) . "</lastmod>\n";
            $xml .= '        <changefreq>' . e($url['changefreq']) . "</changefreq>\n";
            $xml .= '        <priority>' . e($url['priority']) . "</priority>\n";
            $xml .= "    </url>\n";
        }

        $xml .= "</urlset>\n";

        return $xml;
    }

    private function limitDescription(?string $value): string
    {
        return Str::limit(trim(strip_tags((string) $value)), 155, '');
    }

    private function keywordsFromProduct(Produit $produit): string
    {
        $tags = $produit->tags;

        if (is_string($tags)) {
            $decoded = json_decode($tags, true);
            $tags = is_array($decoded) ? $decoded : [$tags];
        }

        return collect([
            $produit->nom,
            $produit->category?->nom,
            $this->shopName(),
            'mode Senegal',
            'boutique en ligne Senegal',
            'Dakar',
        ])->merge(is_array($tags) ? $tags : [])
            ->filter()
            ->unique()
            ->implode(', ');
    }

    private function absoluteAsset(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset(ltrim($path, '/'));
    }

    private function productImage(Produit $produit): ?string
    {
        $image = $produit->images_produits
            ->where('est_visible', true)
            ->sortByDesc('est_principale')
            ->first()?->url;

        return $image ?: $this->absoluteAsset($produit->image_principale);
    }

    private function publicUrl(string $path = '/'): string
    {
        $base = rtrim((string) env('FRONTEND_URL', config('app.url')), '/');
        if ($base === '') {
            $base = rtrim((string) config('app.url'), '/');
        }

        $path = '/' . ltrim($path, '/');
        return $path === '/' ? $base : $base . $path;
    }

    private function shopName(): string
    {
        return (string) ShopSetting::getValue('boutique_nom', config('app.name', 'NDEYA SHOP'));
    }

    private function shopDescription(): string
    {
        return (string) ShopSetting::getValue(
            'seo_description',
            ShopSetting::getValue(
                'boutique_description',
                'Boutique en ligne au Senegal : montres, chaussures, sacs, accessoires et produits tendance. Commandez facilement avec livraison a Dakar et partout au Senegal.'
            )
        );
    }

    private function organizationSchema(): array
    {
        $sameAs = array_values(array_filter([
            ShopSetting::getValue('social_instagram', config('app.instagram_url')),
            ShopSetting::getValue('social_tiktok', config('app.tiktok_url')),
            ShopSetting::getValue('social_facebook', ''),
        ]));

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Store',
            'name' => $this->shopName(),
            'url' => $this->publicUrl('/'),
            'logo' => asset('favicon.ico'),
            'description' => $this->shopDescription(),
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => ShopSetting::getValue('boutique_ville', 'Dakar'),
                'addressCountry' => ShopSetting::getValue('boutique_pays', 'SN'),
            ],
            'sameAs' => $sameAs,
        ];
    }

    private function productSchema(Produit $produit, ?string $image): array
    {
        $price = $produit->prix_promo ?: $produit->prix;

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $produit->nom,
            'description' => $this->limitDescription($produit->description_courte ?: $produit->description),
            'image' => array_values(array_filter([$image])),
            'sku' => 'ND-' . $produit->id,
            'category' => $produit->category?->nom,
            'brand' => [
                '@type' => 'Brand',
                'name' => $this->shopName(),
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => $this->publicUrl("/produits/{$produit->slug}"),
                'priceCurrency' => 'XOF',
                'price' => (string) round((float) $price),
                'itemCondition' => 'https://schema.org/NewCondition',
                'availability' => $produit->stock_disponible > 0 || !$produit->gestion_stock
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
            ],
        ] + ($produit->note_moyenne > 0 && $produit->nombre_avis > 0 ? [
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => round((float) $produit->note_moyenne, 1),
                'reviewCount' => (int) $produit->nombre_avis,
            ],
        ] : []) + (($reviews = $this->productReviews($produit)) ? [
            'review' => $reviews,
        ] : []);
    }

    /**
     * Avis réels (approuvés et visibles) au format schema.org/Review.
     * Aucune donnée fabriquée : retourne un tableau vide si le produit n'a pas d'avis.
     */
    private function productReviews(Produit $produit): array
    {
        return AvisClient::where('produit_id', $produit->id)
            ->where('statut', 'approuve')
            ->where('est_visible', true)
            ->where('note_globale', '>', 0)
            ->with('client:id,prenom')
            ->orderByDesc('est_mis_en_avant')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (AvisClient $avis) => array_filter([
                '@type' => 'Review',
                'reviewRating' => [
                    '@type' => 'Rating',
                    'ratingValue' => round((float) $avis->note_globale, 1),
                    'bestRating' => 5,
                    'worstRating' => 1,
                ],
                'author' => [
                    '@type' => 'Person',
                    'name' => $avis->nom_affiche ?: ($avis->client?->prenom ?: 'Client'),
                ],
                'datePublished' => $avis->created_at?->toDateString(),
                'name' => $avis->titre ?: null,
                'reviewBody' => $avis->commentaire ?: null,
            ], fn ($value) => $value !== null))
            ->all();
    }
}
