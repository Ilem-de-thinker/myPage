<?php

use App\Domains\Booking\Models\Service;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\SocialLinks;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function detailsPage(): array
{
    $seller = Seller::factory()->create();
    $page = StorePage::factory()->for($seller)->create([
        'theme' => 'service',
        'status' => 'published',
        'slug' => 'details-shop',
        'settings' => ['timezone' => 'Africa/Lagos'],
    ]);

    return [$seller, $page];
}

describe('social handles', function () {
    it('turns a bare handle into a profile link', function () {
        expect(SocialLinks::normalise(['instagram' => 'adatextiles']))
            ->toBe(['instagram' => 'https://www.instagram.com/adatextiles']);
    });

    it('accepts a handle with an @ on the front', function () {
        expect(SocialLinks::normalise(['tiktok' => '@kemibraids']))
            ->toBe(['tiktok' => 'https://www.tiktok.com/@kemibraids']);
    });

    it('takes the handle out of a pasted profile URL', function () {
        expect(SocialLinks::normalise(['instagram' => 'https://www.instagram.com/adatextiles']))
            ->toBe(['instagram' => 'https://www.instagram.com/adatextiles']);
    });

    it('turns a youtube channel handle into a channel URL', function () {
        expect(SocialLinks::normalise(['youtube' => '@kemibraids']))
            ->toBe(['youtube' => 'https://www.youtube.com/@kemibraids']);
    });

    it('refuses a pasted URL that points at a different host', function () {
        // The one that matters: a link we build must never point anywhere but
        // the platform the seller named, however they typed it.
        expect(SocialLinks::normalise(['instagram' => 'https://evil.example.com/adatextiles']))
            ->toBe([]);
    });

    it('refuses a javascript URL and anything that is not a handle', function () {
        expect(SocialLinks::normalise([
            'instagram' => 'javascript:alert(1)',
            'facebook' => 'not a handle at all',
            'tiktok' => 'ok.handle',
        ]))->toBe(['tiktok' => 'https://www.tiktok.com/@ok.handle']);
    });

    it('refuses a platform it does not know', function () {
        expect(SocialLinks::normalise(['myspace' => 'adatextiles']))->toBe([]);
    });

    it('drops an empty handle rather than linking nowhere', function () {
        expect(SocialLinks::normalise(['instagram' => '   ']))->toBe([]);
    });

    it('renders in a fixed platform order regardless of input order', function () {
        $page = StorePage::factory()->create(['social_links' => [
            'tiktok' => 'https://www.tiktok.com/@b',
            'instagram' => 'https://www.instagram.com/a',
        ]]);

        expect(array_column(SocialLinks::forPage($page), 'platform'))
            ->toBe(['instagram', 'tiktok']);
    });
});

describe('business details settings', function () {
    it('saves the details the seller enters', function () {
        [$seller, $page] = detailsPage();

        actingAs($seller)->post('/storefront/details', [
            'tagline' => 'Cut and stitched to measure',
            'public_email' => 'ada@example.com',
            'whatsapp_number' => '0803 123 4567',
            'address' => '22b Oduduwa Crescent, Lekki',
            'city' => 'Lekki',
            'state' => 'Lagos',
            'social' => ['instagram' => 'adatextiles'],
        ])->assertSessionDoesntHaveErrors();

        $page->refresh();

        expect($page->tagline)->toBe('Cut and stitched to measure')
            ->and($page->public_email)->toBe('ada@example.com')
            ->and($page->whatsapp_number)->toBe('+2348031234567')
            ->and($page->address)->toBe('22b Oduduwa Crescent, Lekki')
            ->and($page->city)->toBe('Lekki')
            ->and($page->social_links)->toBe(['instagram' => 'https://www.instagram.com/adatextiles']);
    });

    it('keeps the social links when only the tagline is saved', function () {
        [$seller, $page] = detailsPage();

        actingAs($seller)->post('/storefront/details', [
            'tagline' => 'New tagline',
            'public_email' => null,
            'whatsapp_number' => '0803 123 4567',
            'address' => null,
            'city' => 'Lagos',
            'state' => 'Lagos',
            'social' => ['instagram' => 'handle'],
        ]);

        expect($page->refresh()->social_links)
            ->toBe(['instagram' => 'https://www.instagram.com/handle']);
    });

    it('shows the details tab with the values the seller already has', function () {
        [$seller, $page] = detailsPage();

        $page->update([
            'tagline' => 'Saved tagline',
            'public_email' => 'saved@example.com',
            'social_links' => ['instagram' => 'https://www.instagram.com/savedhandle'],
        ]);

        actingAs($seller)->get('/storefront?tab=details')
            ->assertOk()
            ->assertSee('Business details')
            ->assertSee('Social media')
            ->assertSee('saved@example.com');
    });

    it('rejects a WhatsApp number that is not Nigerian', function () {
        [$seller, $page] = detailsPage();

        actingAs($seller)->post('/storefront/details', [
            'tagline' => null,
            'public_email' => null,
            'whatsapp_number' => '+1 555 0100',
            'address' => null,
            'city' => 'Lagos',
            'state' => 'Lagos',
            'social' => [],
        ])->assertSessionHasErrors('whatsapp_number');

        expect($page->fresh()->whatsapp_number)->toBe('+2348030000000');
    });

    it('rejects an email that is not an email', function () {
        [$seller, $page] = detailsPage();

        actingAs($seller)->post('/storefront/details', [
            'public_email' => 'not-an-email',
            'whatsapp_number' => '0803 123 4567',
            'city' => 'Lagos',
            'state' => 'Lagos',
        ])->assertSessionHasErrors('public_email');
    });
});

describe('business details on the public page', function () {
    it('shows the address, email and social links in the footer', function () {
        [$seller, $page] = detailsPage();

        $page->update([
            'address' => '14B Adeniran Ogunsanya Street, Surulere',
            'city' => 'Surulere',
            'state' => 'Lagos',
            'public_email' => 'kemi@example.com',
            'social_links' => ['instagram' => 'https://www.instagram.com/kemibraids'],
        ]);

        get("/{$page->slug}")
            ->assertOk()
            ->assertSee('14B Adeniran Ogunsanya Street, Surulere')
            ->assertSee('kemi@example.com')
            ->assertSee('https://www.instagram.com/kemibraids');
    });

    it('hides the social links the seller has not set', function () {
        [$seller, $page] = detailsPage();

        $page->update(['social_links' => ['instagram' => 'https://www.instagram.com/kemibraids']]);

        $html = get("/{$page->slug}")->assertOk()->getContent();

        expect($html)->toContain('https://www.instagram.com/kemibraids')
            ->not->toContain('https://www.tiktok.com/');
    });

    it('shows the details on the contact page with nothing written there', function () {
        [$seller, $page] = detailsPage();

        $page->update([
            'address' => '14B Adeniran Ogunsanya Street, Surulere',
            'city' => 'Surulere',
            'state' => 'Lagos',
            'public_email' => 'kemi@example.com',
        ]);

        get("/{$page->slug}/contact")
            ->assertOk()
            ->assertSee('14B Adeniran Ogunsanya Street, Surulere')
            ->assertSee('kemi@example.com')
            ->assertSee('WhatsApp');
    });

    it('puts the social links in the structured data for a store', function () {
        [$seller] = detailsPage();
        $page = $seller->storePages->first();

        $page->update([
            'theme' => 'store',
            'status' => 'published',
            'social_links' => ['instagram' => 'https://www.instagram.com/kemibraids'],
        ]);

        $html = get("/{$page->slug}")->assertOk()->getContent();

        expect($html)->toContain('"sameAs":["https://www.instagram.com/kemibraids"]');
    });

    it('puts the social links in the structured data for a service business', function () {
        [$seller, $page] = detailsPage();

        $page->update([
            'social_links' => ['instagram' => 'https://www.instagram.com/kemibraids'],
        ]);

        $html = get("/{$page->slug}")->assertOk()->getContent();

        expect($html)->toContain('"sameAs":["https://www.instagram.com/kemibraids"]');
    });

    it('omits sameAs when the seller has no social links', function () {
        [$seller, $page] = detailsPage();

        $html = get("/{$page->slug}")->assertOk()->getContent();

        expect($html)->not->toContain('"sameAs"');
    });

    it('does not show the details tab for a seller who cannot be found', function () {
        $seller = Seller::factory()->create();

        // A seller with no page yet is redirected to onboarding rather than
        // shown an empty editor.
        actingAs($seller)->get('/storefront')->assertRedirect();
    });
});

describe('service detail page', function () {
    it('renders a full detail page with nothing written for it', function () {
        [$seller, $page] = detailsPage();

        $service = Service::factory()->create([
            'store_page_id' => $page->id,
            'seller_id' => $seller->id,
            'name' => 'Braid consultation',
            'slug' => 'braid-consultation',
            'is_active' => true,
            'price_kobo' => 500000,
        ]);

        $page->blocks()->where('page', 'service-show')->delete();

        get("/{$page->slug}/services/braid-consultation")
            ->assertOk()
            ->assertSee('Braid consultation')
            ->assertSee('Reserve a slot')
            ->assertSee('Duration');
    });

    it('renders the listing with nothing written for it', function () {
        [$seller, $page] = detailsPage();

        $service = Service::factory()->create([
            'store_page_id' => $page->id,
            'seller_id' => $seller->id,
            'name' => 'Braid consultation',
            'slug' => 'braid-consultation',
            'is_active' => true,
        ]);

        $page->blocks()->where('page', 'services')->delete();

        get("/{$page->slug}/services")->assertOk()->assertSee('Braid consultation');
    });
});
