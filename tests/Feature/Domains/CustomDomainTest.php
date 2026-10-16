<?php

use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Services\Feature;
use App\Domains\Catalog\Models\Product;
use App\Domains\Domains\Enums\DomainStatus;
use App\Domains\Domains\Jobs\VerifyDomainDns;
use App\Domains\Domains\Models\DomainBinding;
use App\Domains\Domains\Services\Contracts\DnsResolver;
use App\Domains\Domains\Services\DomainVerifier;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\Hostname;
use App\Shared\Support\ReservedHostnames;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeDnsResolver;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function (): void {
    config([
        'mypage.features.custom_domains' => true,
        'mypage.custom_domain.cname_target' => 'domains.mypage.test',
    ]);
});

/**
 * A seller on the plan the feature is sold with.
 *
 * @return array{0: Seller, 1: StorePage}
 */
function proSeller(string $slug = 'ada-textiles'): array
{
    $seller = Seller::factory()->create();
    $page = StorePage::factory()->for($seller)->create([
        'slug' => $slug,
        'status' => 'published',
    ]);

    $plan = Plan::firstOrCreate(['slug' => 'pro'], ['name' => 'Pro', 'interval' => 'monthly', 'is_active' => true]);

    Subscription::factory()->create([
        'seller_id' => $seller->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'current_period_ends_at' => now()->addMonth(),
    ]);

    return [$seller, $page];
}

function bindingFor(StorePage $page, string $host = 'shop.com', DomainStatus $status = DomainStatus::Pending): DomainBinding
{
    return DomainBinding::withoutGlobalScopes()->create([
        'seller_id' => $page->seller_id,
        'store_page_id' => $page->id,
        'host' => $host,
        'status' => $status,
    ]);
}

/**
 * A storefront request pinned to a seller's domain.
 *
 * The host has to be set per request. Left implicit it leaks into the next
 * call in the same test, and a later dashboard request ends up addressed to the
 * shop — where /domains is correctly not a route.
 */
function shopRequest(string $path = '/', string $host = 'shop.com')
{
    return get("http://{$host}{$path}");
}

/**
 * A request to the platform's own domain.
 *
 * Written out in full because the host leaks between calls within a test, and
 * a storefront call that precedes a dashboard call would otherwise re-address
 * the next request to the seller's own domain, where /domains is correctly not
 * a route at all.
 */
function apexRequest(string $method, string $path, array $data = []): TestResponse
{
    return test()->call($method, "http://localhost{$path}", $data);
}

function dnsPointingAtUs(string ...$hosts): FakeDnsResolver
{
    return FakeDnsResolver::for(array_fill_keys($hosts, 'domains.mypage.test'));
}

describe('hostname normalisation', function () {
    it('lowercases and strips a scheme, a trailing dot and a path', function (string $input, string $expected) {
        expect(Hostname::normalise($input))->toBe($expected);
    })->with([
        'bare' => ['shop.com', 'shop.com'],
        'mixed case' => ['Shop.COM', 'shop.com'],
        'with scheme' => ['https://shop.com', 'shop.com'],
        'with www' => ['https://www.shop.com', 'www.shop.com'],
        'trailing dot' => ['shop.com.', 'shop.com'],
        'padded' => ['  shop.com  ', 'shop.com'],
        'path is dropped' => ['https://shop.com/about', 'shop.com'],
    ]);

    it('refuses anything that is not a bare domain', function (string $input) {
        expect(Hostname::normalise($input))->toBeNull();
    })->with([
        'ip address' => ['192.168.1.1'],
        'credentials' => ['user@shop.com'],
        'port' => ['shop.com:8080'],
        'spaces' => ['my shop.com'],
        'no dot' => ['localhost'],
        'empty' => [''],
        'bad label' => ['-shop.com'],
        'trailing hyphen' => ['shop-.com'],
    ]);
});

describe('reserved hostnames', function () {
    it('refuses the platform apex and anything under it', function (string $host) {
        expect(ReservedHostnames::isReserved($host))->toBeTrue();
    })->with([
        'apex' => ['mypage.test'],
        'www' => ['www.mypage.test'],
        'subdomain' => ['login.mypage.test'],
        'deep subdomain' => ['a.b.mypage.test'],
    ]);

    it('allows a domain that merely ends with the same letters', function () {
        // "notmypage.test" is not "mypage.test" and must not be refused with it.
        expect(ReservedHostnames::isReserved('notmypage.test'))->toBeFalse();
        expect(ReservedHostnames::isReserved('shop.com'))->toBeFalse();
    });
});

describe('dns verification', function () {
    it('activates a domain whose CNAME points at us', function () {
        [$seller, $page] = proSeller();
        $binding = bindingFor($page);

        $resolver = dnsPointingAtUs('shop.com');
        $status = app(DomainVerifier::class, ['dns' => $resolver])->verify($binding);

        expect($status)->toBe(DomainStatus::Active)
            ->and($binding->refresh()->verified_at)->not->toBeNull()
            ->and($binding->last_error)->toBeNull();
    });

    it('leaves a domain pending when nothing points at us', function () {
        [$seller, $page] = proSeller();
        $binding = bindingFor($page);

        $resolver = FakeDnsResolver::for(['shop.com' => 'parking.example.com']);
        $status = app(DomainVerifier::class, ['dns' => $resolver])->verify($binding);

        expect($status)->toBe(DomainStatus::Pending)
            ->and($binding->refresh()->last_error)->toContain('domains.mypage.test');
    });

    it('leaves a domain pending when it has no CNAME at all', function () {
        [$seller, $page] = proSeller();
        $binding = bindingFor($page);

        $status = app(DomainVerifier::class, ['dns' => FakeDnsResolver::for([])])->verify($binding);

        expect($status)->toBe(DomainStatus::Pending);
    });

    it('suspends a live domain that stops pointing at us', function () {
        [$seller, $page] = proSeller();
        $binding = bindingFor($page, 'shop.com', DomainStatus::Active);

        $resolver = FakeDnsResolver::for(['shop.com' => 'somewhere-else.example.com']);
        $status = app(DomainVerifier::class, ['dns' => $resolver])->verify($binding);

        expect($status)->toBe(DomainStatus::Suspended)
            ->and($binding->refresh()->last_error)->toContain('no longer points');
    });

    it('keeps a live domain live while its DNS is still correct', function () {
        [$seller, $page] = proSeller();
        $binding = bindingFor($page, 'shop.com', DomainStatus::Active);

        $status = app(DomainVerifier::class, ['dns' => dnsPointingAtUs('shop.com')])->verify($binding);

        expect($status)->toBe(DomainStatus::Active)
            ->and($binding->refresh()->status)->toBe(DomainStatus::Active);
    });
});

describe('serving a custom domain', function () {
    it('serves the shop on a verified domain', function () {
        [$seller, $page] = proSeller();
        bindingFor($page, 'shop.com', DomainStatus::Active);

        shopRequest('/')
            ->assertOk()
            ->assertSee($page->name);
    });

    it('serves the shop on the www spelling of the same domain', function () {
        [$seller, $page] = proSeller();
        bindingFor($page, 'shop.com', DomainStatus::Active);

        shopRequest('/', 'www.shop.com')->assertOk()->assertSee($page->name);
    });

    it('does not serve a domain that has not been verified', function () {
        [$seller, $page] = proSeller();
        bindingFor($page, 'shop.com', DomainStatus::Pending);

        shopRequest('/')->assertNotFound();
    });

    it('does not serve a domain whose DNS stopped pointing at us', function () {
        [$seller, $page] = proSeller();
        bindingFor($page, 'shop.com', DomainStatus::Suspended);

        shopRequest('/')->assertNotFound();
    });

    it('never serves one seller on another seller\'s domain', function () {
        [$ada, $adaPage] = proSeller('ada-textiles');
        [$kemi, $kemiPage] = proSeller('kemi-braids');

        // Kemi's shop is the one bound to the host. Ada's slug must not be
        // reachable there, and vice versa.
        bindingFor($kemiPage, 'shop.com', DomainStatus::Active);

        shopRequest('/')->assertOk()->assertSee($kemiPage->name)->assertDontSee($adaPage->name);
    });

    it('serves the shop listing, not the seller dashboard, on a colliding path', function () {
        [$seller, $page] = proSeller();
        bindingFor($page, 'shop.com', DomainStatus::Active);

        Product::factory()->create([
            'store_page_id' => $page->id,
            'seller_id' => $seller->id,
            'name' => 'Listed Product',
            'status' => 'active',
        ]);

        // /products is both a dashboard screen and a shop listing. On the seller's
        // own domain it has to be the shop.
        shopRequest('/products')
            ->assertOk()
            ->assertSee('Listed Product')
            ->assertDontSee('Sign in');
    });

    it('serves the shop on paths the dashboard also claims', function (string $path) {
        [$seller, $page] = proSeller();
        bindingFor($page, 'shop.com', DomainStatus::Active);

        shopRequest($path)->assertOk();
    })->with(['/about', '/contact', '/cart']);

    it('does not expose platform pages on a seller domain', function (string $path) {
        [$seller, $page] = proSeller();
        bindingFor($page, 'shop.com', DomainStatus::Active);

        // /login and /dashboard are ours, not the shop's. A seller's domain must
        // never render our sign-in page.
        shopRequest($path)->assertNotFound();
    })->with(['/login', '/dashboard', '/orders', '/register']);

    it('still serves the mypage.ng slug after a domain is added', function () {
        [$seller, $page] = proSeller();
        bindingFor($page, 'shop.com', DomainStatus::Active);

        get('/ada-textiles')->assertOk()->assertSee($page->name);
    });
});

describe('seller dashboard', function () {
    it('adds a domain and queues a check', function () {
        Queue::fake();
        [$seller] = proSeller();

        actingAs($seller)->post('http://localhost/domains', ['domain' => 'Shop.COM'])->assertSessionDoesntHaveErrors();

        $binding = DomainBinding::withoutGlobalScopes()->where('host', 'shop.com')->sole();

        expect($binding->seller_id)->toBe($seller->id)
            ->and($binding->status)->toBe(DomainStatus::Pending);

        Queue::assertPushed(VerifyDomainDns::class);
    });

    it('accepts a domain pasted with a scheme', function () {
        Queue::fake();
        [$seller] = proSeller();

        actingAs($seller)->post('http://localhost/domains', ['domain' => 'https://www.shop.com'])
            ->assertSessionDoesntHaveErrors();

        expect(DomainBinding::withoutGlobalScopes()->where('host', 'shop.com')->exists())->toBeTrue();
    });

    it('refuses a domain that is not a domain', function () {
        Queue::fake();
        [$seller] = proSeller();

        actingAs($seller)->post('http://localhost/domains', ['domain' => 'not a domain'])
            ->assertSessionHasErrors('domain');

        expect(DomainBinding::withoutGlobalScopes()->count())->toBe(0);
    });

    it('refuses the platform apex', function () {
        Queue::fake();
        [$seller] = proSeller();

        actingAs($seller)->post('http://localhost/domains', ['domain' => 'mypage.test'])->assertSessionHasErrors('domain');
        actingAs($seller)->post('http://localhost/domains', ['domain' => 'login.mypage.test'])->assertSessionHasErrors('domain');
    });

    it('refuses a domain another seller already holds', function () {
        Queue::fake();
        [$ada, $adaPage] = proSeller('ada-textiles');
        [$kemi] = proSeller('kemi-braids');

        bindingFor($adaPage, 'shop.com', DomainStatus::Active);

        actingAs($kemi)->post('/domains', ['domain' => 'shop.com'])
            ->assertSessionHasErrors('domain');
    });

    it('lets a seller re-add their own domain without an error', function () {
        Queue::fake();
        [$seller, $page] = proSeller();

        bindingFor($page, 'shop.com', DomainStatus::Pending);

        actingAs($seller)->post('http://localhost/domains', ['domain' => 'shop.com'])
            ->assertSessionDoesntHaveErrors();

        expect(DomainBinding::withoutGlobalScopes()->where('host', 'shop.com')->count())->toBe(1);
    });

    it('cannot add a domain without a plan that includes it', function () {
        Queue::fake();

        $seller = Seller::factory()->create();
        StorePage::factory()->for($seller)->create();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'interval' => 'monthly', 'is_active' => true]);
        Subscription::factory()->create([
            'seller_id' => $seller->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'current_period_ends_at' => now()->addMonth(),
        ]);

        actingAs($seller)->post('http://localhost/domains', ['domain' => 'shop.com']);

        expect(DomainBinding::withoutGlobalScopes()->count())->toBe(0);
    });

    it('cannot touch another seller\'s binding', function () {
        [$ada, $adaPage] = proSeller('ada-textiles');
        [$kemi] = proSeller('kemi-braids');

        $adasBinding = bindingFor($adaPage, 'shop.com');

        actingAs($kemi)->delete("http://localhost/domains/{$adasBinding->id}")->assertNotFound();
        actingAs($kemi)->post("http://localhost/domains/{$adasBinding->id}/verify")->assertNotFound();

        expect(DomainBinding::withoutGlobalScopes()->whereKey($adasBinding->id)->exists())->toBeTrue();
    });

    it('stops serving a domain the seller removes', function () {
        [$seller, $page] = proSeller();
        $binding = bindingFor($page, 'shop.com', DomainStatus::Active);

        shopRequest('/')->assertOk();

        actingAs($seller)->delete("http://localhost/domains/{$binding->id}")->assertSessionDoesntHaveErrors();

        // The cached lookup must be dropped with the row, or the shop keeps
        // answering on a domain the seller has given up.
        shopRequest('/')->assertNotFound();
    });

    it('hides the whole feature while the platform flag is off', function () {
        config([
            'mypage.features.custom_domains' => false,
            'mypage.features_locked' => true,
        ]);

        [$seller] = proSeller();

        actingAs($seller)->get('http://localhost/domains')->assertNotFound();
        actingAs($seller)->post('http://localhost/domains', ['domain' => 'shop.com'])->assertNotFound();
    });

    it('shows the seller the CNAME target they need', function () {
        [$seller, $page] = proSeller();
        bindingFor($page, 'shop.com', DomainStatus::Pending);

        actingAs($seller)->get('http://localhost/domains')
            ->assertOk()
            ->assertSee('CNAME')
            ->assertSee('domains.mypage.test')
            ->assertSee('Check again');
    });

    it('tells a pro seller how to connect, not to upgrade', function () {
        [$seller] = proSeller();

        actingAs($seller)->get('http://localhost/domains')
            ->assertOk()
            ->assertSee('Add a domain')
            ->assertDontSee('plan includes your own domain');
    });

    it('tells a starter seller which plan is needed', function () {
        $seller = Seller::factory()->create();
        StorePage::factory()->for($seller)->create();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'interval' => 'monthly', 'is_active' => true]);
        Subscription::factory()->create([
            'seller_id' => $seller->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'current_period_ends_at' => now()->addMonth(),
        ]);

        actingAs($seller)->get('http://localhost/domains')
            ->assertOk()
            ->assertSee('Pro plan includes your own domain');
    });
});

describe('verification job', function () {
    it('does nothing for a binding that has been deleted since dispatch', function () {
        $dns = dnsPointingAtUs('shop.com');
        app()->instance(DnsResolver::class, $dns);

        [$seller, $page] = proSeller();
        $binding = bindingFor($page);
        $binding->delete();

        (new VerifyDomainDns($binding))->handle(app(DomainVerifier::class));

        expect(DomainBinding::withoutGlobalScopes()->whereKey($binding->id)->exists())->toBeFalse();
    });
});

describe('the pending sweep', function () {
    it('queues a check for every domain still waiting', function () {
        Queue::fake();

        [$seller, $page] = proSeller();
        bindingFor($page, 'waiting.com', DomainStatus::Pending);
        bindingFor($page, 'live.com', DomainStatus::Active);

        $this->artisan('domains:verify-pending')->assertSuccessful();

        Queue::assertPushed(VerifyDomainDns::class, 1);
    });
});
