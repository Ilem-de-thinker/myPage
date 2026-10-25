<?php

use App\Domains\Admin\Models\AdminAuditLog;
use App\Domains\Admin\Models\BadgeApplication;
use App\Domains\Admin\Models\BadgeStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Seller\Enums\StoreStatus;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

function adminUser(): Seller
{
    return Seller::factory()->admin()->create(['email_verified_at' => now()]);
}

function pendingApplication(): BadgeApplication
{
    return BadgeApplication::factory()->create();
}

it('hides the whole panel from anyone who is not an admin', function () {
    [$seller] = sellerWithPage();

    foreach (['/admin', '/admin/stores', '/admin/badges', '/admin/transactions', '/admin/audit'] as $url) {
        // 404 rather than 403: an internal panel should not advertise itself.
        actingAs($seller)->get($url)->assertNotFound();
    }

    actingAs($seller)->post(route('admin.stores.suspend', StorePage::factory()->create(['status' => 'published'])), ['reason' => 'x'])
        ->assertNotFound();
});

it('lets an admin into the panel', function () {
    actingAs(adminUser())
        ->get('/admin')
        ->assertOk()
        ->assertSee('Overview')
        ->assertSee('Gross sales');

    actingAs(adminUser())->get('/admin/transactions')->assertOk();
    actingAs(adminUser())->get('/admin/stores')->assertOk();
    actingAs(adminUser())->get('/admin/badges')->assertOk();
    actingAs(adminUser())->get('/admin/audit')->assertOk();
});

it('shows platform money that only counts settled sales', function () {
    $admin = adminUser();

    [$seller, $page] = sellerWithPage(['status' => 'published']);

    // Paid: counts. Pending: does not.
    Order::factory()->paid()->create([
        'seller_id' => $seller->id, 'store_page_id' => $page->id, 'total_kobo' => 1000000,
    ]);
    Order::factory()->platform()->create([
        'seller_id' => $seller->id, 'store_page_id' => $page->id, 'total_kobo' => 5000000,
    ]);

    actingAs($admin)->get('/admin')
        ->assertOk()
        ->assertSee('₦10,000')
        // The unpaid ₦50,000 must not be counted as gross sales.
        ->assertDontSee('₦60,000');
});

it('stamps the page as verified when a badge is approved', function () {
    // The application row says what happened to the request. verified_at is the
    // separate question of whether the page is trusted now, and the directory
    // sort, sitemap priority and structured data all read that one.
    $application = pendingApplication();

    expect($application->storePage->verified_at)->toBeNull();

    actingAs(adminUser())->post(route('admin.badges.decide', $application), ['decision' => 'approve']);

    expect($application->storePage->fresh()->verified_at)->not->toBeNull();
});

it('does not stamp a page when the application is rejected', function () {
    $application = pendingApplication();

    actingAs(adminUser())->post(route('admin.badges.decide', $application), [
        'decision' => 'reject',
        'rejection_reason' => 'The certificate names a different business entirely.',
    ]);

    expect($application->storePage->fresh()->verified_at)->toBeNull();
});

it('shows the badge on the storefront once an admin approves it', function () {
    $admin = adminUser();

    [$seller, $page] = sellerWithPage(['status' => 'published', 'slug' => 'badge-seller']);
    $application = BadgeApplication::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
    ]);

    actingAs($seller)->get(storeUrl('badge-seller'))->assertOk()->assertDontSee('Verified');

    actingAs($admin)->post(route('admin.badges.decide', $application), [
        'decision' => 'approve',
        'note' => 'CAC matches',
    ])->assertRedirect();

    expect($application->fresh()->status)->toBe(BadgeStatus::Approved);

    // And the buyer's page now carries the tick.
    actingAs($seller)->get(storeUrl('badge-seller'))->assertOk()->assertSee('Verified');
});

it('never badges a page whose application was rejected', function () {
    [$seller, $page] = sellerWithPage(['status' => 'published', 'slug' => 'rejected-badge']);

    BadgeApplication::factory()->rejected()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
    ]);

    actingAs($seller)->get(storeUrl('rejected-badge'))->assertOk()->assertDontSee('Verified');
});

it('records who approved a badge and why', function () {
    $admin = adminUser();
    $application = pendingApplication();

    actingAs($admin)->post(route('admin.badges.decide', $application), [
        'decision' => 'approve',
        'note' => 'CAC certificate matches the trading name',
    ]);

    expect($application->fresh()->reviewed_by_seller_id)->toBe($admin->id)
        ->and(AdminAuditLog::where('action', 'badge.approved')->count())->toBe(1);
});

it('insists on a reason before rejecting a badge', function () {
    $admin = adminUser();
    $application = pendingApplication();

    actingAs($admin)->post(route('admin.badges.decide', $application), [
        'decision' => 'reject',
        'rejection_reason' => '   ',
    ])->assertSessionHasErrors('rejection_reason');

    expect($application->fresh()->status)->toBe(BadgeStatus::Pending);
});

it('decides an application only once', function () {
    $admin = adminUser();
    $application = pendingApplication();

    actingAs($admin)->post(route('admin.badges.decide', $application), ['decision' => 'approve']);
    actingAs($admin)->post(route('admin.badges.decide', $application), ['decision' => 'reject', 'rejection_reason' => 'changed my mind']);

    // The second click must not rewrite the first decision or the reviewer's name.
    expect($application->fresh()->status)->toBe(BadgeStatus::Approved)
        ->and($application->fresh()->reviewed_by_seller_id)->toBe($admin->id)
        ->and($application->fresh()->rejection_reason)->toBeNull();
});

it('lets a seller apply with a document', function () {
    Storage::fake('local');

    [$seller, $page] = sellerWithPage();

    actingAs($seller)
        ->post(route('badge.submit'), [
            'business_name' => 'Ada Textiles Limited',
            'document_type' => 'cac',
            'document' => UploadedFile::fake()->create('cac.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect(route('badge.apply'));

    $application = BadgeApplication::sole();

    expect($application->business_name)->toBe('Ada Textiles Limited')
        ->and($application->status)->toBe(BadgeStatus::Pending)
        ->and($application->store_page_id)->toBe($page->id);

    Storage::disk('local')->assertExists($application->document_path);
});

it('refuses a document that is not a pdf or an image', function () {
    Storage::fake('local');

    [$seller] = sellerWithPage();

    actingAs($seller)
        ->post(route('badge.submit'), [
            'business_name' => 'Ada Textiles Limited',
            'document_type' => 'cac',
            'document' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
        ])
        ->assertSessionHasErrors('document');

    expect(BadgeApplication::count())->toBe(0);
});

it('will not take a second application while one is waiting', function () {
    Storage::fake('local');

    [$seller, $page] = sellerWithPage();
    BadgeApplication::factory()->create(['seller_id' => $seller->id, 'store_page_id' => $page->id]);

    actingAs($seller)->post(route('badge.submit'), [
        'business_name' => 'Ada Textiles Limited',
        'document_type' => 'cac',
        'document' => UploadedFile::fake()->create('cac.pdf', 120, 'application/pdf'),
    ])->assertSessionHasErrors('document');

    expect(BadgeApplication::count())->toBe(1);
});

it('never shows one seller another seller\'s applications', function () {
    [$seller, $page] = sellerWithPage();
    $foreign = BadgeApplication::factory()->create(['business_name' => 'Secret Ltd']);

    actingAs($seller)->get(route('badge.apply'))->assertOk()->assertDontSee('Secret Ltd');
    actingAs($seller)->post(route('badge.submit'), [
        'business_name' => $foreign->business_name,
        'document_type' => 'cac',
        'document' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
    ]);
});

it('suspends a store with a reason and puts it back as draft', function () {
    $admin = adminUser();

    [$seller, $page] = sellerWithPage(['status' => 'published', 'slug' => 'badshop']);

    actingAs($admin)->post(route('admin.stores.suspend', $page), [
        'reason' => 'Chargeback fraud',
    ])->assertRedirect();

    expect($page->fresh()->status)->toBe(StoreStatus::Suspended);

    // And it disappears from the storefront entirely.
    actingAs($seller)->get('/badshop')->assertNotFound();

    actingAs($admin)->post(route('admin.stores.reinstate', $page))->assertRedirect();

    // Back to draft, not published: an admin should not put a broken page live.
    expect($page->fresh()->status)->toBe(StoreStatus::Draft);
});

it('insists on a reason before suspending a store', function () {
    $admin = adminUser();
    [, $page] = sellerWithPage(['status' => 'published']);

    actingAs($admin)->post(route('admin.stores.suspend', $page), ['reason' => ' '])
        ->assertSessionHasErrors('reason');

    expect($page->fresh()->status)->toBe(StoreStatus::Published);
});

it('writes an audit entry for every admin decision', function () {
    $admin = adminUser();
    $page = StorePage::factory()->create(['status' => 'published']);
    $application = pendingApplication();

    actingAs($admin)->post(route('admin.badges.decide', $application), ['decision' => 'approve']);
    actingAs($admin)->post(route('admin.stores.suspend', $page), ['reason' => 'Fraud']);

    $actions = AdminAuditLog::orderBy('id')->pluck('action')->all();

    expect($actions)->toContain('badge.approved')
        ->toContain('store_page.suspended');

    // Every entry names the admin who made it.
    expect(AdminAuditLog::whereNull('admin_seller_id')->count())->toBe(0);
});

it('treats the audit log as append only', function () {
    $admin = adminUser();
    $application = pendingApplication();

    actingAs($admin)->post(route('admin.badges.decide', $application), ['decision' => 'approve']);

    $entry = AdminAuditLog::sole();

    expect(fn () => $entry->update(['note' => 'rewritten']))
        ->toThrow(LogicException::class, 'immutable');

    expect(fn () => $entry->delete())
        ->toThrow(LogicException::class, 'immutable');
});

it('lets an admin see a transaction belonging to another seller', function () {
    $admin = adminUser();

    [$seller, $page] = sellerWithPage(['status' => 'published']);
    $transaction = Transaction::factory()->paid()->create([
        'seller_id' => $seller->id,
        'payable_type' => (new Order)->getMorphClass(),
        'payable_id' => Order::factory()->create(['seller_id' => $seller->id, 'store_page_id' => $page->id])->id,
        'amount_kobo' => 250000,
    ]);

    // Not the admin's own sale. Reading across tenants is the whole point here.
    actingAs($admin)->get('/admin/transactions')
        ->assertOk()
        ->assertSee($transaction->reference);
});

it('filters the transaction browser by status and reference', function () {
    $admin = adminUser();

    $paid = Transaction::factory()->paid()->create(['reference' => 'MPPAIDONE']);
    Transaction::factory()->create(['status' => 'pending', 'reference' => 'MPPENDINGONE']);

    actingAs($admin)->get('/admin/transactions?status=paid')
        ->assertOk()
        ->assertSee($paid->reference)
        ->assertDontSee('MPPENDINGONE');

    actingAs($admin)->get('/admin/transactions?q=MPPAIDONE')
        ->assertOk()
        ->assertSee($paid->reference);
});

it('keeps the admin panel out of search engines', function () {
    actingAs(adminUser())->get('/admin')->assertOk()->assertSee('noindex, nofollow', false);
});
