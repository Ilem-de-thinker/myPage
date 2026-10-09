<?php

use App\Domains\Catalog\Actions\AdjustStock;
use App\Domains\Catalog\Exceptions\OutOfStock;
use App\Domains\Catalog\Models\InventoryLog;

use function Pest\Laravel\actingAs;

it('adjusts stock and writes an inventory log', function () {
    [$seller, $page, $product] = sellerWithProduct(['stock_qty' => 4, 'track_stock' => true]);

    app(AdjustStock::class)->handle($product, -2, 'order placed');

    expect($product->fresh()->stock_qty)->toBe(2);

    $log = InventoryLog::where('product_id', $product->id)->first();

    expect($log)->not->toBeNull();
    expect($log->delta)->toBe(-2);
    expect($log->reason)->toBe('order placed');
});

it('refuses to take tracked stock below zero', function () {
    [, , $product] = sellerWithProduct(['stock_qty' => 1, 'track_stock' => true]);

    expect(fn () => app(AdjustStock::class)->handle($product, -5, 'too many'))->toThrow(OutOfStock::class);

    expect($product->fresh()->stock_qty)->toBe(1);
    expect(InventoryLog::where('product_id', $product->id)->count())->toBe(0);
});

it('allows a negative delta when stock is not tracked', function () {
    [, , $product] = sellerWithProduct(['stock_qty' => 0, 'track_stock' => false]);

    app(AdjustStock::class)->handle($product, -3, 'oversell allowed');

    expect($product->fresh()->stock_qty)->toBe(-3);
});

it('adjusts stock from the dashboard', function () {
    [$seller, , $product] = sellerWithProduct(['stock_qty' => 3, 'track_stock' => true]);

    actingAs($seller)
        ->patch("/products/{$product->id}/stock", ['delta' => 2, 'reason' => 'restock'])
        ->assertRedirect();

    expect($product->fresh()->stock_qty)->toBe(5);
});

it('refuses a dashboard stock change that would go negative', function () {
    [$seller, , $product] = sellerWithProduct(['stock_qty' => 1, 'track_stock' => true]);

    actingAs($seller)
        ->patch("/products/{$product->id}/stock", ['delta' => -5, 'reason' => 'typo'])
        ->assertSessionHasErrors('stock');

    expect($product->fresh()->stock_qty)->toBe(1);
});

it('adjusts stock down through the dashboard endpoint', function () {
    [$seller, , $product] = sellerWithProduct(['stock_qty' => 5, 'track_stock' => true]);

    actingAs($seller)
        ->patch("/products/{$product->id}/stock", ['delta' => -2, 'reason' => 'sold'])
        ->assertRedirect();

    expect($product->fresh()->stock_qty)->toBe(3);
});

it('validates the stock adjust payload', function () {
    [$seller, , $product] = sellerWithProduct();

    actingAs($seller)->patch("/products/{$product->id}/stock", [])->assertSessionHasErrors(['delta', 'reason']);
    actingAs($seller)->patch("/products/{$product->id}/stock", ['delta' => 'lots', 'reason' => 'x'])->assertSessionHasErrors('delta');
});

it('logs a stock change made on the product form', function () {
    [$seller, , $product] = sellerWithProduct(['stock_qty' => 10, 'track_stock' => true]);

    actingAs($seller)->put("/products/{$product->id}", [
        'name' => 'Recounted Item',
        'price' => '1000',
        'status' => 'active',
        'stock_qty' => 7,
    ])->assertRedirect(route('products.edit', $product));

    expect($product->fresh()->stock_qty)->toBe(7);
    expect(InventoryLog::where('product_id', $product->id)->first()->delta)->toBe(-3);
});
