<?php

use App\Domains\Catalog\Models\Category;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;

use function Pest\Laravel\actingAs;

test('seller can create a category for their page', function () {
    $seller = Seller::factory()->create();
    StorePage::factory()->for($seller)->create();

    actingAs($seller)->postJson('/categories', ['name' => 'Fabrics'])
        ->assertCreated()
        ->assertJsonPath('name', 'Fabrics');

    expect(Category::where('name', 'Fabrics')->exists())->toBeTrue();
});
