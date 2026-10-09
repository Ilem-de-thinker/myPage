<?php

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;

use function Pest\Laravel\actingAs;

it('lists the categories page', function () {
    [$seller, $page] = sellerWithPage();

    $page->categories()->create(['seller_id' => $seller->id, 'name' => 'Fabrics']);

    actingAs($seller)->get(route('categories.index'))
        ->assertOk()
        ->assertSee('Categories')
        ->assertSee('Fabrics');
});

it('creates a category with a unique slug', function () {
    [$seller, $page] = sellerWithPage();

    actingAs($seller)->post(route('categories.store'), ['name' => 'Fabrics'])
        ->assertRedirect(route('categories.index'));

    $category = Category::sole();

    expect($category->name)->toBe('Fabrics')
        ->and($category->slug)->toStartWith('fabrics-')
        ->and($category->store_page_id)->toBe($page->id)
        ->and($category->seller_id)->toBe($seller->id);
});

it('answers the product form inline creation with json', function () {
    [$seller, $page] = sellerWithPage();

    actingAs($seller)->postJson(route('categories.store'), ['name' => 'Ready Made'])
        ->assertCreated()
        ->assertJsonStructure(['id', 'name']);

    expect(Category::sole()->name)->toBe('Ready Made');
});

it('rejects a duplicate name within the same store', function () {
    [$seller, $page] = sellerWithPage();

    $page->categories()->create(['seller_id' => $seller->id, 'name' => 'Fabrics']);

    actingAs($seller)->post(route('categories.store'), ['name' => 'Fabrics'])
        ->assertSessionHasErrors('name');
});

it('allows two sellers to use the same category name', function () {
    [$seller, $page] = sellerWithPage();

    $page->categories()->create(['seller_id' => $seller->id, 'name' => 'Fabrics']);

    [$otherSeller, $otherPage] = sellerWithPage();

    actingAs($otherSeller)->post(route('categories.store'), ['name' => 'Fabrics'])
        ->assertRedirect(route('categories.index'))
        ->assertSessionHasNoErrors();

    expect(Category::withoutGlobalScopes()->count())->toBe(2);
});

it('renames a category and keeps its slug when the name is unchanged', function () {
    [$seller, $page] = sellerWithPage();

    $category = $page->categories()->create(['seller_id' => $seller->id, 'name' => 'Fabrics']);

    actingAs($seller)->put(route('categories.update', $category), ['name' => 'Ready Made'])
        ->assertRedirect(route('categories.index'));

    expect($category->fresh()->name)->toBe('Ready Made')
        ->and($category->fresh()->slug)->toStartWith('ready-made-');

    actingAs($seller)->put(route('categories.update', $category), ['name' => 'Ready Made'])->assertRedirect();

    expect($category->fresh()->slug)->toStartWith('ready-made-');
});

it('counts the products in each category', function () {
    [$seller, $page] = sellerWithPage();

    $category = $page->categories()->create(['seller_id' => $seller->id, 'name' => 'Fabrics']);

    Product::factory()->count(3)->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
        'category_id' => $category->id,
    ]);

    $response = actingAs($seller)->get(route('categories.index'))->assertOk();

    $response->assertSee('Fabrics')->assertSee('Products');

    expect($response->viewData('categories')->first()->products_count)->toBe(3);
});

it('filters the listing by name', function () {
    [$seller, $page] = sellerWithPage();

    $page->categories()->create(['seller_id' => $seller->id, 'name' => 'Fabrics']);
    $page->categories()->create(['seller_id' => $seller->id, 'name' => 'Ready Made']);

    actingAs($seller)->get(route('categories.index', ['q' => 'Fabr']))
        ->assertOk()
        ->assertSee('Fabrics')
        ->assertDontSee('Ready Made');
});

it('deletes a category but keeps its products', function () {
    [$seller, $page] = sellerWithPage();

    $category = $page->categories()->create(['seller_id' => $seller->id, 'name' => 'Fabrics']);

    $product = Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
        'category_id' => $category->id,
    ]);

    actingAs($seller)->delete(route('categories.destroy', $category))->assertRedirect();

    expect(Category::count())->toBe(0)
        ->and($product->fresh())->not->toBeNull()
        ->and($product->fresh()->category_id)->toBeNull();
});

it('never exposes another seller\'s categories', function () {
    [$seller] = sellerWithPage();

    [$otherSeller, $otherPage] = sellerWithPage();

    $foreign = $otherPage->categories()->create([
        'seller_id' => $otherSeller->id,
        'name' => 'Secret Range',
    ]);

    actingAs($seller)->get(route('categories.index'))
        ->assertOk()
        ->assertDontSee('Secret Range');

    actingAs($seller)->get(route('categories.edit', $foreign))->assertNotFound();
    actingAs($seller)->put(route('categories.update', $foreign), ['name' => 'Hijack'])->assertNotFound();
    actingAs($seller)->delete(route('categories.destroy', $foreign))->assertNotFound();

    expect($foreign->fresh()->name)->toBe('Secret Range');
});

it('opens the create form', function () {
    [$seller] = sellerWithPage();

    actingAs($seller)->get(route('categories.create'))
        ->assertOk()
        ->assertSee('Add category');
});
