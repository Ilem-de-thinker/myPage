<?php

namespace App\Domains\Directory\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Serves robots.txt rather than shipping it in public/.
 *
 * Two reasons. The sitemap line has to be an absolute URL with the real host,
 * which a static file cannot know and which goes stale the moment the domain
 * changes. And the disallow list is derived from real routes rather than
 * remembered by hand, so a new private section cannot be forgotten here.
 *
 * None of this is access control. Every path below is already behind auth or a
 * tenant check; this is a request not to waste crawl budget on them.
 */
class RobotsTxtController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [
            'User-agent: *',
            '',
            // Seller pages are the product and should be crawled hard.
            'Allow: /',
        ];

        foreach ($this->disallowed() as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        $lines[] = '';

        $sitemap = $this->sitemapUrl();

        if ($sitemap !== null) {
            $lines[] = 'Sitemap: '.$sitemap;
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ])
            // Crawlers cache this hard. Caching it ourselves means a newly
            // published page can wait a day to be crawled for no reason.
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * @return array<int, string>
     */
    private function disallowed(): array
    {
        return [
            '/admin',
            '/dashboard',
            '/payouts',
            '/login',
            '/register',
            '/forgot-password',
            '/reset-password',
            '/verify-email',
            '/checkout',
            '/cart',
            '/webhooks',
        ];
    }

    /**
     * Only advertised once it has actually been generated. Pointing a crawler at
     * a sitemap that does not exist is a 404 on every crawl.
     */
    private function sitemapUrl(): ?string
    {
        $path = public_path('sitemaps/sitemap.xml');

        if (! is_file($path)) {
            return null;
        }

        return rtrim((string) config('app.url'), '/').'/sitemaps/sitemap.xml';
    }
}
