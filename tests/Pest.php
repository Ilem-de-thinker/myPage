<?php

use App\Domains\Catalog\Models\Product;
use App\Domains\Payments\Models\SellerGateway;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * A seller with one store page, returned as [seller, page] for convenience.
 *
 * @return array{0: Seller, 1: StorePage}
 */
function sellerWithPage(array $attributes = []): array
{
    $seller = Seller::factory()->create();
    $page = StorePage::factory()->for($seller)->create($attributes);

    // Buyer money settles in the seller's own gateway account, so a shop that
    // can check out has a key. Tests that exercise the unconfigured path delete
    // this row explicitly.
    SellerGateway::factory()->for($seller)->create();

    return [$seller, $page];
}

/**
 * A published store page with an active product, returned as [seller, page, product].
 *
 * @return array{0: Seller, 1: StorePage, 2: Product}
 */
function sellerWithProduct(array $productAttributes = [], array $pageAttributes = []): array
{
    [$seller, $page] = sellerWithPage($pageAttributes);

    $product = Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
        ...$productAttributes,
    ]);

    return [$seller, $page, $product];
}

/**
 * Stub the Paystack API so payment flows can be exercised without credentials.
 * Register this once per test: repeated Http::fake() calls append stubs and the
 * first matching pattern wins.
 *
 * @param  array{status?: string, amount_kobo?: int|null, transfer_success?: bool, bank_resolve_success?: bool}  $options
 */
function fakePaystack(array $options = []): void
{
    $options += [
        'status' => 'success',
        'amount_kobo' => null,
        'transfer_success' => true,
        'bank_resolve_success' => true,
    ];

    $transferStatus = $options['transfer_success'] ? 'success' : 'failed';

    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'data' => ['authorization_url' => 'https://checkout.paystack.com/test-checkout'],
        ]),
        'api.paystack.co/transaction/verify/*' => function ($request) use ($options) {
            $reference = Str::after($request->url(), '/verify/');

            // Echo back what that transaction was actually charged, unless the
            // test is deliberately simulating an underpayment.
            $amount = $options['amount_kobo'] ?? Transaction::where('reference', $reference)->value('amount_kobo');

            return Http::response([
                'data' => array_filter([
                    'status' => $options['status'],
                    'amount' => $amount,
                    'currency' => $options['currency'] ?? 'NGN',
                    'paid_at' => now()->toIso8601String(),
                ], fn ($value) => $value !== null),
            ]);
        },
        'api.paystack.co/transfer/transferrecipient' => Http::response([
            'data' => ['recipient_code' => 'RCP_test'],
        ]),
        'api.paystack.co/plan/*' => Http::response([
            'data' => ['name' => 'Growth', 'interval' => 'monthly'],
        ]),
        // A failing lookup is how the bank endpoint behaves when credentials are
        // wrong or the gateway is down. Both are third-party faults a seller
        // must never see as a crash.
        'api.paystack.co/bank/resolve*' => $options['bank_resolve_success']
            ? Http::response(['data' => ['account_name' => 'Test Seller']])
            : Http::response(['message' => 'Invalid key'], 401),
        'api.paystack.co/transfer' => Http::response([
            'data' => ['status' => $transferStatus, 'reference' => 'TRF_test'],
        ]),
    ]);
}

/**
 * A published service page with at least one bookable service on it.
 *
 * @return array{0: Seller, 1: StorePage}
 */
function bookableServicePage(): array
{
    $seller = Seller::factory()->create();
    $page = StorePage::factory()->for($seller)->create([
        'theme' => 'service',
        'status' => 'published',
    ]);

    return [$seller, $page];
}

/**
 * A storefront URL on the shop's own subdomain.
 *
 * The slug is the address, not a path segment, so a request to the apex never
 * reaches a shop and a shop link always names the business.
 */
function storeUrl(string $slug, string $path = '/'): string
{
    return 'http://'.$slug.'.'.config('mypage.domain').($path === '' ? '/' : $path);
}
