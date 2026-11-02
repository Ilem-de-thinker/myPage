@extends('layouts.app')

@section('heading', 'AI drafts')

@section('content')
    @php
        $pendingCount = (int) ($counts['pending'] ?? 0);
    @endphp

    <div class="space-y-4 p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">Review Mercho drafts</h2>
                <p class="text-xs text-ink-3">Nothing is published until you approve it. Edit the details, then approve or discard each draft.</p>
            </div>
            <a href="{{ route('dashboard.ai.tasks.index') }}" class="text-sm font-bold text-brand">Task history</a>
        </div>

        <nav class="flex flex-wrap gap-2 text-xs font-semibold" aria-label="Draft status filters">
            @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'discarded' => 'Discarded', 'undone' => 'Undone', 'all' => 'All'] as $key => $label)
                @php $count = $key === 'all' ? $counts->sum() : (int) ($counts[$key] ?? 0); @endphp
                <a href="{{ route('dashboard.ai.drafts', ['status' => $key]) }}"
                   @class([
                       'rounded-full border px-3 py-1.5',
                       'border-brand bg-brand-wash text-brand-text' => $status === $key,
                       'border-rule text-ink-2 hover:bg-tint' => $status !== $key,
                   ])>
                    {{ $label }} <span class="tabular-nums text-ink-3">{{ $count }}</span>
                </a>
            @endforeach
        </nav>

        @if ($drafts->isEmpty())
            <div class="rounded-2xl border border-dashed border-rule px-6 py-10 text-center">
                <p class="text-sm font-semibold text-ink">No {{ $status === 'all' ? '' : $status }} drafts.</p>
                <p class="mt-1 text-xs text-ink-3">Use Mercho from your products or services page to create drafts.</p>
            </div>
        @else
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-ink-3">
                <p>
                    Showing <span class="font-semibold text-ink">{{ $drafts->firstItem() }}–{{ $drafts->lastItem() }}</span>
                    of <span class="font-semibold text-ink">{{ $drafts->total() }}</span>
                    {{ $status === 'all' ? 'drafts' : $status.' drafts' }}
                </p>
                <p class="font-semibold">Newest first</p>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($drafts as $draft)
                    @php
                        $payload = $draft->payload ?? [];
                        $feature = $payload['_feature'] ?? null;
                        $isListing = in_array($feature, ['products', 'services'], true);
                        $media = array_values(array_filter((array) ($payload['_media_relative_paths'] ?? [])));
                        $taskLabel = \App\Domains\Ai\Services\AiSettings::taskLabel($draft->task);
                        $config = [
                            'id' => $draft->id,
                            'status' => $draft->status,
                            'feature' => $feature,
                            'payload' => $payload,
                            'csrf' => csrf_token(),
                            'updateUrl' => route('dashboard.ai.drafts.update', $draft),
                            'approveUrl' => route('dashboard.ai.drafts.approve', $draft),
                            'discardUrl' => route('dashboard.ai.drafts.discard', $draft),
                            'undoUrl' => route('dashboard.ai.drafts.undo', $draft),
                        ];
                    @endphp

                    <article class="space-y-3 rounded-2xl border border-rule bg-card p-4" x-data="aiDraftReview(@jsExpr($config))">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[11px] font-bold uppercase tracking-wide text-ink-3">{{ $taskLabel }}</p>
                                <p class="text-xs text-ink-3">v{{ $draft->version }} · {{ $draft->created_at->diffForHumans() }}</p>
                            </div>
                            <span class="rounded-full bg-tint px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-ink-2" x-text="status"></span>
                        </div>

                        @if (! empty($media))
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($media[0]) }}" alt="" class="h-40 w-full rounded-xl object-cover">
                        @endif

                        @if ($isListing)
                            <label class="block text-xs font-semibold text-ink-2">
                                Name
                                <input x-model="payload.name" :disabled="!isPending" class="mt-1 min-h-10 w-full rounded-xl border border-rule-strong bg-card px-3 text-sm text-ink disabled:opacity-70">
                            </label>

                            <label class="block text-xs font-semibold text-ink-2">
                                Description
                                <textarea x-model="payload.description" rows="4" :disabled="!isPending" class="mt-1 w-full rounded-xl border border-rule-strong bg-card p-3 text-sm leading-6 text-ink disabled:opacity-70"></textarea>
                            </label>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="block text-xs font-semibold text-ink-2">
                                    Category
                                    <input x-model="payload.category" :disabled="!isPending" list="draft-categories" class="mt-1 min-h-10 w-full rounded-xl border border-rule-strong bg-card px-3 text-sm text-ink disabled:opacity-70">
                                </label>
                                <label class="block text-xs font-semibold text-ink-2">
                                    Price hint
                                    <input x-model="payload.price_hint" :disabled="!isPending" class="mt-1 min-h-10 w-full rounded-xl border border-rule-strong bg-card px-3 text-sm text-ink disabled:opacity-70">
                                </label>
                            </div>

                            <label class="block text-xs font-semibold text-ink-2">
                                Tags
                                <input x-model="payload.tags" :disabled="!isPending" placeholder="Ankara, tote, handmade" class="mt-1 min-h-10 w-full rounded-xl border border-rule-strong bg-card px-3 text-sm text-ink disabled:opacity-70">
                            </label>

                            <div class="grid gap-3">
                                <label class="block text-xs font-semibold text-ink-2">
                                    SEO title
                                    <input x-model="payload.seo_title" :disabled="!isPending" class="mt-1 min-h-10 w-full rounded-xl border border-rule-strong bg-card px-3 text-sm text-ink disabled:opacity-70">
                                </label>
                                <label class="block text-xs font-semibold text-ink-2">
                                    SEO description
                                    <textarea x-model="payload.seo_description" rows="2" :disabled="!isPending" class="mt-1 w-full rounded-xl border border-rule-strong bg-card p-3 text-sm text-ink disabled:opacity-70"></textarea>
                                </label>
                            </div>
                        @elseif (! empty($payload['presets']))
                            <div class="space-y-2">
                                @foreach ($payload['presets'] as $index => $preset)
                                    <div class="rounded-xl border border-rule bg-surface p-3">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="text-sm font-bold">Preset {{ $index + 1 }} · {{ $preset['theme_key'] ?? 'Theme' }}</p>
                                                <p class="mt-1 text-xs leading-5 text-ink-2">{{ $preset['reason'] ?? '' }}</p>
                                            </div>
                                            <div class="flex shrink-0 items-center gap-2">
                                                <span class="size-7 rounded-full border border-rule" style="background: {{ $preset['brand_color'] ?? '#ffffff' }}" title="{{ $preset['brand_color'] ?? '' }}"></span>
                                                <span class="size-7 rounded-full border border-rule" style="background: {{ $preset['accent_color'] ?? '#ffffff' }}" title="{{ $preset['accent_color'] ?? '' }}"></span>
                                            </div>
                                        </div>
                                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                                            <p class="text-[11px] text-ink-3">{{ $preset['brand_color'] ?? '' }} · {{ $preset['accent_color'] ?? '' }}</p>
                                            <button type="button" x-show="isPending" @click="applyPreset({{ $index }})" :disabled="busy" class="min-h-10 rounded-xl bg-brand px-4 text-xs font-bold text-brand-ink disabled:opacity-50">Apply this direction</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @elseif ($draft->task === 'storefront_direction' && ! empty($payload['theme_key']))
                            <div class="space-y-3">
                                <div class="rounded-xl border border-rule bg-surface p-3">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-sm font-bold">{{ config('themes.presets.'.$payload['theme_key'].'.label', $payload['theme_key']) }}</p>
                                            <p class="mt-1 text-xs leading-5 text-ink-2">{{ $payload['reason'] ?? '' }}</p>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-2">
                                            <span class="size-7 rounded-full border border-rule" style="background: {{ $payload['brand_color'] ?? '#ffffff' }}"></span>
                                            <span class="size-7 rounded-full border border-rule" style="background: {{ $payload['accent_color'] ?? '#ffffff' }}"></span>
                                        </div>
                                    </div>
                                    <p class="mt-3 text-[11px] text-ink-3">
                                        {{ \Illuminate\Support\Str::headline($payload['surface'] ?? 'light') }} surface ·
                                        {{ \Illuminate\Support\Str::headline($payload['radius'] ?? 'rounded') }} corners ·
                                        {{ \Illuminate\Support\Str::headline($payload['hero_style'] ?? 'immersive') }} hero ·
                                        {{ ! empty($payload['carousel_autoplay']) ? 'auto-advance carousel' : 'manual carousel' }}
                                    </p>
                                    @if (! empty($payload['hidden_sections']))
                                        <p class="mt-1 text-[11px] text-ink-3">Hides: {{ implode(', ', $payload['hidden_sections']) }}</p>
                                    @endif
                                </div>

                                @foreach (($payload['copy'] ?? []) as $copyPage => $copyBlocks)
                                    <details class="rounded-xl border border-rule bg-surface p-3">
                                        <summary class="cursor-pointer text-xs font-bold">{{ \Illuminate\Support\Str::headline($copyPage) }} copy</summary>
                                        <div class="mt-3 space-y-2">
                                            @foreach ($copyBlocks as $copyBlock => $copyFields)
                                                <div>
                                                    <p class="text-[10px] font-bold uppercase tracking-wide text-ink-3">{{ \Illuminate\Support\Str::headline($copyBlock) }}</p>
                                                    @foreach ($copyFields as $copyField => $copyValue)
                                                        <p class="mt-1 whitespace-pre-wrap text-xs leading-5 text-ink-2">{{ \Illuminate\Support\Str::headline($copyField) }}: {{ is_array($copyValue) ? count($copyValue).' rows' : $copyValue }}</p>
                                                    @endforeach
                                                </div>
                                            @endforeach
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                        @else
                            @if (! empty($payload['text']))
                                <div class="rounded-xl border border-rule bg-surface p-3">
                                    <p class="text-xs font-semibold text-ink-2">Generated text</p>
                                    <p class="mt-1 whitespace-pre-wrap text-sm leading-6 text-ink">{{ $payload['text'] }}</p>
                                </div>
                            @else
                                <pre class="max-h-48 overflow-auto whitespace-pre-wrap rounded-xl border border-rule bg-surface p-3 text-xs leading-5 text-ink-2">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                            @endif
                        @endif

                        <p x-show="message" x-cloak class="rounded-xl border border-ok-edge bg-ok-wash px-3 py-2 text-xs text-ok-text" x-text="message"></p>
                        <p x-show="error" x-cloak role="alert" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" x-text="error"></p>

                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <button type="button" x-show="isPending" @click="discard()" :disabled="busy" class="min-h-10 rounded-xl border border-rule-strong px-3 text-xs font-bold text-ink-2 disabled:opacity-50">Discard</button>
                            <button type="button" x-show="isPending && isListing" @click="save()" :disabled="busy" class="min-h-10 rounded-xl border border-rule-strong px-3 text-xs font-bold text-ink-2 disabled:opacity-50">Save changes</button>
                            <button type="button" x-show="isPending && !hasPresets" @click="approve()" :disabled="busy" class="min-h-10 rounded-xl bg-brand px-4 text-xs font-bold text-brand-ink disabled:opacity-50">Approve</button>
                            <button type="button" x-show="isApproved" @click="undo()" :disabled="busy" class="min-h-10 rounded-xl border border-rule-strong px-3 text-xs font-bold text-ink-2 disabled:opacity-50">Undo approval</button>
                        </div>
                    </article>
                @endforeach
            </div>

            {{ $drafts->links() }}
        @endif

        <datalist id="draft-categories">
            @foreach ($page->categories()->orderBy('name')->pluck('name') as $category)
                <option value="{{ $category }}"></option>
            @endforeach
        </datalist>
    </div>
@endsection
