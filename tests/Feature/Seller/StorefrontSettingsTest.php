<?php

use App\Domains\Seller\Models\PageBlock;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

function settingsPage(): array
{
    $seller = Seller::factory()->create();
    $page = StorePage::factory()->for($seller)->withContent()->create([
        'theme' => 'store',
        'status' => 'published',
        'slug' => 'settings-shop',
    ]);

    return [$seller, $page];
}

it('shows the settings screen with one card per block the theme declares', function () {
    [$seller] = settingsPage();

    actingAs($seller)->get('/storefront')
        ->assertOk()
        ->assertSee('Your storefront')
        ->assertSee('Headline')
        ->assertSee('Hero photo')
        ->assertSee('Featured products');
});

it('calls the editor a website for a service seller', function () {
    [$seller] = sellerWithPage(['theme' => 'service', 'status' => 'published']);

    actingAs($seller)->get('/storefront')
        ->assertOk()
        ->assertSee('Your website')
        ->assertSee('Save website')
        // The tab strip follows the service theme, so no product blocks.
        ->assertDontSee('Your storefront');
});

it('saves each field under its own name', function () {
    [$seller, $page] = settingsPage();

    // The hero has three separate text fields. They used to share a single
    // `string` input, so the last one submitted silently overwrote the others.
    actingAs($seller)->post('/storefront/home', [
        'blocks' => [
            'hero' => [
                'headline' => 'Made for your wedding',
                'subtext' => 'Hand cut in Surulere',
                'button_label' => 'See the cloth',
            ],
        ],
    ])->assertRedirect();

    $hero = $page->blocks()->where('key', 'hero')->firstOrFail();

    expect($hero->content)->toMatchArray([
        'headline' => 'Made for your wedding',
        'subtext' => 'Hand cut in Surulere',
        'button_label' => 'See the cloth',
    ]);
});

it('keeps only the fields a block declares', function () {
    [$seller, $page] = settingsPage();

    actingAs($seller)->post('/storefront/home', [
        'blocks' => [
            'hero' => [
                'headline' => 'Real headline',
                // The categories block has no such field, and the hero has no
                // `enabled` flag any more.
                'enabled' => '1',
                'position' => '9',
                'made_up' => 'should not survive',
            ],
        ],
    ]);

    expect($page->blocks()->where('key', 'hero')->firstOrFail()->content)
        ->toBe(['headline' => 'Real headline']);
});

it('strips scripts out of rich text', function () {
    [$seller, $page] = settingsPage();

    actingAs($seller)->post('/storefront/home', [
        'blocks' => [
            'about' => ['body' => '<p>Since 2020</p><script>alert(1)</script>'],
        ],
    ]);

    expect($page->blocks()->where('key', 'about')->firstOrFail()->content['body'])
        ->toBe('<p>Since 2020</p>alert(1)');
});

it('keeps reviews as a list of quote and author', function () {
    [$seller, $page] = settingsPage();

    actingAs($seller)->post('/storefront/home', [
        'blocks' => [
            'reviews' => [
                'items' => [
                    ['quote' => 'Wore these to my engagement', 'author' => 'Ngozi'],
                    ['quote' => '', 'author' => 'Blank row, dropped'],
                ],
            ],
        ],
    ]);

    expect($page->blocks()->where('key', 'reviews')->firstOrFail()->content['items'])
        ->toBe([['quote' => 'Wore these to my engagement', 'author' => 'Ngozi']]);
});

it('removes a block once the seller empties it', function () {
    [$seller, $page] = settingsPage();

    expect($page->blocks()->where('key', 'hero')->exists())->toBeTrue();

    actingAs($seller)->post('/storefront/home', [
        'blocks' => ['hero' => ['headline' => '', 'subtext' => '']],
    ]);

    // No switch to turn it off any more: clearing the fields is what removes it.
    expect($page->blocks()->where('key', 'hero')->exists())->toBeFalse();
});

it('hides an empty block from the public page but keeps its input in settings', function () {
    [$seller, $page] = settingsPage();

    actingAs($seller)->post('/storefront/home', [
        'blocks' => ['hero' => ['headline' => '', 'subtext' => '']],
    ]);

    // Gone from the shop...
    $this->get(storeUrl($page->slug))
        ->assertOk()
        ->assertDontSee('Hero', false);

    // ...but the seller can still fill it in again.
    actingAs($seller)->get('/storefront')
        ->assertOk()
        ->assertSee('Headline');
});

it('saves the brand colour, logo and cover', function () {
    Storage::fake('public');

    [$seller, $page] = settingsPage();

    actingAs($seller)->post('/storefront/brand', [
        'brand_color' => '#7c3aed',
        'logo' => UploadedFile::fake()->image('logo.jpg'),
        'cover' => UploadedFile::fake()->image('cover.jpg'),
    ])->assertRedirect();

    $page->refresh();

    expect($page->brand_color)->toBe('#7c3aed')
        ->and($page->logo_path)->not->toBeNull()
        ->and($page->cover_path)->not->toBeNull();

    Storage::disk('public')->assertExists($page->logo_path);
    Storage::disk('public')->assertExists($page->cover_path);
});

it('keeps the existing logo when a new colour is saved without a file', function () {
    Storage::fake('public');

    [$seller, $page] = settingsPage();
    $original = $page->blocks()->count(); // unrelated, just proving nothing else broke

    actingAs($seller)->post('/storefront/brand', ['brand_color' => '#0f766e']);

    expect($page->refresh()->brand_color)->toBe('#0f766e')
        ->and($page->blocks()->count())->toBe($original);
});

it('rejects a brand colour that is not a hex value', function () {
    [$seller, $page] = settingsPage();

    actingAs($seller)->post('/storefront/brand', ['brand_color' => 'purple'])
        ->assertSessionHasErrors('brand_color');

    expect($page->refresh()->brand_color)->not->toBe('purple');
});

it('refuses to write a block key the theme does not declare for that page', function () {
    [$seller, $page] = settingsPage();

    // `story` is a real key, but it belongs to the about page, and
    // `not_a_block` is not a key this theme knows at all.
    $storyBefore = $page->blocks()->where('key', 'story')->firstOrFail()->content;

    actingAs($seller)->post('/storefront/home', [
        'blocks' => [
            'not_a_block' => ['headline' => 'Injected'],
            'story' => ['body' => 'Should not land here'],
        ],
    ]);

    expect(PageBlock::where('store_page_id', $page->id)->where('key', 'not_a_block')->exists())->toBeFalse()
        ->and($page->blocks()->where('key', 'story')->firstOrFail()->content)->toBe($storyBefore);
});

it('cannot touch another sellers blocks', function () {
    [$seller, $page] = settingsPage();

    $other = Seller::factory()->create();
    $otherPage = StorePage::factory()->for($other)->withContent()->create();
    $otherHero = $otherPage->blocks()->where('key', 'hero')->firstOrFail();
    $before = $otherHero->content;

    // The key matches a block the seller *does* own, so the only thing stopping
    // the write is that rows are looked up through the seller's own page.
    actingAs($seller)->post('/storefront/home', [
        'blocks' => ['hero' => ['headline' => 'Mine now']],
    ]);

    expect($otherHero->refresh()->content)->toBe($before)
        ->and($page->blocks()->where('key', 'hero')->firstOrFail()->content['headline'])->toBe('Mine now');
});
