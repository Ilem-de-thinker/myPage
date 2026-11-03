@extends('layouts.app')

@section('heading', 'AI task results')

@section('content')
    @php
        $taskConfig = [
            'id' => $task->id,
            'status' => $task->status,
            'completed' => $task->completed,
            'failed' => $task->failed,
            'total' => $task->total,
            'statusUrl' => route('dashboard.ai.tasks.status', $task),
            'cancelUrl' => route('dashboard.ai.tasks.cancel', $task),
            'retryUrl' => route('dashboard.ai.tasks.retry-failed', $task),
            'csrf' => csrf_token(),
        ];
    @endphp

    <div class="p-6 space-y-4" x-data="aiTaskShow(@jsExpr($taskConfig))" x-init="init()">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ $task->label() }} task</h2>
                <p class="text-xs text-ink-3">
                    <span x-text="statusLabel"></span> ·
                    <span x-text="completed"></span>/<span x-text="total"></span> done ·
                    <span x-text="failed"></span> failed
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" x-show="failed > 0" @click="retryFailed()" :disabled="busy" class="rounded-lg border border-rule px-3 py-2 text-sm disabled:opacity-50">Retry failed</button>
                <button type="button" x-show="canCancel" @click="cancel()" :disabled="busy" class="rounded-lg border border-rule px-3 py-2 text-sm disabled:opacity-50">Cancel</button>
                @if ($task->type === 'descriptions')
                    <form method="POST" action="{{ route('products.apply-descriptions', $task) }}">
                        @csrf
                        <button class="rounded-lg bg-brand px-3 py-2 text-sm font-bold text-brand-ink">Apply descriptions</button>
                    </form>
                @elseif ($task->type === 'categories')
                    <form method="POST" action="{{ route('categories.apply-ai', $task) }}">
                        @csrf
                        <button class="rounded-lg bg-brand px-3 py-2 text-sm font-bold text-brand-ink">Apply categories</button>
                    </form>
                @elseif ($task->type === 'service_descriptions')
                    <form method="POST" action="{{ route('services.apply-descriptions', $task) }}">
                        @csrf
                        <button class="rounded-lg bg-brand px-3 py-2 text-sm font-bold text-brand-ink">Apply descriptions</button>
                    </form>
                @else
                    <a href="{{ route('dashboard.ai.drafts') }}" class="rounded-lg bg-brand px-3 py-2 text-sm font-bold text-brand-ink">Review drafts</a>
                @endif
                @if ($task->type === 'descriptions' && ! empty($task->summary['previous_descriptions']))
                    <form method="POST" action="{{ route('products.undo-descriptions', $task) }}">
                        @csrf
                        <button class="rounded-lg border border-rule px-3 py-2 text-sm">Undo descriptions</button>
                    </form>
                @elseif ($task->type === 'categories' && ! empty($task->summary['previous_assignments']))
                    <form method="POST" action="{{ route('categories.undo-ai', $task) }}">
                        @csrf
                        <button class="rounded-lg border border-rule px-3 py-2 text-sm">Undo categories</button>
                    </form>
                @elseif ($task->type === 'service_descriptions' && ! empty($task->summary['previous_service_descriptions']))
                    <form method="POST" action="{{ route('services.undo-descriptions', $task) }}">
                        @csrf
                        <button class="rounded-lg border border-rule px-3 py-2 text-sm">Undo descriptions</button>
                    </form>
                @endif
            </div>
        </div>

        <p x-show="message" x-cloak class="rounded-xl border border-ok-edge bg-ok-wash px-4 py-3 text-xs text-ok-text" x-text="message"></p>
        <p x-show="error" x-cloak role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-700" x-text="error"></p>

        <div class="grid gap-4 md:grid-cols-2">
            @forelse ($task->operations as $op)
                <div class="space-y-2 rounded-xl border border-rule bg-card p-4">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-semibold">{{ ucfirst($op->status) }}</span>
                        @if ($op->error)
                            <span class="truncate text-xs text-red-600" title="{{ $op->error }}">{{ $op->error }}</span>
                        @endif
                    </div>
                    @php $draft = $op->status === 'succeeded' ? \App\Domains\Ai\Models\AiDraft::find($op->output['draft_id'] ?? null) : null; @endphp
                    @if ($draft)
                        <p class="font-semibold">{{ $draft->payload['name'] ?? 'Draft' }}</p>
                        <p class="line-clamp-3 text-sm text-ink-2">{{ $draft->payload['description'] ?? '' }}</p>
                        @if (! empty($draft->payload['_media_relative_paths']))
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($draft->payload['_media_relative_paths'][0]) }}" alt="" class="h-32 w-full rounded-lg object-cover">
                        @endif
                        <a href="{{ route('dashboard.ai.drafts') }}" class="inline-block text-xs font-bold text-brand">Edit and publish draft</a>
                    @elseif ($op->status === 'succeeded' && in_array($task->type, ['descriptions', 'service_descriptions'], true))
                        @php $output = $op->output ?? []; @endphp
                        <p class="font-semibold">{{ $op->input['item']['name'] ?? ($task->type === 'service_descriptions' ? 'Service' : 'Product') }}</p>
                        <p class="line-clamp-4 text-sm text-ink-2">{{ $output['description'] ?? '' }}</p>
                        @if (! empty($output['seo_title']))
                            <p class="text-xs text-ink-3">SEO: {{ $output['seo_title'] }}</p>
                        @endif
                        @if (! empty($output['flags']) || ! empty($output['insufficient_info']))
                            <p class="text-xs text-amber-700">Review: {{ $output['insufficient_info'] ?: implode(', ', (array) $output['flags']) }}</p>
                        @endif
                    @elseif ($op->status === 'succeeded' && $task->type === 'categories')
                        @php $output = $op->output ?? []; @endphp
                        <p class="font-semibold">Category proposal</p>
                        <p class="text-sm text-ink-2">{{ $output['summary'] ?? '' }}</p>
                        <p class="text-xs text-ink-3">
                            {{ count($output['categories'] ?? []) }} categories ·
                            {{ count($output['assignments'] ?? []) }} items assigned
                        </p>
                    @elseif ($op->status === 'queued' || $op->status === 'running')
                        <p class="text-xs text-ink-3">Waiting for Mercho to finish this item…</p>
                    @elseif ($op->status === 'cancelled')
                        <p class="text-xs text-ink-3">Cancelled.</p>
                    @elseif ($op->status === 'failed')
                        <p class="text-xs text-red-600">{{ $op->error ?: 'This item could not be generated.' }}</p>
                    @endif
                </div>
            @empty
                <p class="text-sm text-ink-3">This task has no items yet.</p>
            @endforelse
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('aiTaskShow', (config = {}) => ({
                id: config.id,
                status: config.status,
                completed: config.completed,
                failed: config.failed,
                total: config.total,
                busy: false,
                error: '',
                message: '',
                timer: null,

                get canCancel() {
                    return ['queued', 'processing', 'partially_completed', 'failed'].includes(this.status);
                },

                get statusLabel() {
                    return (this.status || '').replaceAll('_', ' ');
                },

                init() {
                    this.poll();
                },

                async request(url, method = 'GET') {
                    const response = await fetch(url, {
                        method,
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': config.csrf,
                        },
                    });

                    let data = {};
                    try {
                        data = await response.json();
                    } catch (e) {
                        data = {};
                    }

                    if (! response.ok) {
                        throw new Error(data.message || 'The request could not be completed.');
                    }

                    return data;
                },

                applyTask(task) {
                    if (! task) {
                        return;
                    }

                    this.status = task.status;
                    this.completed = task.completed;
                    this.failed = task.failed;
                    this.total = task.total;
                },

                async poll() {
                    window.clearTimeout(this.timer);

                    try {
                        const data = await this.request(config.statusUrl);
                        this.applyTask(data.task);

                        if (['queued', 'processing'].includes(this.status)) {
                            this.timer = window.setTimeout(() => this.poll(), 3000);
                        }
                    } catch (e) {
                        this.timer = window.setTimeout(() => this.poll(), 5000);
                    }
                },

                async cancel() {
                    this.busy = true;
                    this.error = '';
                    this.message = '';

                    try {
                        const data = await this.request(config.cancelUrl, 'POST');
                        this.applyTask(data.task);
                        this.message = 'Task cancelled. Any queued items will not run.';
                        window.clearTimeout(this.timer);
                    } catch (e) {
                        this.error = e.message;
                    } finally {
                        this.busy = false;
                    }
                },

                async retryFailed() {
                    this.busy = true;
                    this.error = '';
                    this.message = '';

                    try {
                        const data = await this.request(config.retryUrl, 'POST');
                        this.applyTask(data.task);
                        this.message = data.retried + ' failed item(s) queued again.';
                        this.poll();
                    } catch (e) {
                        this.error = e.message;
                    } finally {
                        this.busy = false;
                    }
                },
            }));
        });
    </script>
@endsection
