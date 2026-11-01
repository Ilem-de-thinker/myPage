<?php

namespace App\Domains\Directory\Services;

use App\Domains\Booking\Models\Service;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\SocialLinks;
use Illuminate\Support\Str;

/**
 * schema.org JSON-LD for a storefront.
 *
 * Two shapes, because the two themes are genuinely different businesses and
 * describing both as a generic LocalBusiness makes a hair salon look like a
 * fabric shop in search results:
 *
 * - Store theme: an OnlineStore, with products and prices attached, so it can
 *   surface as a product listing with a real price rather than a bare link.
 * - Service theme: a HealthAndBeautyBusiness-style service menu, with the
 *   offers a booker would search for.
 *
 * Only approved, active rows are included. A discontinued service or a
 * hidden product still being advertised in search is a promise the page cannot
 * keep.
 */
class StructuredData
{
    /**
     * @param  iterable<Product>|null  $products  what this particular view is
     *                                            showing; null means the whole
     *                                            active catalogue.
     * @return array<string, mixed>
     */
    public static function forPage(StorePage $page, ?iterable $products = null): array
    {
        return $page->theme === Theme::Store
            ? self::onlineStore($page, $products)
            : self::serviceBusiness($page);
    }

    /**
     * @return array<string, mixed>
     */
    private static function onlineStore(StorePage $page, ?iterable $visible = null): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'OnlineStore',
            'name' => $page->name,
            'url' => self::pageUrl($page),
            'description' => $page->tagline,
        ];

        if ($page->logo_path) {
            $data['logo'] = asset('storage/'.$page->logo_path);
        }

        if ($page->city || $page->state) {
            $data['address'] = array_filter([
                '@type' => 'PostalAddress',
                'addressLocality' => $page->city,
                'addressRegion' => $page->state,
                'streetAddress' => $page->address,
            ]);
        }

        if ($page->whatsapp_number) {
            $data['contactPoint'] = [[
                '@type' => 'ContactPoint',
                'contactType' => 'customer service',
                'telephone' => $page->whatsapp_number,
                'availableLanguage' => 'en',
            ]];
        }

        if ($page->verified_at) {
            // The one signal this platform actually vouches for. It is only ever
            // set by an admin after checking a CAC certificate, so it is worth
            // saying out loud rather than implying.
            $data['hasCredential'] = [[
                '@type' => 'EducationalOccupationalCredential',
                'credentialCategory' => 'verified business',
                'recognizedBy' => ['@type' => 'Organization', 'name' => 'mypage.ng'],
            ]];
        }

        // Where else this business can be found, in the seller's own words.
        //
        // Only ever built from handles we turned into URLs ourselves. A raw
        // handle would either be a broken link or an invitation to point at
        // somewhere the seller did not mean.
        $sameAs = SocialLinks::forPage($page);

        if ($sameAs !== []) {
            $data['sameAs'] = array_column($sameAs, 'url');
        }

        // Only active products. A retired product still being advertised in
        // search is a promise the page cannot keep.
        //
        // A caller that already knows which products the page is showing wins:
        // on a filtered or paginated listing, describing the full catalogue
        // would advertise items the visitor is not being shown.
        $products = $visible !== null
            ? collect($visible)->take(20)
            : Product::withoutGlobalScopes()
                ->where('store_page_id', $page->getKey())
                ->where('status', 'active')
                ->orderByDesc('is_featured')
                ->orderBy('name')
                ->limit(20)
                ->get();

        if ($products->isNotEmpty()) {
            $data['makesOffer'] = $products->map(fn (Product $product) => array_filter([
                '@type' => 'Offer',
                'name' => $product->name,
                'price' => self::priceString($product->price_kobo),
                'priceCurrency' => 'NGN',
                'availability' => self::availability($product),
                'priceValidUntil' => $product->saleEndsAt()?->toDateString(),
                'url' => self::pageUrl($page, 'products/'.$product->slug),
            ]))->all();
        }

        return self::withFaq($page, $data);
    }

    /**
     * @return array<string, mixed>
     */
    private static function serviceBusiness(StorePage $page): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'HealthAndBeautyBusiness',
            'name' => $page->name,
            'url' => self::pageUrl($page),
            'description' => $page->tagline,
        ];

        if ($page->city || $page->state) {
            $data['address'] = array_filter([
                '@type' => 'PostalAddress',
                'addressLocality' => $page->city,
                'addressRegion' => $page->state,
            ]);
        }

        $sameAs = SocialLinks::forPage($page);

        if ($sameAs !== []) {
            $data['sameAs'] = array_column($sameAs, 'url');
        }

        $services = Service::withoutGlobalScopes()
            ->where('store_page_id', $page->getKey())
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->limit(20)
            ->get();

        if ($services->isNotEmpty()) {
            $data['hasOfferCatalog'] = [
                '@type' => 'OfferCatalog',
                'name' => 'Services',
                'itemListElement' => $services->map(fn (Service $service) => array_filter([
                    '@type' => 'Offer',
                    'name' => $service->name,
                    'description' => Str::limit(strip_tags((string) $service->description), 200),
                    'priceCurrency' => 'NGN',
                    'price' => self::priceString($service->effectivePriceKobo()),
                    'priceValidUntil' => $service->saleEndsAt()?->toDateString(),
                    'url' => self::pageUrl($page, 'services/'.$service->slug),
                ]))->all(),
            ];
        }

        if ($page->verified_at) {
            $data['hasCredential'] = [[
                '@type' => 'EducationalOccupationalCredential',
                'credentialCategory' => 'verified business',
            ]];
        }

        return self::withFaq($page, $data);
    }

    /**
     * Add only complete FAQ rows. A partial draft must not become misleading
     * search metadata while the seller is still editing the block.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function withFaq(StorePage $page, array $data): array
    {
        $block = $page->blocks()
            ->where('page', 'home')
            ->where('key', 'faq')
            ->first();

        $items = collect($block?->field('items') ?? [])
            ->filter(fn ($item): bool => is_array($item) && filled($item['question'] ?? null) && filled($item['answer'] ?? null))
            ->map(fn (array $item): array => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => strip_tags((string) $item['answer']),
                ],
            ])
            ->values()
            ->all();

        if ($items !== []) {
            $data['subjectOf'] = [
                '@type' => 'FAQPage',
                'mainEntity' => $items,
            ];
        }

        return $data;
    }

    /**
     * Only tracked stock can run out. Saying OutOfStock for a product nobody
     * counts stock on is a lie that makes a working shop look closed.
     */
    private static function availability(Product $product): string
    {
        if (! $product->track_stock) {
            return 'https://schema.org/InStock';
        }

        return $product->stock_qty > 0
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';
    }

    /**
     * Money is integer kobo everywhere else; JSON-LD wants a decimal string.
     * Divided here, once, rather than at the call sites.
     */
    private static function priceString(int $kobo): string
    {
        return number_format($kobo / 100, 2, '.', '');
    }

    private static function pageUrl(StorePage $page, string $path = ''): string
    {
        return rtrim((string) config('app.url'), '/')
            .'/'.$page->slug
            .($path === '' ? '' : '/'.$path);
    }
}
