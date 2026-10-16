<?php

namespace App\Console\Commands;

use App\Domains\Seller\Enums\StoreStatus;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Writes sitemap files covering every published page.
 *
 * Split at 50,000 URLs per file because that is the ceiling search engines
 * accept, and the sitemap protocol wants a sitemap index above multiple files.
 * One flat list is simpler right up until it silently stops being submitted.
 *
 * Only published pages appear. A draft in a sitemap is a page a stranger can
 * reach from search, and a suspended one is a business under dispute.
 */
class GenerateSitemap extends Command
{
    /** The documented per-file ceiling. */
    private const URLS_PER_FILE = 50_000;

    protected $signature = 'sitemap:generate {--output=public/sitemaps : where to write the sitemap files}';

    protected $description = 'Build sitemap.xml and its sharded children from published pages';

    public function handle(): int
    {
        $output = rtrim((string) $this->option('output'), '/');
        File::ensureDirectoryExists($output);

        $urls = $this->collectUrls();

        if ($urls === []) {
            $this->components->error('No published pages. Nothing to advertise.');

            return self::FAILURE;
        }

        $shards = array_chunk($urls, self::URLS_PER_FILE);
        $written = [];

        foreach ($shards as $index => $shard) {
            $name = count($shards) === 1 ? 'sitemap.xml' : 'sitemap-'.($index + 1).'.xml';

            $this->write($output.'/'.$name, $this->renderSet($shard));

            $written[] = $name;
        }

        // The index is what gets submitted to search engines, so it has to exist
        // whenever there is more than one shard.
        if (count($shards) > 1) {
            $this->write($output.'/sitemap.xml', $this->renderIndex($written));
            $this->components->info(sprintf(
                '%d URLs across %d shards, plus an index. Sitemap index is above 50,000 URLs.',
                count($urls),
                count($shards),
            ));
        } else {
            $this->components->info(sprintf('%d URLs in %s', count($urls), $written[0]));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{loc: string, lastmod: string, changefreq: string, priority: string}>
     */
    private function collectUrls(): array
    {
        $urls = [[
            'loc' => $this->absolute(route('home')),
            'lastmod' => now()->toAtomString(),
            // The landing page changes when we ship, so it is worth re-crawling
            // more often than a shop that has not been edited since Tuesday.
            'changefreq' => 'weekly',
            'priority' => '1.0',
        ]];

        if (config('mypage.features.directory')) {
            $urls[] = [
                'loc' => $this->absolute(route('directory.index')),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'daily',
                'priority' => '0.8',
            ];
        }

        /*
            The discovery hub.

            Behind its own flag rather than the directory's, and listed separately
            for the same reason the routes are: switching one on should not put the
            other in front of the world.

            `daily`, and above the directory. The hub is the surface that changes
            most — a seller opts in, publishes a rating, gets featured — and it is
            the one a crawler should re-read rather than serve from a month-old
            cache.
        */
        if (config('mypage.features.discovery')) {
            $urls[] = [
                'loc' => $this->absolute(route('discovery.index')),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'daily',
                'priority' => '0.9',
            ];
        }

        StorePage::withoutGlobalScopes()
            ->where('status', StoreStatus::Published)
            ->whereNotNull('published_at')
            ->orderBy('id')
            ->chunkById(500, function ($pages) use (&$urls): void {
                foreach ($pages as $page) {
                    $urls[] = [
                        'loc' => $this->absolute(route('storefront.show', ['slug' => $page->slug])),
                        'lastmod' => ($page->updated_at ?? now())->toAtomString(),
                        'changefreq' => 'weekly',
                        // A verified page has been through a human check, so it
                        // is worth more than an unclaimed one of equal recency.
                        'priority' => $page->verified_at ? '0.8' : '0.6',
                    ];
                }
            });

        return $urls;
    }

    /**
     * @param  array<int, array{loc: string, lastmod: string, changefreq: string, priority: string}>  $urls
     */
    private function renderSet(array $urls): string
    {
        $xml = $this->urlsetDeclaration();

        foreach ($urls as $url) {
            $xml .= "  <url>\n"
                .'    <loc>'.$this->escape($url['loc'])."</loc>\n"
                .'    <lastmod>'.$url['lastmod']."</lastmod>\n"
                .'    <changefreq>'.$url['changefreq']."</changefreq>\n"
                .'    <priority>'.$url['priority']."</priority>\n"
                ."  </url>\n";
        }

        return $xml."</urlset>\n";
    }

    /**
     * @param  array<int, string>  $files
     */
    private function renderIndex(array $files): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($files as $file) {
            $xml .= "  <sitemap>\n"
                .'    <loc>'.$this->escape($this->absolute('/'.$file))."</loc>\n"
                .'    <lastmod>'.now()->toAtomString()."</lastmod>\n"
                ."  </sitemap>\n";
        }

        return $xml."</sitemapindex>\n";
    }

    private function urlsetDeclaration(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
    }

    private function write(string $path, string $contents): void
    {
        File::put($path, $contents);
    }

    /**
     * Sitemap locations must be absolute and carry the real host. A relative
     * URL is not merely wrong, it gets the whole file thrown away.
     *
     * route() already returns an absolute URL, so this only has to deal with the
     * path-only strings passed in from elsewhere. Appending app.url to an
     * already-absolute URL is how you end up shipping
     * example.com/example.com/page to a crawler.
     */
    private function absolute(string $url): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
    }

    /**
     * Ampersands in a query string are the classic way to produce a sitemap that
     * fails to parse.
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
