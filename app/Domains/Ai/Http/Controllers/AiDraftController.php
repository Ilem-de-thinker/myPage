<?php

namespace App\Domains\Ai\Http\Controllers;

use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Ai\Models\AiDraft;
use App\Domains\Ai\Support\AiFeatureRegistry;
use App\Domains\Billing\Services\SubscriptionEntitlements;
use App\Domains\Booking\Enums\BillingUnit;
use App\Domains\Booking\Enums\DepositType;
use App\Domains\Booking\Models\Service;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AiDraftController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();

        return response()->json(['drafts' => AiDraft::query()->where('store_page_id', $page->id)->with('parent')->latest()->limit(30)->get()]);
    }

    public function versions(Request $request, int $draft): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $current = AiDraft::query()->where('store_page_id', $page->id)->findOrFail($draft);
        $rootId = $current->parent_id ?: $current->id;
        $versions = AiDraft::query()->where('store_page_id', $page->id)->where(fn ($query) => $query->whereKey($rootId)->orWhere('parent_id', $rootId))->latest('version')->get();

        return response()->json(['versions' => $versions]);
    }

    public function approve(Request $request, int $draft): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $draft = AiDraft::query()->where('store_page_id', $page->id)->where('status', 'pending')->findOrFail($draft);
        $payload = $draft->payload;
        $undoPage = ($draft->task === 'site_builder' || ($payload['_logo_colors'] ?? false) || ($payload['presets'] ?? null))
            ? $this->pageSnapshot($page)
            : null;

        if ($draft->task === 'site_builder') {
            $validated = validator($payload, [
                'theme_key' => ['required', 'string', Rule::in(array_keys(config('themes.presets', [])))],
                'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'tagline' => ['nullable', 'string', 'max:180'],
                'about' => ['nullable', 'string', 'max:5000'],
            ])->validate();
            $settings = $page->settings ?? [];
            $settings['accent_color'] = $validated['accent_color'];
            $settings['theme_preset'] = $validated['theme_key'];
            $settings['business_type'] = $payload['business_type'] ?? $page->businessType();
            $settings['ai_draft_id'] = $draft->id;
            $theme = ($payload['business_type'] ?? $page->businessType()) === 'service' ? Theme::Service : Theme::Store;
            $page->forceFill(['theme' => $theme, 'brand_color' => $validated['brand_color'], 'tagline' => $validated['tagline'] ?? $page->tagline, 'bio' => $validated['about'] ?? $page->bio, 'settings' => $settings])->save();
        }

        if ($payload['_logo_colors'] ?? false) {
            $validated = validator($payload, [
                'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            ])->validate();
            $settings = $page->settings ?? [];
            $settings['accent_color'] = $validated['accent_color'];
            $settings['ai_logo_draft_id'] = $draft->id;
            $page->forceFill(['brand_color' => $validated['brand_color'], 'settings' => $settings])->save();
            $draft->update(['status' => 'approved', 'approved_at' => now(), 'reviewed_by' => $request->user()->id]);
        }

        if ($draft->payload['presets'] ?? null) {
            $choice = $request->validate(['preset' => ['required', 'integer', 'min:0', 'max:2']])['preset'];
            $preset = $draft->payload['presets'][$choice] ?? null;
            validator($preset, ['theme_key' => ['required', 'string', Rule::in(array_keys(config('themes.presets', [])))], 'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/']])->validate();
            $settings = $page->settings ?? [];
            $settings['theme_preset'] = $preset['theme_key'];
            $settings['accent_color'] = $preset['accent_color'];
            $page->forceFill(['brand_color' => $preset['brand_color'], 'settings' => $settings])->save();
        }

        if ($draft->payload['_feature'] ?? null) {
            $this->applyDraft($request, $page, $draft);
        } else {
            $draft->update(['status' => 'approved', 'approved_at' => now(), 'reviewed_by' => $request->user()->id]);
        }

        if ($undoPage !== null) {
            $draft->update(['payload' => [...$draft->fresh()->payload, '_undo_page' => $undoPage]]);
        }

        return response()->json(['draft' => $draft->fresh(), 'message' => 'Draft approved. Review the storefront before publishing.']);
    }

    public function reject(Request $request, int $draft): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $draft = AiDraft::query()->where('store_page_id', $page->id)->where('status', 'pending')->findOrFail($draft);
        $draft->update(['status' => 'rejected', 'rejected_at' => now(), 'reviewed_by' => $request->user()->id]);

        return response()->json(['draft' => $draft]);
    }

    public function update(Request $request, int $draft): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $draft = AiDraft::query()->where('store_page_id', $page->id)->where('status', 'pending')->findOrFail($draft);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:180'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'category' => ['sometimes', 'nullable', 'string', 'max:120'],
            'tags' => ['sometimes', 'array', 'max:30'],
            'tags.*' => ['string', 'max:80'],
            'variants' => ['sometimes', 'array', 'max:30'],
            'variants.*' => ['string', 'max:120'],
            'price_hint' => ['sometimes', 'nullable', 'string', 'max:120'],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:70'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:180'],
            'packages' => ['sometimes', 'array', 'max:12'],
            'packages.*' => ['string', 'max:500'],
            'duration' => ['sometimes', 'nullable', 'string', 'max:120'],
            'what_to_expect' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'faq' => ['sometimes', 'array', 'max:12'],
            'faq.*' => ['string', 'max:500'],
            'booking_questions' => ['sometimes', 'array', 'max:20'],
            'booking_questions.*' => ['string', 'max:300'],
        ]);
        $draft->update(['payload' => array_merge($draft->payload, $data)]);

        return response()->json(['draft' => $draft->fresh()]);
    }

    public function discard(Request $request, int $draft): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $draft = AiDraft::query()->where('store_page_id', $page->id)->where('status', 'pending')->findOrFail($draft);
        $draft->update(['status' => 'discarded', 'rejected_at' => now(), 'reviewed_by' => $request->user()->id]);

        return response()->json(['draft' => $draft]);
    }

    public function regenerate(Request $request, int $draft, AiManager $ai): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $draft = AiDraft::query()->where('store_page_id', $page->id)->where('status', 'pending')->findOrFail($draft);
        $feature = $draft->payload['_feature'] ?? null;
        abort_unless(is_string($feature), 422, 'This draft cannot be regenerated here.');
        AiFeatureRegistry::authorize($page, $feature);
        $task = AiFeatureRegistry::get($feature)['task'];
        $result = $ai->run($task, ['text' => trim(($draft->payload['name'] ?? '')."\n".($draft->payload['description'] ?? ''))], $page);
        $previous = $draft->parent_id ?: $draft->id;
        $newDraft = AiDraft::create(['seller_id' => $request->user()->id, 'store_page_id' => $page->id, 'parent_id' => $previous, 'task' => $task, 'version' => $draft->version + 1, 'status' => 'pending', 'payload' => [...(is_array($result) ? $result : ['text' => $result]), '_feature' => $feature]]);

        return response()->json(['draft' => $newDraft]);
    }

    public function confirmSelected(Request $request): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $ids = $request->validate(['draft_ids' => ['required', 'array', 'min:1', 'max:50'], 'draft_ids.*' => ['integer']])['draft_ids'];
        $drafts = AiDraft::query()->where('store_page_id', $page->id)->where('status', 'pending')->whereIn('id', $ids)->get();

        foreach ($drafts as $draft) {
            $this->applyDraft($request, $page, $draft);
        }

        return response()->json(['confirmed' => $drafts->pluck('id')->values()]);
    }

    public function undo(Request $request, int $draft): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $draft = AiDraft::query()->where('store_page_id', $page->id)->where('status', 'approved')->findOrFail($draft);
        if ($draft->applied_type && class_exists($draft->applied_type)) {
            $model = $draft->applied_type::query()->whereKey($draft->applied_id)->where('store_page_id', $page->id)->first();
            $model?->delete();
        }

        $snapshot = $draft->payload['_undo_page'] ?? null;
        if (is_array($snapshot)) {
            $page->forceFill([
                'theme' => $snapshot['theme'] ?? $page->theme,
                'brand_color' => $snapshot['brand_color'] ?? $page->brand_color,
                'tagline' => $snapshot['tagline'] ?? $page->tagline,
                'bio' => $snapshot['bio'] ?? $page->bio,
                'settings' => $snapshot['settings'] ?? $page->settings,
            ])->save();
        }

        $draft->update(['status' => 'undone', 'reviewed_by' => $request->user()->id]);

        return response()->json(['draft' => $draft->fresh(), 'message' => 'The Mercho change was undone.']);
    }

    private function applyDraft(Request $request, StorePage $page, AiDraft $draft): void
    {
        $feature = $draft->payload['_feature'] ?? null;
        if ($feature) {
            AiFeatureRegistry::authorize($page, $feature);
            if (in_array($feature, ['products', 'services'], true)) {
                app(SubscriptionEntitlements::class)->assertCanCreateListing($page);
            }
            $name = trim((string) ($draft->payload['name'] ?? '')) ?: 'Untitled draft';
            $description = (string) ($draft->payload['description'] ?? '');
            $created = DB::transaction(function () use ($feature, $page, $request, $draft, $name, $description): ?Model {
                if ($feature === 'products') {
                    return Product::create(['seller_id' => $request->user()->id, 'store_page_id' => $page->id, 'name' => $name, 'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)), 'description' => $description, 'ai_metadata' => $draft->payload, 'price_kobo' => 0, 'sku' => 'AI-'.strtoupper(Str::random(8)), 'status' => 'draft', 'stock_qty' => 0]);
                } elseif ($feature === 'services') {
                    return Service::create(['seller_id' => $request->user()->id, 'store_page_id' => $page->id, 'name' => $name, 'description' => $description, 'ai_metadata' => $draft->payload, 'duration_min' => 60, 'price_kobo' => 0, 'deposit_type' => DepositType::None, 'deposit_value' => 0, 'billing_unit' => BillingUnit::PerProject, 'is_active' => false, 'position' => $page->services()->count()]);
                }

                return null;
            });
            $draft->update(['applied_type' => $created?->getMorphClass() ?? null, 'applied_id' => $created?->getKey()]);
        }
        $draft->update(['status' => 'approved', 'approved_at' => now(), 'reviewed_by' => $request->user()->id]);
    }

    /**
     * @return array{theme: string, brand_color: string, tagline: ?string, bio: ?string, settings: array<string, mixed>}
     */
    private function pageSnapshot(StorePage $page): array
    {
        return [
            'theme' => $page->theme->value,
            'brand_color' => $page->brand_color,
            'tagline' => $page->tagline,
            'bio' => $page->bio,
            'settings' => $page->settings ?? [],
        ];
    }
}
