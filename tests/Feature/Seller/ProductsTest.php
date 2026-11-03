<?php

use App\Domains\Catalog\Jobs\PurgeDeletedProducts;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

/** A product owned by a different seller, used for tenancy assertions. */
function otherSellerProduct(string $name = 'Other Seller Product'): Product
{
    $page = StorePage::factory()->create();

    return Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'name' => $name,
    ]);
}

it('lists products for the seller page only', function () {
    [$seller, $page] = sellerWithPage();

    Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
        'name' => 'Adire Fabric',
    ]);

    // The global scope must hide another seller's product.
    otherSellerProduct();

    actingAs($seller)->get('/products')
        ->assertOk()
        ->assertSee('Adire Fabric')
        ->assertDontSee('Other Seller Product');
});

it('creates a product with kobo conversion', function () {
    [$seller, $page] = sellerWithPage();

    $product = Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
        'name' => 'Ankara Print',
        'price' => 1250000,
        'status' => 'active',
        'stock_qty' => 4,
    ]);

    actingAs($seller)->post('/products', [
        'name' => 'Ankara Print',
        'price' => '12500',
        'status' => 'active',
        'stock_qty' => 4,
    ])->assertRedirect();

    expect($product->price_kobo)->not->toBeNull();
    expect($product->store_page_id)->toBe($page->id);
    expect($product->seller_id)->toBe($seller->id);
});

it('stores a delivery override on a product', function () {
    [$seller] = sellerWithPage();

    actingAs($seller)->post('/products', [
        'name' => 'Heavy Armchair',
        'price' => '40000',
        'status' => 'active',
        'delivery_mode' => 'pickup',
        'delivery_fee' => '2500',
    ])->assertRedirect();

    $product = Product::withoutGlobalScopes()->sole();

    expect($product->delivery_mode->value)->toBe('pickup')
        ->and($product->delivery_fee_kobo)->toBe(250_000);
});

it('updates a product and stores the price in kobo', function () {
    [$seller, , $product] = sellerWithProduct();

    actingAs($seller)->put("/products/{$product->id}", [
        'name' => 'Renamed',
        'price' => '9000',
        'status' => 'archived',
    ])->assertRedirect(route('products.edit', $product));

    expect($product->fresh()->name)->toBe('Renamed');
    expect($product->fresh()->price_kobo)->toBe(900000);
    expect($product->fresh()->status)->toBe('archived');
});

it('forbids editing another seller\'s product', function () {
    [$seller] = sellerWithPage();

    $other = otherSellerProduct();

    actingAs($seller)->get("/products/{$other->id}/edit")->assertNotFound();
    actingAs($seller)->put("/products/{$other->id}", ['name' => 'x', 'price' => 1, 'status' => 'draft'])->assertNotFound();
});

it('deletes a product', function () {
    [$seller, , $product] = sellerWithProduct();

    actingAs($seller)->delete("/products/{$product->id}")->assertRedirect(route('products.index'));
    expect(Product::find($product->id))->toBeNull();
});

it('bulk deletes only own products', function () {
    [$seller, $page] = sellerWithPage();

    // The other seller's id in the payload must be ignored.
    $a = Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
    ]);

    $b = Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
    ]);

    $other = otherSellerProduct();

    actingAs($seller)->post('/products/bulk-destroy', ['ids' => [$a->id, $b->id, $other->id]])
        ->assertRedirect(route('products.index'));

    expect(Product::find($a->id))->toBeNull();
    expect(Product::find($b->id))->toBeNull();
    expect(Product::withoutGlobalScopes()->find($other->id))->not->toBeNull();
});

it('filters products by search', function () {
    [$seller, $page] = sellerWithPage();

    Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
        'name' => 'Ankara Shirt',
    ]);

    Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
        'name' => 'Lace Gown',
    ]);

    // Only the matching product is returned.
    actingAs($seller)->get('/products?q=Ankara')
        ->assertOk()
        ->assertSee('Ankara Shirt')
        ->assertDontSee('Lace Gown');
});

it('auto-generates a sku when blank', function () {
    [$seller] = sellerWithPage();

    $product = Product::factory()->create([
        'store_page_id' => null,
        'seller_id' => $seller->id,
        'name' => 'Auto Sku Item',
        'price' => 1000,
        'status' => 'draft',
    ]);

    actingAs($seller)->post('/products', [
        'name' => 'Auto Sku Item',
        'price' => '1000',
        'status' => 'draft',
    ])->assertRedirect();

    // A blank sku must still store a generated value.
    $product = Product::firstWhere('name', 'Auto Sku Item');
    expect($product->sku)->toStartWith('SKU-');
});

it('rejects a category that belongs to another store', function () {
    [$seller, $page] = sellerWithPage();

    [$otherSeller, $otherPage] = sellerWithPage();

    $foreign = $otherPage->categories()->create(['seller_id' => $otherSeller->id, 'name' => 'Foreign Fabrics']);

    actingAs($seller)->post(route('products.store'), [
        'name' => 'Ankara Print',
        'price' => '15000',
        'status' => 'active',
        'category_id' => $foreign->id,
    ])->assertSessionHasErrors('category_id');
});

it('keeps image files while a deleted product is recoverable', function () {
    [$seller, $page] = sellerWithPage();

    Storage::fake('public');

    $product = Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
        'name' => 'File Bound Product',
    ]);

    $path = "products/{$product->id}/a.webp";
    Storage::disk('public')->put($path, 'img');
    $product->images()->create(['path' => $path, 'position' => 0]);

    actingAs($seller)->delete(route('products.destroy', $product))->assertRedirect(route('products.index'));

    // Soft delete: hidden from the catalogue, but the row and its files stay
    // so a mistaken delete is reversible for 30 days.
    Storage::disk('public')->assertExists($path);
    expect(Product::find($product->id))->toBeNull()
        ->and(Product::onlyTrashed()->find($product->id))->not->toBeNull()
        ->and(ProductImage::where('product_id', $product->id)->count())->toBe(1);
});

it('purges a deleted product and its files after the recovery window', function () {
    [$seller, $page] = sellerWithPage();

    Storage::fake('public');

    $product = Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
    ]);

    $path = "products/{$product->id}/old.webp";
    Storage::disk('public')->put($path, 'img');
    $product->images()->create(['path' => $path, 'position' => 0]);
    $product->delete();

    // Still inside the window: the purge must leave it alone.
    (new PurgeDeletedProducts)->handle();
    expect(Product::onlyTrashed()->find($product->id))->not->toBeNull();

    Product::onlyTrashed()->whereKey($product->id)->update(['deleted_at' => now()->subDays(31)]);
    (new PurgeDeletedProducts)->handle();

    expect(Product::onlyTrashed()->find($product->id))->toBeNull()
        ->and(ProductImage::where('product_id', $product->id)->count())->toBe(0);
    Storage::disk('public')->assertMissing($path);
});

it('caps how many photos a product may have', function () {
    [$seller, $page] = sellerWithPage();

    Storage::fake('public');

    $product = Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
    ]);

    foreach (range(0, 7) as $position) {
        $product->images()->create(['path' => "products/{$product->id}/{$position}.webp", 'position' => $position]);
    }

    actingAs($seller)->post(route('products.update', $product), [
        '_method' => 'PUT',
        'name' => $product->name,
        'price' => 1000,
        'status' => 'draft',
        'images' => [UploadedFile::fake()->image('extra.jpg')],
    ])->assertSessionHasErrors('images');
});
