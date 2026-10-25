<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

it('serves a platform manifest and service worker', function () {
    get('/manifest.webmanifest')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('short_name', 'mypage.ng')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('icons.0.type', 'image/png');

    get('/service-worker.js')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript')
        ->assertSee('networkFirst');
});

it('serves a branded manifest, icons and offline page for a merchant', function () {
    [, $page] = sellerWithPage(['status' => 'published', 'slug' => 'pwa-shop']);

    get(storeUrl($page->slug, '/manifest.webmanifest'))
        ->assertOk()
        ->assertJsonPath('name', $page->name)
        ->assertJsonPath('scope', '/')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('icons.0.type', 'image/png')
        ->assertJsonCount(2, 'icons');

    get(storeUrl($page->slug, '/pwa/icon/192'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'max-age=86400, public');

    get(storeUrl($page->slug, '/offline'))
        ->assertOk()
        ->assertSee($page->name);
});

it('renders a raster icon from a merchant logo when one is available', function () {
    Storage::fake('public');
    [, $page] = sellerWithPage(['status' => 'published', 'slug' => 'logo-shop']);
    $page->update(['logo_path' => UploadedFile::fake()->image('logo.jpg')->store('storefront/logo', 'public')]);

    $response = get(storeUrl($page->slug, '/pwa/icon/512'));

    $response->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($response->getContent())->toStartWith("\x89PNG");
});
