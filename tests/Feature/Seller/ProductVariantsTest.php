<?php

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

it('lists the variants page for a product', function () {
    [$seller, , $product] = sellerWithProduct();

    $product->variants()->create(['name' => 'Large', 'stock_qty' => 2]);

    actingAs($seller)->get("/products/{$product->id}/variants")
        ->assertOk()
        ->assertSee('Variants')
        ->assertSee('Large');
});

it('creates a variant on its own with an auto sku', function () {
    [$seller, , $product] = sellerWithProduct();

    actingAs($seller)->post("/products/{$product->id}/variants", [
        'name' => 'Large',
        'price' => '5500',
        'stock_qty' => 3,
    ])->assertRedirect(route('products.variants.index', $product));

    $variant = $product->fresh()->variants->sole();

    expect($variant->name)->toBe('Large');
    expect($variant->price_kobo)->toBe(550000);
    expect($variant->stock_qty)->toBe(3);
    expect($variant->sku)->toStartWith('VAR-');
});

it('leaves the variant price null so the product price applies', function () {
    [$seller, , $product] = sellerWithProduct();

    actingAs($seller)->post("/products/{$product->id}/variants", [
        'name' => 'One Size',
        'stock_qty' => 1,
    ]);

    $variant = $product->fresh()->variants->sole();

    expect($variant->price_kobo)->toBeNull();
    expect($variant->priceKobo())->toBe($product->price_kobo);
});

it('keeps a submitted variant sku', function () {
    [$seller, , $product] = sellerWithProduct();

    actingAs($seller)->post("/products/{$product->id}/variants", [
        'name' => 'Medium',
        'sku' => 'MY-CUSTOM-SKU',
    ]);

    expect($product->fresh()->variants->sole()->sku)->toBe('MY-CUSTOM-SKU');
});

it('updates a variant without touching the product', function () {
    [$seller, , $product] = sellerWithProduct();

    $variant = $product->variants()->create(['name' => 'Small', 'stock_qty' => 1]);

    actingAs($seller)->put("/variants/{$variant->id}", [
        'name' => 'Small',
        'price' => '4500',
        'stock_qty' => 6,
    ])->assertRedirect(route('products.variants.index', $product));

    expect($variant->fresh()->price_kobo)->toBe(450000);
    expect($variant->fresh()->stock_qty)->toBe(6);
    expect($product->fresh()->price_kobo)->toBe($product->price_kobo);
});

it('opens the edit form for a variant', function () {
    [$seller, , $product] = sellerWithProduct();

    $variant = $product->variants()->create(['name' => 'Large', 'stock_qty' => 4]);

    actingAs($seller)->get(route('variants.edit', $variant))
        ->assertOk()
        ->assertSee('Edit variant')
        ->assertSee('Large');
});

it('links each variant on the index to its own edit page', function () {
    [$seller, , $product] = sellerWithProduct();

    $variants = $product->variants()->createMany([
        ['name' => 'Large'],
        ['name' => 'Small'],
    ]);

    $response = actingAs($seller)->get(route('products.variants.index', $product));

    $response->assertOk();

    foreach ($variants as $variant) {
        $response->assertSee(route('variants.edit', $variant), false);
    }
});

it('deletes a variant', function () {
    [$seller, , $product] = sellerWithProduct();

    $variant = $product->variants()->create(['name' => 'Small']);

    actingAs($seller)->delete("/variants/{$variant->id}")->assertRedirect();

    expect($product->fresh()->variants)->toHaveCount(0);
});

it('validates the variant payload', function () {
    [$seller, , $product] = sellerWithProduct();

    actingAs($seller)->post("/products/{$product->id}/variants", [])->assertSessionHasErrors('name');
    actingAs($seller)->post("/products/{$product->id}/variants", ['name' => 'Bad', 'price' => '-1'])->assertSessionHasErrors('price');
});

it('rejects a duplicate variant sku', function () {
    [$seller, , $product] = sellerWithProduct();

    actingAs($seller)->post("/products/{$product->id}/variants", ['name' => 'A', 'sku' => 'DUP-1']);

    actingAs($seller)->post("/products/{$product->id}/variants", ['name' => 'B', 'sku' => 'DUP-1'])
        ->assertSessionHasErrors('sku');
});

it('scopes variant skus to one store so two sellers may reuse a code', function () {
    [$seller, $page, $product] = sellerWithProduct();

    actingAs($seller)->post("/products/{$product->id}/variants", ['name' => 'A', 'sku' => 'BAG-001'])
        ->assertRedirect();

    [$otherSeller, , $otherProduct] = sellerWithProduct();

    actingAs($otherSeller)->post("/products/{$otherProduct->id}/variants", ['name' => 'A', 'sku' => 'BAG-001'])
        ->assertRedirect();

    expect(ProductVariant::withoutGlobalScopes()->where('sku', 'BAG-001')->count())->toBe(2)
        ->and(ProductVariant::withoutGlobalScopes()->where('sku', 'BAG-001')->where('store_page_id', $page->id)->count())->toBe(1);
});

it('lets a variant keep its own sku when it is edited', function () {
    [$seller, , $product] = sellerWithProduct();

    $variant = $product->variants()->create(['name' => 'A', 'sku' => 'KEEP-ME']);

    actingAs($seller)->put("/variants/{$variant->id}", [
        'name' => 'A renamed',
        'sku' => 'KEEP-ME',
    ])->assertRedirect(route('products.variants.index', $product));

    expect($variant->fresh()->sku)->toBe('KEEP-ME');
    expect($variant->fresh()->name)->toBe('A renamed');
});

it('forbids touching variants of another seller\'s product', function () {
    [$seller] = sellerWithPage();

    $otherPage = StorePage::factory()->create();
    $foreignProduct = Product::factory()->create([
        'store_page_id' => $otherPage->id,
        'seller_id' => $otherPage->seller_id,
    ]);
    $foreignVariant = $foreignProduct->variants()->create(['name' => 'Not Yours']);

    actingAs($seller)->get("/products/{$foreignProduct->id}/variants")->assertNotFound();
    actingAs($seller)->get(route('variants.edit', $foreignVariant))->assertNotFound();
    actingAs($seller)->put("/variants/{$foreignVariant->id}", ['name' => 'Hijack'])->assertNotFound();
    actingAs($seller)->delete("/variants/{$foreignVariant->id}")->assertNotFound();
});

it('generates a unique sku for every variant', function () {
    [, , $product] = sellerWithProduct();

    $skus = collect(range(1, 5))
        ->map(fn () => $product->variants()->create(['name' => 'V'])->sku)
        ->unique();

    expect($skus)->toHaveCount(5);
    expect(ProductVariant::generateSku())->toStartWith('VAR-');
});

it('stores an uploaded variant photo', function () {
    [$seller, , $product] = sellerWithProduct();

    $path = UploadedFile::fake()->image('red.webp', 400, 400);

    actingAs($seller)->post("/products/{$product->id}/variants", [
        'name' => 'Red',
        'image' => $path,
    ])->assertRedirect(route('products.variants.index', $product));

    $variant = $product->fresh()->variants->sole();

    expect($variant->image_path)->not->toBeNull();
    expect(Storage::disk('public')->exists($variant->image_path))->toBeTrue();
});

it('shows the variant image url when one is stored', function () {
    [, , $product] = sellerWithProduct();

    $variant = $product->variants()->create(['name' => 'Red']);

    expect($variant->imageUrl())->toBeNull();

    $variant->update(['image_path' => 'variants/1/red.webp']);

    expect($variant->fresh()->imageUrl())->toContain('variants/1/red.webp');
});

it('paginates the variant list', function () {
    [$seller, , $product] = sellerWithProduct();

    $product->variants()->createMany(
        collect(range(1, 21))->map(fn (int $number): array => ['name' => "Variant {$number}"])->all()
    );

    actingAs($seller)->get(route('products.variants.index', $product))
        ->assertOk()
        ->assertSee('page=2');
});
