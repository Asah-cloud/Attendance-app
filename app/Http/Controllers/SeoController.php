<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SeoController extends Controller
{
    /**
     * Everything is crawlable — pages that shouldn't be indexed carry their
     * own <meta name="robots" content="noindex"> tag instead of being
     * blocked here. Blocking crawl AND relying on a noindex tag on the same
     * URL is unreliable: a crawler that can't fetch a page can't see the
     * tag telling it to leave the page out of the index.
     */
    public function robots(): Response
    {
        $body = "User-agent: *\nAllow: /\n\nSitemap: ".url('/sitemap.xml')."\n";

        return response($body)->header('Content-Type', 'text/plain');
    }

    /**
     * Only the two pages actually meant to be indexed. Anything
     * tenant/event/company-specific is intentionally never listed here.
     */
    public function sitemap(): Response
    {
        $pages = [
            ['loc' => url('/'), 'changefreq' => 'monthly', 'priority' => '1.0', 'view' => 'landing'],
            ['loc' => url('/pricing'), 'changefreq' => 'monthly', 'priority' => '0.8', 'view' => 'pricing'],
        ];

        foreach ($pages as &$page) {
            $path = resource_path("views/{$page['view']}.blade.php");
            $page['lastmod'] = file_exists($path) ? date('Y-m-d', filemtime($path)) : now()->toDateString();
        }

        $xml = view('sitemap', ['pages' => $pages])->render();

        return response($xml)->header('Content-Type', 'application/xml');
    }
}
