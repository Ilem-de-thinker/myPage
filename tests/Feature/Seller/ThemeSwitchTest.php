<?php

use App\Domains\Seller\Models\Seller;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
    The seller's own surfaces — dashboard, onboarding wizard, ops panel — used to
    be light-only: every card was a literal bg-white / text-slate-* utility, so
    the one global theme-mode choice flipped the page background and left the
    panels white. They now read the same tokens as the public pages, so the
    stored choice has to reach every one of them.

    These assertions guard that decision, not the palette: that no shell pins
    data-theme, and that each ships both halves of the switch — the pre-paint
    reader and the button that writes the choice.
*/
it('lets every seller shell follow the stored theme choice', function (string $routeName, array $parameters, callable $signIn) {
    $content = actingAs($signIn())->get(route($routeName, $parameters))->assertOk()->getContent();

    expect($content)
        // x-ui.theme-boot reads localStorage and writes data-theme before first paint.
        ->toContain("localStorage.getItem('theme-mode')")
        // x-ui.theme-toggle is the half that writes the choice back.
        ->toContain('Switch between light and dark')
        // A pinned attribute would override the stored choice entirely.
        ->not->toContain('data-theme="light"')
        ->not->toContain('data-theme="dark"');
})->with([
    // Route names rather than resolved URLs: a dataset is evaluated while Pest
    // collects, which is before the application exists to generate a URL.
    'dashboard' => ['dashboard', [], fn () => sellerWithPage()[0]],
    'onboarding' => ['onboarding.show', ['step' => 'business'], fn () => Seller::factory()->create()],
    'admin panel' => ['admin.index', [], fn () => Seller::factory()->admin()->create(['email_verified_at' => now()])],
]);

it('keeps the switch on the public pages too', function () {
    get('/')->assertOk()
        ->assertSee('Switch between light and dark', escape: false);
});
