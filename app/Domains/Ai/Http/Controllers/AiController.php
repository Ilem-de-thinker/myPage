<?php

namespace App\Domains\Ai\Http\Controllers;

use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Ai\Models\AiDraft;
use App\Domains\Ai\Models\AiOperation;
use App\Domains\Ai\Models\AiTask;
use App\Domains\Ai\Services\AiCreditService;
use App\Domains\Ai\Services\AiImageService;
use App\Domains\Ai\Services\AiLimits;
use App\Domains\Ai\Services\AiSettings;
use App\Domains\Ai\Services\AiUsageReservations;
use App\Domains\Ai\Services\AiUsageSummary;
use App\Domains\Ai\Support\AiFeatureRegistry;
use App\Domains\Ai\Support\StorefrontDirection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Throwable;

class AiController extends Controller
{
    public function __construct(
        private readonly AiSettings $settings,
        private readonly AiLimits $limits,
    ) {}

    public function contextual(Request $request, AiManager $ai): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $this->limits->throttle($page);
        $this->limits->assertCanQueue($page);
        $data = $request->validate([
            'feature' => ['required', 'string'],
            'input' => ['required', 'array', 'max:30'],
        ]);
        abort_if(strlen((string) json_encode($data['input'])) > 65536, 422, 'That input is too large.');
        $definition = AiFeatureRegistry::get($data['feature']);
        AiFeatureRegistry::authorize($page, $data['feature']);
        abort_unless((bool) $this->settings->enabled() && (bool) ($page->settings['ai_enabled'] ?? true), 503, 'Mercho is disabled for this store.');
        if ($data['feature'] === 'content' && ($data['input']['mode'] ?? null) === 'translate') {
            validator($data['input'], ['text' => ['required', 'string', 'max:10000'], 'language' => ['required', 'in:English,Nigerian Pidgin,Yoruba,Igbo,Hausa']])->validate();
        }
        $task = $data['feature'] === 'content' && ($data['input']['mode'] ?? null) === 'translate'
            ? 'translate'
            : $definition['task'];

        $input = $data['input'];

        if ($task === 'storefront_direction') {
            // The client only knows which tab was open. Build the real business
            // context and the registries here so the model cannot be steered by
            // a crafted request into inventing a theme, section, or block.
            $input = array_merge($input, StorefrontDirection::registry($page));
            $input['text'] = StorefrontDirection::description($page);
        }

        $result = $ai->run($task, $input, $page);

        if ($task === 'storefront_direction' && is_array($result)) {
            $result = StorefrontDirection::sanitize($page, $result);
        }

        if ($data['feature'] === 'services' && is_array($result)) {
            $result['name'] = $result['name'] ?? $result['title'] ?? 'Service draft';
        }
        $payload = is_array($result) ? $result : ['text' => $result];
        $previous = AiDraft::query()->where('store_page_id', $page->id)->where('task', $task)->latest('id')->first();
        $draft = AiDraft::create(['seller_id' => $request->user()->id, 'store_page_id' => $page->id, 'parent_id' => $previous?->parent_id ?: $previous?->id, 'task' => $task, 'version' => ($previous?->version ?? 0) + 1, 'status' => 'pending', 'payload' => $payload]);

        return response()->json(['feature' => $data['feature'], 'result' => $result, 'draft_id' => $draft->id, 'approval_required' => true]);
    }

    public function logoColors(Request $request): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $this->limits->throttle($page);
        AiFeatureRegistry::authorize($page, 'appearance');
        abort_unless((bool) $this->settings->enabled() && (bool) ($page->settings['ai_enabled'] ?? true), 503, 'Mercho is disabled for this store.');
        abort_unless(function_exists('imagecreatefromjpeg'), 503, 'Logo color extraction is unavailable.');
        $data = $request->validate(['logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']]);
        [$sourceWidth, $sourceHeight] = @getimagesize($data['logo']->getRealPath()) ?: [0, 0];
        abort_if($sourceWidth * $sourceHeight === 0 || $sourceWidth > 4096 || $sourceHeight > 4096, 422, 'That logo is too large to process. Please use one under 4096 pixels.');

        $image = match ($data['logo']->getMimeType()) {
            'image/png' => imagecreatefrompng($data['logo']->getRealPath()),
            'image/webp' => imagecreatefromwebp($data['logo']->getRealPath()),
            default => imagecreatefromjpeg($data['logo']->getRealPath()),
        };
        $colors = [];
        for ($x = 0; $x < imagesx($image); $x += max(1, intdiv(imagesx($image), 24))) {
            for ($y = 0; $y < imagesy($image); $y += max(1, intdiv(imagesy($image), 24))) {
                $rgb = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                if (($rgb['alpha'] ?? 0) < 100) {
                    $colors[] = sprintf('#%02x%02x%02x', $rgb['red'], $rgb['green'], $rgb['blue']);
                }
            }
        }
        imagedestroy($image);

        $brandColor = $colors[0] ?? $page->brand_color;
        $accentColor = $colors[1] ?? ($colors[0] ?? ($page->settings['accent_color'] ?? $page->brand_color));
        $previous = AiDraft::query()->where('store_page_id', $page->id)->where('task', 'theme_and_color_preset')->latest('id')->first();
        $draft = AiDraft::create([
            'seller_id' => $request->user()->id,
            'store_page_id' => $page->id,
            'parent_id' => $previous?->parent_id ?: $previous?->id,
            'task' => 'theme_and_color_preset',
            'version' => ($previous?->version ?? 0) + 1,
            'status' => 'pending',
            'payload' => ['_logo_colors' => true, 'brand_color' => $brandColor, 'accent_color' => $accentColor],
        ]);

        return response()->json(['brand_color' => $brandColor, 'accent_color' => $accentColor, 'draft_id' => $draft->id, 'approval_required' => true]);
    }

    public function suggestPhotoGroups(Request $request, AiManager $ai, AiImageService $images): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $this->limits->throttle($page);
        $imageLimit = $this->limits->imageLimit($page);
        $data = $request->validate([
            'feature' => ['required', 'in:products,services'],
            'photos' => ['required', 'array', 'min:2', 'max:'.$imageLimit],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'photos.max' => __('ai.image_limit', ['limit' => $imageLimit]),
        ]);
        $this->limits->assertImageCount($page, count($data['photos']));
        AiFeatureRegistry::authorize($page, $data['feature']);

        $relativePaths = [];
        try {
            foreach ($data['photos'] as $photo) {
                $relativePaths[] = $images->stage($photo, $page, 'grouping');
            }

            $result = $ai->run('listing_photo_grouping', [
                'feature' => $data['feature'],
                'photo_count' => count($relativePaths),
                'media_relative_paths' => $relativePaths,
            ], $page);
        } finally {
            $images->deleteMany($relativePaths);
        }

        $groups = collect($result['groups'] ?? [])->map(fn (array $group): array => collect($group)->map(fn (string|int $index): int => (int) $index)->filter(fn (int $index): bool => $index >= 1 && $index <= count($data['photos']))->values()->all())->filter()->values()->all();

        if ($groups === []) {
            throw ValidationException::withMessages(['photos' => 'Mercho could not suggest photo groups.']);
        }

        return response()->json(['groups' => $groups, 'reason' => $result['reason'] ?? null]);
    }

    public function usage(Request $request, AiUsageSummary $usage): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $this->limits->throttle($page);

        return response()->json($usage->forPage($page));
    }

    public function estimate(Request $request, AiCreditService $credits): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $this->limits->throttle($page);
        $data = $request->validate(['feature' => ['required', 'string'], 'item_count' => ['required', 'integer', 'min:1', 'max:50']]);
        $this->limits->assertWithinTaskCap($data['item_count']);
        AiFeatureRegistry::authorize($page, $data['feature']);
        $definition = AiFeatureRegistry::get($data['feature']);

        return response()->json($credits->estimate($page, $data['item_count'], $definition['task']));
    }

    public function batch(Request $request, AiCreditService $credits): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $data = $request->validate([
            'feature' => ['required', 'in:products,services'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.text' => ['nullable', 'string', 'max:2000'],
            'items.*.group' => ['nullable', 'integer', 'min:0'],
        ]);
        AiFeatureRegistry::authorize($page, $data['feature']);
        $this->limits->throttle($page);
        $this->limits->assertWithinTaskCap(count($data['items']));
        $this->limits->assertCanQueue($page, 1);

        $task = $data['feature'] === 'products' ? 'listing_copy' : 'service_listing';
        $estimate = $credits->estimate($page, count($data['items']), $task);
        $slots = $this->limits->availableSlots($page);
        abort_if($slots < 1, 429, 'This store already has the maximum number of Mercho tasks running. Wait for one to finish and try again.');
        $allowed = min($estimate['allowed'], $slots, $this->limits->maxBatchItems());
        abort_if($allowed < 1, 429, 'This store has reached its monthly Mercho limit for listing copy.');

        $aiTask = AiTask::create([
            'seller_id' => $request->user()->id,
            'store_page_id' => $page->id,
            'type' => $data['feature'] === 'products' ? 'products' : 'services',
            'status' => 'queued',
            'total' => $allowed,
        ]);

        $operations = [];
        $reservationFailed = false;

        // A concurrent request can consume the remaining credits between the
        // estimate above and this loop. The reservation is the authoritative
        // gate, so a batch that loses the race keeps what it could reserve
        // instead of erroring with work already queued.
        foreach (array_slice($data['items'], 0, $allowed) as $index => $item) {
            $operation = AiOperation::create([
                'seller_id' => $request->user()->id,
                'store_page_id' => $page->id,
                'ai_task_id' => $aiTask->id,
                'task' => $task,
                'status' => 'queued',
                'input' => ['feature' => $data['feature'], 'batch_index' => $index, ...$item],
            ]);

            try {
                app(AiUsageReservations::class)->reserveAndDispatch($operation);
            } catch (Throwable $exception) {
                $reservationFailed = true;
                break;
            }

            $operations[] = ['id' => $operation->id, 'status' => $operation->status, 'index' => $index];
        }

        $reserved = count($operations);
        $aiTask->update(['status' => $reserved > 0 ? 'processing' : 'failed', 'total' => $reserved, 'started_at' => $reserved > 0 ? now() : null]);

        $limitReached = $allowed < count($data['items']) || $reservationFailed;

        return response()->json([
            'operations' => $operations,
            'task_id' => $aiTask->id,
            'estimate' => $estimate,
            'limit_reached' => $limitReached,
            'upgrade_required' => $limitReached,
            'active_limit_reached' => $limitReached,
        ], 202);
    }

    public function run(Request $request, AiManager $ai): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $this->limits->throttle($page);
        $this->limits->assertCanQueue($page);
        abort_unless((bool) $this->settings->enabled() && (bool) ($page->settings['ai_enabled'] ?? true), 503, 'Mercho is disabled for this store.');
        $data = $request->validate(['task' => ['required', 'string', 'in:listing_copy,service_listing,copy_rewrite'], 'feature' => ['required', 'string'], 'input' => ['required', 'array', 'max:30']]);
        abort_if(strlen((string) json_encode($data['input'])) > 65536, 422, 'That input is too large.');
        $definition = AiFeatureRegistry::get($data['feature']);
        abort_unless($definition['task'] === $data['task'], 422, 'This Mercho task does not belong to this page.');
        AiFeatureRegistry::authorize($page, $data['feature']);
        $result = $ai->run($data['task'], $data['input'], $page);
        if ($data['feature'] === 'services' && is_array($result)) {
            $result['name'] = $result['name'] ?? $result['title'] ?? 'Service draft';
        }
        $previous = AiDraft::query()->where('store_page_id', $page->id)->where('task', $data['task'])->latest('id')->first();
        $root = $previous?->parent_id ?: $previous?->id;
        $draft = AiDraft::create(['seller_id' => $request->user()->id, 'store_page_id' => $page->id, 'parent_id' => $root, 'task' => $data['task'], 'version' => ($previous?->version ?? 0) + 1, 'status' => 'pending', 'payload' => is_array($result) ? $result : ['text' => $result]]);

        return response()->json(['result' => $result, 'draft_id' => $draft->id, 'approval_required' => true]);
    }

    public function queue(Request $request): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $this->limits->throttle($page);
        $this->limits->assertCanQueue($page);
        $data = $request->validate([
            'task' => ['required', 'string', 'in:site_builder,listing_from_photo,insights_digest'],
            'feature' => ['required', 'string'],
            'input' => ['required', 'array', 'max:30'],
        ]);
        abort_if(strlen((string) json_encode($data['input'])) > 65536, 422, 'That input is too large.');
        $features = [
            'site_builder' => 'store-creation',
            'listing_from_photo' => 'products',
            'insights_digest' => 'analytics',
        ];
        abort_unless(($features[$data['task']] ?? null) === $data['feature'], 422, 'This Mercho task does not belong to this feature.');
        AiFeatureRegistry::authorize($page, $data['feature']);
        $estimate = app(AiCreditService::class)->estimate($page, 1, $data['task']);
        abort_if($estimate['allowed'] < 1, 429, 'This store has reached its monthly Mercho limit for '.AiSettings::taskLabel($data['task']).'.');
        $operation = AiOperation::create(['seller_id' => $request->user()->id, 'store_page_id' => $page->id, 'task' => $data['task'], 'status' => 'queued', 'input' => ['feature' => $data['feature'], ...$data['input']]]);
        app(AiUsageReservations::class)->reserveAndDispatch($operation);

        return response()->json(['operation_id' => $operation->id, 'status' => $operation->status, 'estimate' => $estimate], 202);
    }

    public function photoListing(Request $request, AiCreditService $credits, AiImageService $images): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $imageLimit = $this->limits->imageLimit($page);
        $data = $request->validate([
            'feature' => ['required', 'in:products,services'],
            'hint' => ['nullable', 'string', 'max:500'],
            'group' => ['sometimes', 'boolean'],
            'groups' => ['nullable', 'string', 'max:2000'],
            'photos' => ['required', 'array', 'min:1', 'max:'.$imageLimit],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'photos.max' => __('ai.image_limit', ['limit' => $imageLimit]),
        ]);
        $this->limits->assertImageCount($page, count($data['photos']));
        AiFeatureRegistry::authorize($page, $data['feature']);
        $this->limits->throttle($page);

        $taskType = $data['feature'] === 'products' ? 'listing_from_photo' : 'service_listing';
        $groups = $this->photoGroups($data['groups'] ?? null, count($data['photos']), $imageLimit);
        $requested = $groups !== null ? count($groups) : (($data['group'] ?? false) ? 1 : count($data['photos']));
        $estimate = $credits->estimate($page, $requested, $taskType);
        $allowed = min($estimate['allowed'], $this->limits->availableSlots($page), $imageLimit);
        abort_if($allowed < 1, 429, 'This store already has the maximum number of Mercho tasks running. Wait for one to finish and try again.');

        $photos = $groups !== null
            ? $data['photos']
            : (($data['group'] ?? false) ? $data['photos'] : array_slice($data['photos'], 0, $allowed));
        $operations = [];

        $storedRelative = [];
        try {
            foreach ($photos as $photo) {
                $storedRelative[] = $images->stage($photo, $page);
            }
        } catch (Throwable $exception) {
            // Staged files must not be orphaned when one upload fails mid-loop.
            if ($storedRelative !== []) {
                $images->deleteMany($storedRelative);
            }

            throw $exception;
        }
        $relativeGroups = $groups !== null
            ? collect(array_slice($groups, 0, $allowed))->map(fn (array $group): array => collect($group)->map(fn (int $index): string => $storedRelative[$index - 1])->all())->all()
            : (($data['group'] ?? false) ? [$storedRelative] : array_map(fn (string $path): array => [$path], $storedRelative));

        $aiTask = AiTask::create([
            'seller_id' => $request->user()->id,
            'store_page_id' => $page->id,
            'type' => $data['feature'] === 'products' ? 'products' : 'services',
            'status' => 'queued',
            'total' => count($relativeGroups),
        ]);

        $usedRelative = [];
        $reservationFailed = false;

        // A concurrent request can consume the remaining credits between the
        // estimate above and this loop, so the reservation decides. Groups
        // that lose the race get their staged files cleaned up below.
        foreach ($relativeGroups as $index => $relativeGroup) {
            $operation = AiOperation::create([
                'seller_id' => $request->user()->id,
                'store_page_id' => $page->id,
                'task' => $taskType,
                'status' => 'queued',
                'input' => ['feature' => $data['feature'], 'hint' => $data['hint'] ?? null, 'batch_index' => $index, 'media_relative_paths' => $relativeGroup],
                'ai_task_id' => $aiTask->id,
            ]);

            try {
                app(AiUsageReservations::class)->reserveAndDispatch($operation);
            } catch (Throwable $exception) {
                $reservationFailed = true;
                break;
            }

            $usedRelative = [...$usedRelative, ...$relativeGroup];
            $operations[] = ['id' => $operation->id, 'status' => $operation->status, 'index' => $index];
        }
        $images->deleteMany(array_values(array_diff($storedRelative, array_unique($usedRelative))));

        $reserved = count($operations);
        $aiTask->update(['status' => $reserved > 0 ? 'processing' : 'failed', 'total' => $reserved, 'started_at' => $reserved > 0 ? now() : null]);

        $limitReached = $allowed < $requested || $reservationFailed;

        return response()->json([
            'operations' => $operations,
            'estimate' => $estimate,
            'task_id' => $aiTask->id,
            'limit_reached' => $limitReached,
            'upgrade_required' => $limitReached,
        ], 202);
    }

    public function retry(Request $request, int $operation): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $operation = AiOperation::query()->where('store_page_id', $page->id)->where('status', 'failed')->findOrFail($operation);
        $operation->update(['status' => 'queued', 'error' => null, 'started_at' => null, 'completed_at' => null]);
        app(AiUsageReservations::class)->reserveAndDispatch($operation);
        if ($operation->ai_task_id) {
            AiTask::find($operation->ai_task_id)?->refreshProgress();
        }

        return response()->json(['operation' => $operation->fresh()], 202);
    }

    public function status(Request $request, int $operation): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $operation = AiOperation::query()->where('store_page_id', $page->id)->findOrFail($operation);

        $draft = $operation->status === 'succeeded'
            ? AiDraft::query()->where('store_page_id', $page->id)->find($operation->output['draft_id'] ?? null)
            : null;

        return response()->json(['operation' => $operation, 'draft_id' => $draft?->id]);
    }

    /**
     * @return list<list<int>>|null
     */
    private function photoGroups(?string $encodedGroups, int $photoCount, int $imageLimit): ?array
    {
        if (blank($encodedGroups)) {
            return null;
        }

        $groups = json_decode($encodedGroups, true);
        if (! is_array($groups)) {
            throw ValidationException::withMessages(['groups' => 'Photo groups are invalid.']);
        }

        validator(['groups' => $groups], ['groups' => ['required', 'array', 'min:1', 'max:'.$imageLimit], 'groups.*' => ['required', 'array', 'min:1', 'max:'.$photoCount], 'groups.*.*' => ['integer', 'min:1', 'max:'.$photoCount]])->validate();

        return array_map(fn (array $group): array => array_values(array_unique(array_map('intval', $group))), $groups);
    }
}
