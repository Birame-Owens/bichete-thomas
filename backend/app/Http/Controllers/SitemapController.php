<?php

namespace App\Http\Controllers;

use App\Models\CategorieCoiffure;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $categories = CategorieCoiffure::query()
            ->where('actif', true)
            ->orderBy('id')
            ->get(['id', 'updated_at']);

        $baseUrl = rtrim(config('app.frontend_url', 'https://bichettethomas.site'), '/');

        $urls = [];

        $urls[] = [
            'loc' => $baseUrl . '/',
            'changefreq' => 'weekly',
            'priority' => '1.0',
            'lastmod' => now()->toDateString(),
        ];

        $urls[] = [
            'loc' => $baseUrl . '/categories',
            'changefreq' => 'weekly',
            'priority' => '0.8',
            'lastmod' => $categories->max('updated_at')?->toDateString() ?? now()->toDateString(),
        ];

        foreach ($categories as $category) {
            $urls[] = [
                'loc' => $baseUrl . '/categories/' . $category->id,
                'changefreq' => 'weekly',
                'priority' => '0.7',
                'lastmod' => $category->updated_at?->toDateString() ?? now()->toDateString(),
            ];
        }

        $xml = $this->buildXml($urls);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * @param array<int, array<string, string>> $urls
     */
    private function buildXml(array $urls): string
    {
        $items = '';
        foreach ($urls as $url) {
            $items .= sprintf(
                "\n    <url>\n        <loc>%s</loc>\n        <lastmod>%s</lastmod>\n        <changefreq>%s</changefreq>\n        <priority>%s</priority>\n    </url>",
                htmlspecialchars($url['loc'], ENT_XML1),
                $url['lastmod'],
                $url['changefreq'],
                $url['priority'],
            );
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            . $items . "\n"
            . '</urlset>';
    }
}
