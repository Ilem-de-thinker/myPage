<?php

use App\Domains\Catalog\Jobs\ProcessProductImport;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImport;
use Illuminate\Support\Facades\Storage;

it('imports valid rows and quarantines rows whose price does not parse', function () {
    [$seller, $page] = sellerWithPage();

    Storage::fake('local');
    Storage::disk('local')->put('imports/products.csv', implode("\n", [
        'name,price,stock_qty,status',
        'Ankara Print,15000,12,active',
        'Mystery Item,banana,3,draft',
    ]));

    $import = ProductImport::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'filename' => 'products.csv',
        'path' => 'imports/products.csv',
    ]);

    (new ProcessProductImport($import->id))->handle();

    $import->refresh();

    expect($import->status)->toBe('complete')
        ->and($import->imported_count)->toBe(1)
        ->and($import->failed_count)->toBe(1)
        ->and($import->errors)->toHaveCount(1);

    expect(Product::where('store_page_id', $page->id)->sole()->name)->toBe('Ankara Print');
    Storage::disk('local')->assertMissing('imports/products.csv');
});

it('fails the import instead of crashing when the CSV is missing', function () {
    [$seller, $page] = sellerWithPage();

    Storage::fake('local');

    $import = ProductImport::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'filename' => 'gone.csv',
        'path' => 'imports/gone.csv',
    ]);

    (new ProcessProductImport($import->id))->handle();

    expect($import->refresh()->status)->toBe('failed');
});

it('survives rows with more columns than the header', function () {
    [$seller, $page] = sellerWithPage();

    Storage::fake('local');
    Storage::disk('local')->put('imports/ragged.csv', implode("\n", [
        'name,price,stock_qty',
        'Ankara Print,15000,12,unexpected,extra',
    ]));

    $import = ProductImport::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'filename' => 'ragged.csv',
        'path' => 'imports/ragged.csv',
    ]);

    (new ProcessProductImport($import->id))->handle();

    $import->refresh();

    expect($import->status)->toBe('complete')
        ->and($import->imported_count)->toBe(1);
});
