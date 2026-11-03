<?php

namespace App\Domains\Ai\Http\Controllers;

use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Ai\Models\AiDraft;
use App\Domains\Ai\Services\AiImageService;
use App\Domains\Ai\Support\AiFeatureRegistry;
use App\Domains\Ai\Support\StorefrontDirection;
use App\Domains\Billing\Services\SubscriptionEntitlements;
use App\Domains\Booking\Enums\BillingUnit;
use App\Domains\Booking\Enums\DepositType;
use App\Domains\Booking\Models\Service;
use App\Domains\Catalog\Jobs\ProcessProductImage;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AiDraftController extends Controller
{
    public function __construct(private readonly AiImageService $images) {}

    public function index(Request $request): JsonResponse|View
    {
        $page = $request->user()->storePages()->firstOrFail();
        $query = AiDraft::query()->where('store_page_id', $page->id)->with('parent');

        // The floating "drafts ready" badge polls this endpoint, so JSON callers
        // keep the original latest-30 response. A browser navigation gets the
        // review page instead of a wall of raw JSON.
        if ($request->expectsJson()) {
            return response()->json(['drafts' => $query->latest('created_at')->latest('id')->limit(30)->get()]);
        }

        $status = (string) $request->query('status', 'pending');
        $status = in_array($status, ['pending', 'approved', 'rejected', 'discarded', 'undone', 'all'], true) ? $status : 'pending';

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $drafts = $query->latest('created_at')->latest('id')->paginate(10)->withQueryString();
        $counts = AiDraft::query()
            ->where('store_page_id', $page->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('dashboard.ai.drafts.index', compact('page', 'drafts', 'status', 'counts'));
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
        $undoPage = ($draft->task === 'site_builder' || $draft->task === 'storefront_direction' || ($payload['_logo_colors'] ?? false) || ($payload['presets'] ?? null))
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

        if ($draft->task === 'storefront_direction') {
            // The review modal lets the merchant edit copy before approving, so
            // accept that edited copy and normalise it again on the way in.
            $edited = $request->input('copy');

            if (is_array($edited)) {
                $payload['copy'] = $edited;
            }

            $direction = StorefrontDirection::sanitize($page, $payload);
            StorefrontDirection::apply($page, $direction);

            if (is_array($edited)) {
                $draft->update(['payload' => [...$draft->payload, 'copy' => $direction['copy']]]);
            }
        }

        if ($draft->payload['presets'] ?? null) {
            $choice = $request->validate(['preset' => ['required', 'integer', 'min:0', 'max:2']])['preset'];
            $preset = $draft->payload['presets'][$choice] ?? null;

            $available = collect(config('themes.presets', []))
                ->filter(fn (array $definition): bool => in_array($page->theme->value, $definition['business_types'] ?? [], true))
                ->keys()
                ->values()
                ->all();
            $fallback = $available[0] ?? array_key_first(config('themes.presets', []));

            // Drafts generated before the registry fix can carry a theme enum
            // ("store") instead of a preset key. Keep the suggested colours but
            // apply a compatible direction rather than rejecting the whole draft.
            if (is_array($preset) && ! in_array($preset['theme_key'] ?? null, $available, true)) {
                $preset['theme_key'] = $fallback;
            }

            validator($preset, ['theme_key' => ['required', 'string', Rule::in($available)], 'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/']])->validate();
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
        $this->images->deleteDraftMedia($draft);
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
        $this->images->deleteDraftMedia($draft);
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
        $media = array_values(array_filter((array) ($draft->payload['_media_relative_paths'] ?? []), 'is_string'));
        $input = ['text' => trim(($draft->payload['name'] ?? '')."\n".($draft->payload['description'] ?? ''))];
        if ($media !== []) {
            $input['media_relative_paths'] = $media;
        }

        $result = $ai->run($task, $input, $page);
        $payload = [...(is_array($result) ? $result : ['text' => $result]), '_feature' => $feature];
        if ($media !== []) {
            $payload['_media_relative_paths'] = $media;
        }

        $previous = $draft->parent_id ?: $draft->id;
        $newDraft = AiDraft::create(['seller_id' => $request->user()->id, 'store_page_id' => $page->id, 'ai_task_id' => $draft->ai_task_id, 'parent_id' => $previous, 'task' => $task, 'version' => $draft->version + 1, 'status' => 'pending', 'payload' => $payload]);

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

            if (isset($snapshot['blocks']) && is_array($snapshot['blocks'])) {
                $page->blocks()->delete();

                foreach ($snapshot['blocks'] as $row) {
                    if (! is_array($row) || ! isset($row['page'], $row['key'])) {
                        continue;
                    }

                    $page->blocks()->create([
                        'page' => $row['page'],
                        'key' => $row['key'],
                        'content' => is_array($row['content'] ?? null) ? $row['content'] : [],
                    ]);
                }
            }
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
            $categoryName = trim((string) ($draft->payload['category'] ?? ''));
            $categoryId = null;
            if ($categoryName !== '') {
                $category = Category::firstOrCreate(
                    ['seller_id' => $request->user()->id, 'store_page_id' => $page->id, 'name' => $categoryName],
                    ['position' => $page->categories()->count()]
                );
                $categoryId = $category->id;
            }
            $priceRaw = $draft->payload['price'] ?? $draft->payload['price_hint'] ?? 0;
            $priceKobo = Money::toKobo($priceRaw);

            $relativePaths = array_values(array_filter((array) ($draft->payload['_media_relative_paths'] ?? [])));
            $created = DB::transaction(function () use ($feature, $page, $request, $draft, $name, $description, $categoryId, $priceKobo): ?Model {
                if ($feature === 'products') {
                    return Product::create(['seller_id' => $request->user()->id, 'store_page_id' => $page->id, 'category_id' => $categoryId, 'name' => $name, 'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)), 'description' => $description, 'ai_metadata' => $draft->payload, 'price_kobo' => $priceKobo, 'sku' => 'AI-'.strtoupper(Str::random(8)), 'status' => 'draft', 'stock_qty' => 0]);
                } elseif ($feature === 'services') {
                    return Service::create(['seller_id' => $request->user()->id, 'store_page_id' => $page->id, 'name' => $name, 'description' => $description, 'ai_metadata' => $draft->payload, 'duration_min' => $this->durationMinutes($draft->payload['duration'] ?? null), 'price_kobo' => $priceKobo, 'deposit_type' => DepositType::None, 'deposit_value' => 0, 'billing_unit' => BillingUnit::PerProject, 'is_active' => false, 'position' => $page->services()->count()]);
                }

                return null;
            });
            if ($created instanceof Product && $relativePaths !== []) {
                foreach (array_values($relativePaths) as $i => $relativePath) {
                    if (! is_string($relativePath)) {
                        continue;
                    }

                    $image = $this->images->attachToProduct($created, $relativePath, $i);
                    if ($image) {
                        ProcessProductImage::dispatch($image->getKey());
                    }
                }
            }
            if ($created instanceof Service && $relativePaths !== []) {
                $first = $relativePaths[0] ?? null;
                if (is_string($first)) {
                    $target = $this->images->attachToService($created, $first);
                    if ($target !== null) {
                        $created->update(['image_path' => $target]);
                    }
                }
            }
            $draft->update(['applied_type' => $created?->getMorphClass() ?? null, 'applied_id' => $created?->getKey()]);
        }
        $draft->update(['status' => 'approved', 'approved_at' => now(), 'reviewed_by' => $request->user()->id]);
    }

    private function durationMinutes(mixed $value, int $default = 60): int
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return $default;
        }

        $text = mb_strtolower(trim((string) $value));
        if (! preg_match('/(\d+(?:\.\d+)?)/', $text, $matches)) {
            return $default;
        }

        $minutes = (int) round((float) $matches[1]);
        if (str_contains($text, 'hour') || str_contains($text, 'hr')) {
            $minutes *= 60;
        }

        return max(15, min(10080, $minutes));
    }

    /**
     * @return array{theme: string, brand_color: string, tagline: ?string, bio: ?string, settings: array<string, mixed>, blocks: list<array{page: string, key: string, content: array<string, mixed>}>}
     */
    private function pageSnapshot(StorePage $page): array
    {
        return [
            'theme' => $page->theme->value,
            'brand_color' => $page->brand_color,
            'tagline' => $page->tagline,
            'bio' => $page->bio,
            'settings' => $page->settings ?? [],
            'blocks' => $page->blocks()->get(['page', 'key', 'content'])
                ->map(fn ($block): array => [
                    'page' => $block->page,
                    'key' => $block->key,
                    'content' => $block->content ?? [],
                ])
                ->values()
                ->all(),
        ];
    }
}
