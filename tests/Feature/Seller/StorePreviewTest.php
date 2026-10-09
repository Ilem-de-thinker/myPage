<?php

use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\StoreUrl;

use function Pest\Laravel\get;

it('previews a draft store with a signed url', function () {
    $seller = Seller::factory()->create();
    $page = StorePage::factory()->for($seller)->create(['slug' => 'draft-shop', 'theme' => 'store', 'status' => 'draft']);

    $url = StoreUrl::preview($page);

    expect($url)->toContain('signature=');

    get($url)->assertOk();

    get('/draft-shop')->assertNotFound();
});
