<?php

use App\Domains\Admin\Jobs\ExpireKycVerifications;
use App\Domains\Admin\Models\BadgeApplication;
use App\Domains\Admin\Models\BadgeStatus;
use App\Domains\Admin\Notifications\KycStatusChangedNotification;
use App\Domains\Seller\Models\Seller;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\actingAs;

function kycAdmin(): Seller
{
    return Seller::factory()->admin()->create(['email_verified_at' => now()]);
}

function kycApplication(array $attributes = []): BadgeApplication
{
    [$seller, $page] = sellerWithPage();

    return BadgeApplication::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        ...$attributes,
    ]);
}

it('asks the seller for more information without losing the application', function () {
    Notification::fake();

    $admin = kycAdmin();
    $application = kycApplication();

    actingAs($admin)->post(route('admin.badges.decide', $application), [
        'decision' => 'needs_more_info',
        'rejection_reason' => 'The CAC certificate photo is too blurred to read.',
    ])->assertRedirect();

    $application->refresh();

    expect($application->status)->toBe(BadgeStatus::NeedsMoreInfo)
        ->and($application->rejection_reason)->toContain('blurred')
        ->and($application->canResubmit())->toBeTrue();

    Notification::assertSentTo(
        $application->seller,
        KycStatusChangedNotification::class,
        fn (KycStatusChangedNotification $notification) => $notification->status === 'needs_more_info',
    );
});

it('lets a seller send a new document after being asked for more information', function () {
    Storage::fake('local');

    [$seller, $page] = sellerWithPage();
    BadgeApplication::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'status' => BadgeStatus::NeedsMoreInfo,
        'rejection_reason' => 'Upload a clearer copy.',
    ]);

    actingAs($seller)->post(route('badge.submit'), [
        'business_name' => 'Ada Textiles Limited',
        'registered_legal_name' => 'Ada Textiles Limited',
        'registration_number' => 'RC-1234567',
        'document_type' => 'cac',
        'document' => UploadedFile::fake()->create('cac.pdf', 120, 'application/pdf'),
    ])->assertRedirect(route('badge.apply'));

    expect(BadgeApplication::count())->toBe(2)
        ->and(BadgeApplication::latest('id')->first()->status)->toBe(BadgeStatus::Pending);
});

it('removes the tick when a verification is revoked', function () {
    Notification::fake();

    $admin = kycAdmin();
    [$seller, $page] = sellerWithPage();
    $page->forceFill(['verified_at' => now()])->save();
    $seller->forceFill(['kyc_verified_at' => now()])->save();

    $application = BadgeApplication::factory()->approved()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
    ]);

    actingAs($admin)->post(route('admin.badges.decide', $application), [
        'decision' => 'revoke',
        'rejection_reason' => 'The registration number belongs to a closed business.',
    ])->assertRedirect();

    expect($application->fresh()->status)->toBe(BadgeStatus::Revoked)
        ->and($page->fresh()->verified_at)->toBeNull()
        ->and($seller->fresh()->kyc_verified_at)->toBeNull();

    Notification::assertSentTo($seller, KycStatusChangedNotification::class);
});

it('reopens a rejected application for another review', function () {
    Notification::fake();

    $admin = kycAdmin();
    $application = BadgeApplication::factory()->rejected()->create();

    actingAs($admin)->post(route('admin.badges.decide', $application), [
        'decision' => 'reopen',
        'note' => 'Seller supplied the corrected certificate.',
    ])->assertRedirect();

    expect($application->fresh()->status)->toBe(BadgeStatus::Pending)
        ->and($application->fresh()->rejection_reason)->toBeNull()
        ->and($application->fresh()->submitted_at)->not->toBeNull();
});

it('refuses a registration number already used by another seller', function () {
    Storage::fake('local');

    [, $otherPage] = sellerWithPage();
    BadgeApplication::factory()->create([
        'seller_id' => $otherPage->seller_id,
        'store_page_id' => $otherPage->id,
        'registration_number' => 'RC-9988776',
        'status' => BadgeStatus::Approved,
    ]);

    [$seller] = sellerWithPage();

    actingAs($seller)->post(route('badge.submit'), [
        'business_name' => 'Copycat Ventures',
        'registered_legal_name' => 'Copycat Ventures',
        'registration_number' => 'RC-9988776',
        'document_type' => 'cac',
        'document' => UploadedFile::fake()->create('cac.pdf', 120, 'application/pdf'),
    ])->assertSessionHasErrors('registration_number');

    expect(BadgeApplication::where('registered_legal_name', 'Copycat Ventures')->exists())->toBeFalse();
});

it('expires an approved verification when its document date passes', function () {
    Notification::fake();

    $application = kycApplication([
        'status' => BadgeStatus::Approved,
        'document_expires_at' => now()->subDay(),
        'reviewed_at' => now()->subYear(),
    ]);

    $application->storePage->forceFill(['verified_at' => now()])->save();

    (new ExpireKycVerifications)->handle();

    expect($application->fresh()->status)->toBe(BadgeStatus::Expired)
        ->and($application->storePage->fresh()->verified_at)->toBeNull();

    Notification::assertSentTo($application->seller, KycStatusChangedNotification::class);
});

it('encrypts the KYC document at rest', function () {
    Storage::fake('local');

    [$seller] = sellerWithPage();

    actingAs($seller)->post(route('badge.submit'), [
        'business_name' => 'Ada Textiles Limited',
        'registered_legal_name' => 'Ada Textiles Limited',
        'registration_number' => 'RC-1234567',
        'document_type' => 'cac',
        'document' => UploadedFile::fake()->create('cac.pdf', 10, 'application/pdf'),
    ])->assertRedirect(route('badge.apply'));

    $stored = Storage::disk('local')->get(BadgeApplication::sole()->document_path);

    expect($stored)->toStartWith('enc:v1:')
        ->and($stored)->not->toContain('%PDF');
});

it('serves KYC documents only through a short-lived signed URL', function () {
    Storage::fake('local');
    Storage::disk('local')->put('badges/demo/cac.pdf', 'private');

    $admin = kycAdmin();
    $application = kycApplication(['document_path' => 'badges/demo/cac.pdf']);

    actingAs($admin)
        ->get(route('admin.badges.document', $application))
        ->assertForbidden();

    $signed = URL::temporarySignedRoute('admin.badges.document', now()->addMinutes(5), $application);

    actingAs($admin)->get($signed)->assertOk();
});

it('masks the registration number in the admin queue', function () {
    $admin = kycAdmin();
    kycApplication(['registration_number' => 'RC-1234567']);

    actingAs($admin)->get('/admin/badges')
        ->assertOk()
        ->assertSee('RC-••••567')
        ->assertDontSee('RC-1234567');
});
