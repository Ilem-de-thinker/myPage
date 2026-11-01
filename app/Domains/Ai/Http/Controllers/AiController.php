<?php

namespace App\Domains\Ai\Http\Controllers;

use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Ai\Jobs\RunAiOperation;
use App\Domains\Ai\Models\AiDraft;
use App\Domains\Ai\Models\AiOperation;
use App\Domains\Ai\Services\AiCreditService;
use App\Domains\Ai\Services\AiSettings;
use App\Domains\Ai\Support\AiFeatureRegistry;
use App\Domains\Seller\Enums\Theme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AiController extends Controller
{
    public function __construct(private readonly AiSettings $settings) {}

    public function contextual(Request $request, AiManager $ai): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $data = $request->validate([
            'feature' => ['required', 'string'],
            'input' => ['required', 'array'],
        ]);
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
        if ($task === 'theme_and_color_preset') {
            $input['theme_registry'] = collect(Theme::cases())
                ->map(fn (Theme $theme): array => ['key' => $theme->value, 'label' => $theme->label()])
                ->all();
        }

        $result = $data['feature'] === 'appearance'
            ? ['presets' => collect(range(1, 3))->map(fn (): string|array => $ai->run($task, $input, $page))->all()]
            : $ai->run($task, $input, $page);
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
        AiFeatureRegistry::authorize($page, 'appearance');
        abort_unless((bool) $this->settings->enabled() && (bool) ($page->settings['ai_enabled'] ?? true), 503, 'Mercho is disabled for this store.');
        abort_unless(function_exists('imagecreatefromjpeg'), 503, 'Logo color extraction is unavailable.');
        $data = $request->validate(['logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']]);
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

    public function suggestPhotoGroups(Request $request, AiManager $ai): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $data = $request->validate([
            'feature' => ['required', 'in:products,services'],
            'photos' => ['required', 'array', 'min:2', 'max:12'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);
        AiFeatureRegistry::authorize($page, $data['feature']);

        $paths = [];
        try {
            foreach ($data['photos'] as $photo) {
                $relativePath = $photo->store('ai/listing-inputs', 'local');
                $paths[] = Storage::disk('local')->path($relativePath);
            }

            $result = $ai->run('listing_photo_grouping', ['feature' => $data['feature'], 'photo_count' => count($paths), 'media_paths' => $paths], $page);
        } finally {
            foreach ($paths as $path) {
                if (is_string($path) && is_file($path)) {
                    unlink($path);
                }
            }
        }

        $groups = collect($result['groups'] ?? [])->map(fn (array $group): array => collect($group)->map(fn (string|int $index): int => (int) $index)->filter(fn (int $index): bool => $index >= 1 && $index <= count($data['photos']))->values()->all())->filter()->values()->all();

        if ($groups === []) {
            throw ValidationException::withMessages(['photos' => 'Mercho could not suggest photo groups.']);
        }

        return response()->json(['groups' => $groups, 'reason' => $result['reason'] ?? null]);
    }

    public function estimate(Request $request, AiCreditService $credits): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $data = $request->validate(['feature' => ['required', 'string'], 'item_count' => ['required', 'integer', 'min:1', 'max:50']]);
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
        $task = $data['feature'] === 'products' ? 'listing_copy' : 'service_listing';
        $estimate = $credits->estimate($page, count($data['items']), $task);
        $operations = [];

        foreach (array_slice($data['items'], 0, $estimate['allowed']) as $index => $item) {
            $operation = AiOperation::create(['seller_id' => $request->user()->id, 'store_page_id' => $page->id, 'task' => $task, 'status' => 'queued', 'input' => ['feature' => $data['feature'], 'batch_index' => $index, ...$item]]);
            RunAiOperation::dispatch($operation);
            $operations[] = ['id' => $operation->id, 'status' => $operation->status, 'index' => $index];
        }

        return response()->json(['operations' => $operations, 'estimate' => $estimate, 'upgrade_required' => $estimate['allowed'] < count($data['items'])], 202);
    }

    public function run(Request $request, AiManager $ai): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        abort_unless((bool) $this->settings->enabled() && (bool) ($page->settings['ai_enabled'] ?? true), 503, 'Mercho is disabled for this store.');
        $data = $request->validate(['task' => ['required', 'string', 'in:listing_copy,service_listing,copy_rewrite'], 'feature' => ['required', 'string'], 'input' => ['required', 'array']]);
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
        $data = $request->validate([
            'task' => ['required', 'string', 'in:site_builder,listing_from_photo,insights_digest'],
            'feature' => ['required', 'string'],
            'input' => ['required', 'array'],
        ]);
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
        RunAiOperation::dispatch($operation);

        return response()->json(['operation_id' => $operation->id, 'status' => $operation->status, 'estimate' => $estimate], 202);
    }

    public function photoListing(Request $request, AiCreditService $credits): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $data = $request->validate([
            'feature' => ['required', 'in:products,services'],
            'hint' => ['nullable', 'string', 'max:500'],
            'group' => ['sometimes', 'boolean'],
            'groups' => ['nullable', 'string'],
            'photos' => ['required', 'array', 'min:1', 'max:12'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);
        AiFeatureRegistry::authorize($page, $data['feature']);
        $task = $data['feature'] === 'products' ? 'listing_from_photo' : 'service_listing';
        $groups = $this->photoGroups($data['groups'] ?? null, count($data['photos']));
        $requested = $groups !== null ? count($groups) : (($data['group'] ?? false) ? 1 : count($data['photos']));
        $estimate = $credits->estimate($page, $requested, $task);
        $photos = $groups !== null
            ? $data['photos']
            : (($data['group'] ?? false) && $estimate['allowed'] > 0 ? $data['photos'] : array_slice($data['photos'], 0, $estimate['allowed']));
        $operations = [];

        $storedPaths = [];
        foreach ($photos as $photo) {
            $path = $photo->store('ai/listing-inputs', 'local');
            $storedPaths[] = Storage::disk('local')->path($path);
        }
        $photoGroups = $groups !== null
            ? collect(array_slice($groups, 0, $estimate['allowed']))->map(fn (array $group): array => collect($group)->map(fn (int $index): string => $storedPaths[$index - 1])->all())->all()
            : (($data['group'] ?? false) ? [$storedPaths] : array_map(fn (string $path): array => [$path], $storedPaths));
        $usedPaths = [];
        foreach ($photoGroups as $index => $photoGroup) {
            $usedPaths = [...$usedPaths, ...$photoGroup];
            $operation = AiOperation::create([
                'seller_id' => $request->user()->id,
                'store_page_id' => $page->id,
                'task' => $task,
                'status' => 'queued',
                'input' => ['feature' => $data['feature'], 'hint' => $data['hint'] ?? null, 'batch_index' => $index, 'media_paths' => $photoGroup],
            ]);
            RunAiOperation::dispatch($operation);
            $operations[] = ['id' => $operation->id, 'status' => $operation->status, 'index' => $index];
        }
        foreach (array_diff($storedPaths, array_unique($usedPaths)) as $unusedPath) {
            if (is_file($unusedPath)) {
                unlink($unusedPath);
            }
        }

        return response()->json(['operations' => $operations, 'estimate' => $estimate, 'upgrade_required' => $estimate['allowed'] < $requested], 202);
    }

    public function retry(Request $request, int $operation): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $operation = AiOperation::query()->where('store_page_id', $page->id)->where('status', 'failed')->findOrFail($operation);
        $operation->update(['status' => 'queued', 'error' => null, 'started_at' => null, 'completed_at' => null]);
        RunAiOperation::dispatch($operation);

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

    /** @return list<list<int>>|null */
    private function photoGroups(?string $encodedGroups, int $photoCount): ?array
    {
        if (blank($encodedGroups)) {
            return null;
        }

        $groups = json_decode($encodedGroups, true);
        if (! is_array($groups)) {
            throw ValidationException::withMessages(['groups' => 'Photo groups are invalid.']);
        }

        validator(['groups' => $groups], ['groups' => ['required', 'array', 'min:1', 'max:12'], 'groups.*' => ['required', 'array', 'min:1', 'max:12'], 'groups.*.*' => ['integer', 'min:1', 'max:'.$photoCount]])->validate();

        return array_map(fn (array $group): array => array_values(array_unique(array_map('intval', $group))), $groups);
    }
}
