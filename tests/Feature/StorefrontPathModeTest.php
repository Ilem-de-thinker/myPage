<?php

use App\Shared\Support\StoreUrl;

it('serves storefronts under /store/{slug} when path mode is enabled', function () {
    $previous = [
        'putenv' => getenv('STOREFRONT_MODE'),
        'env' => $_ENV['STOREFRONT_MODE'] ?? null,
        'server' => $_SERVER['STOREFRONT_MODE'] ?? null,
    ];

    putenv('STOREFRONT_MODE=path');
    $_ENV['STOREFRONT_MODE'] = 'path';
    $_SERVER['STOREFRONT_MODE'] = 'path';

    try {
        $this->refreshApplication();
        $this->artisan('migrate', ['--force' => true]);

        [$seller, $page] = sellerWithPage([
            'theme' => 'store',
            'status' => 'published',
        ]);

        expect(config('mypage.storefront_mode'))->toBe('path')
            ->and(StoreUrl::home($page))->toContain('/store/'.$page->slug);

        $this->get('/store/'.$page->slug)->assertOk();
        $this->get('/store/'.$page->slug.'/products')->assertOk();
        $this->get('/store/unknown-store')->assertNotFound();
    } finally {
        if ($previous['putenv'] === false) {
            putenv('STOREFRONT_MODE');
        } else {
            putenv('STOREFRONT_MODE='.$previous['putenv']);
        }

        if ($previous['env'] === null) {
            unset($_ENV['STOREFRONT_MODE']);
        } else {
            $_ENV['STOREFRONT_MODE'] = $previous['env'];
        }

        if ($previous['server'] === null) {
            unset($_SERVER['STOREFRONT_MODE']);
        } else {
            $_SERVER['STOREFRONT_MODE'] = $previous['server'];
        }

        $this->refreshApplication();
    }
});
