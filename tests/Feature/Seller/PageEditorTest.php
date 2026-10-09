<?php

use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use App\Domains\Seller\Models\StoreSection;

use function Pest\Laravel\actingAs;

function editorPage(): array
{
    $seller = Seller::factory()->create();
    $page = StorePage::factory()->for($seller)->create(['theme' => 'store', 'status' => 'published']);
    $hero = StoreSection::factory()->create(['store_page_id' => $page->id, 'page' => 'home', 'key' => 'hero', 'position' => 0, 'is_enabled' => true]);
    $about = StoreSection::factory()->create(['store_page_id' => $page->id, 'page' => 'about', 'key' => 'story', 'position' => 0, 'is_enabled' => true]);

    return [$seller, $page, $hero, $about];
}

it('shows the page editor grouped by page', function () {
    [$seller] = editorPage();

    actingAs($seller)->get('/page')
        ->assertOk()
        ->assertSee('Hero')
        ->assertSee('Story');
});

it('updates section content and disables sections', function () {
    [$seller, $page, $hero, $about] = editorPage();

    actingAs($seller)->post('/page', [
        'sections' => [
            ['id' => $hero->id, 'enabled' => '1', 'heading' => 'Welcome', 'body' => '<p>Hello</p><script>alert(1)</script>'],
            ['id' => $about->id, 'heading' => 'Our story', 'body' => '<p>Since 2020</p>'],
        ],
    ]);

    $hero->refresh();
    $about->refresh();

    expect($hero->is_enabled)->toBeTrue()
        ->and($hero->content['heading'])->toBe('Welcome')
        ->and($hero->content['body'])->toBe('<p>Hello</p>alert(1)')
        ->and($about->is_enabled)->toBeFalse()
        ->and($about->content['heading'])->toBe('Our story');
});

it('records positions per page from the submitted order', function () {
    [$seller, $page, $hero, $about] = editorPage();

    actingAs($seller)->post('/page', [
        'sections' => [
            ['id' => $about->id, 'enabled' => '1', 'heading' => 'A', 'body' => ''],
            ['id' => $hero->id, 'enabled' => '1', 'heading' => 'B', 'body' => ''],
        ],
    ]);

    expect($about->fresh()->position)->toBe(0)
        ->and($hero->fresh()->position)->toBe(0);
});

it('ignores sections that do not belong to the seller page', function () {
    [$seller, $page, $hero, $about] = editorPage();
    $other = StoreSection::factory()->create(['page' => 'home', 'key' => 'hero', 'position' => 0]);

    actingAs($seller)->post('/page', [
        'sections' => [
            ['id' => $other->id, 'enabled' => '1', 'heading' => 'Hacked', 'body' => ''],
        ],
    ]);

    expect($other->fresh()->content)->toBeNull();
});
