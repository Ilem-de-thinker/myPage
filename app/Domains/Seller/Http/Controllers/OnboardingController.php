<?php

namespace App\Domains\Seller\Http\Controllers;

use App\Domains\Admin\Models\PlatformSetting;
use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Billing\Services\SubscriptionEntitlements;
use App\Domains\Booking\Enums\BillingUnit;
use App\Domains\Booking\Enums\DepositType;
use App\Domains\Booking\Models\Service;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Actions\CreateStorePage;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Rules\NigerianPhone;
use App\Shared\Rules\SafeSlug;
use App\Shared\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    private const STEPS = ['business', 'theme', 'brand', 'review'];

    public function show(Request $request, string $step): View
    {
        abort_unless(in_array($step, self::STEPS, true), 404);

        $data = $request->session()->get('onboarding', []);

        return view("onboarding.{$step}", [
            'data' => $data,
            // The template is chosen on step two, so until then the copy falls
            // back to the store wording rather than guessing at a service page.
            'theme' => Theme::tryFrom($data['theme'] ?? '') ?? Theme::Store,
            'availableThemes' => collect(Theme::cases())
                ->filter(fn (Theme $theme): bool => (bool) PlatformSetting::value("theme.{$theme->value}.enabled", true))
                ->values(),
        ]);
    }

    public function store(Request $request, string $step, CreateStorePage $createStorePage, SubscriptionEntitlements $entitlements): RedirectResponse
    {
        abort_unless(in_array($step, self::STEPS, true), 404);

        $data = $request->session()->get('onboarding', []);

        switch ($step) {
            case 'business':
                $validated = $request->validate([
                    'name' => ['required', 'string', 'max:120'],
                    'slug' => ['required', new SafeSlug, Rule::unique('store_pages', 'slug')],
                ]);
                $data = array_merge($data, $validated);
                break;

            case 'theme':
                $availableThemes = collect(Theme::cases())
                    ->filter(fn (Theme $theme): bool => (bool) PlatformSetting::value("theme.{$theme->value}.enabled", true))
                    ->map(fn (Theme $theme): string => $theme->value)
                    ->all();
                $validated = $request->validate(['theme' => ['required', Rule::in($availableThemes)]]);
                $data = array_merge($data, $validated);
                break;

            case 'brand':
                $validated = $request->validate([
                    'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                    'whatsapp' => ['required', new NigerianPhone],
                    'city' => ['required', 'string', 'max:120'],
                    'state' => ['required', 'string', 'max:120'],
                    'bio' => ['nullable', 'string', 'max:1000'],
                ]);
                $validated['whatsapp'] = PhoneNumber::normalize($validated['whatsapp']);
                $data = array_merge($data, $validated);
                break;

            case 'review':
                abort_if(empty($data['name']) || empty($data['slug']) || empty($data['theme']), 422, 'Complete the previous steps first.');

                // A double-click must not try to create a second page. The
                // unique slug would reject it, but as a 500 after the first
                // page is already live; answer idempotently instead.
                $existing = $request->user()->storePages()->first();

                if ($existing !== null) {
                    $request->session()->forget('onboarding');

                    return redirect()->route('dashboard')->with('status', "Your page {$existing->name} is already set up.");
                }

                if (! empty($data['ai_result'])) {
                    $keep = $request->input('starter_keep', array_keys($data['ai_result']['listings'] ?? []));
                    $data['ai_result']['listings'] = collect($data['ai_result']['listings'] ?? [])->only($keep)->values()->all();

                    $keepCategories = $request->input('starter_categories', $data['ai_result']['categories'] ?? []);
                    $data['ai_result']['categories'] = collect($data['ai_result']['categories'] ?? [])
                        ->filter(fn ($name): bool => is_string($name) && filled($name) && in_array($name, $keepCategories, true))
                        ->values()
                        ->all();
                }

                $page = $createStorePage->handle($request->user(), $data);
                $this->createStarterCategories($page, $data['ai_result']['categories'] ?? []);
                $listings = $data['ai_result']['listings'] ?? [];
                $limit = $entitlements->listingLimit($page);
                $this->createStarterDrafts($page, $limit === null ? $listings : array_slice($listings, 0, $limit));
                $request->session()->forget('onboarding');

                return redirect()->route('dashboard')->with('status', "Your page {$page->name} is ready as a draft.");
        }

        $request->session()->put('onboarding', $data);

        return redirect()->route('onboarding.show', self::STEPS[array_search($step, self::STEPS, true) + 1]);
    }

    public function skip(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['onboarding_skipped_at' => now()])->save();
        $request->session()->forget('onboarding');

        return redirect()->route('dashboard')->with('status', 'You can finish setting up your page any time.');
    }

    public function aiCreate(Request $request, AiManager $ai): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['required', 'string', 'max:3000']]);

        try {
            $result = $ai->run('site_builder', [
                'name' => $data['name'],
                'description' => $data['description'],
                'theme_registry' => collect(Theme::cases())
                    ->map(fn (Theme $theme): array => ['key' => $theme->value, 'label' => $theme->label()])
                    ->all(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return back()
                ->withErrors(['description' => 'Mercho is unavailable right now. Fill in your details manually and continue.'])
                ->withInput();
        }
        $businessType = $result['business_type'] ?? 'store';
        $theme = $businessType === 'service' ? Theme::Service : Theme::Store;
        $onboarding = array_merge($request->session()->get('onboarding', []), [
            'name' => $data['name'], 'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'theme' => $theme->value, 'business_type' => $businessType,
            'brand_color' => $result['brand_color'] ?? '#16a34a', 'accent_color' => $result['accent_color'] ?? null,
            'bio' => $result['about'] ?? null, 'ai_result' => $result, 'ai_enabled' => true,
        ]);
        $request->session()->put('onboarding', $onboarding);

        return redirect()->route('onboarding.show', 'review');
    }

    /** @param list<string> $categories */
    private function createStarterCategories(StorePage $page, array $categories): void
    {
        foreach ($categories as $name) {
            if (filled($name)) {
                Category::firstOrCreate(['store_page_id' => $page->id, 'name' => $name]);
            }
        }
    }

    /** @param list<array<string, mixed>> $listings */
    private function createStarterDrafts(StorePage $page, array $listings): void
    {
        if ($listings === []) {
            return;
        }

        DB::transaction(function () use ($page, $listings): void {
            foreach ($listings as $listing) {
                $category = filled($listing['category'] ?? null) ? Category::firstOrCreate(['store_page_id' => $page->id, 'name' => $listing['category']]) : null;
                $attributes = ['seller_id' => $page->seller_id, 'store_page_id' => $page->id, 'name' => $listing['name'] ?? 'Untitled draft', 'description' => $listing['description'] ?? '', 'ai_metadata' => $listing, 'status' => 'draft'];
                if ($page->businessType() === 'service') {
                    Service::create($attributes + ['slug' => Service::generateSlug($attributes['name']), 'duration_min' => 60, 'price_kobo' => 0, 'deposit_type' => DepositType::None, 'deposit_value' => 0, 'billing_unit' => BillingUnit::PerProject, 'is_active' => false, 'position' => $page->services()->count()]);
                } else {
                    Product::create($attributes + ['category_id' => $category?->id, 'slug' => Str::slug($attributes['name']).'-'.Str::lower(Str::random(4)), 'price_kobo' => 0, 'sku' => 'AI-'.strtoupper(Str::random(8)), 'stock_qty' => 0]);
                }
            }
        });
    }
}
