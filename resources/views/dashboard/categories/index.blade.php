@extends('layouts.app')

@section('content')
    <x-ui.page-header
        title="Categories"
        :subtitle="$categories->total().' categor'.($categories->total() === 1 ? 'y' : 'ies').' in your store'"
    >
        <a href="{{ route('categories.create') }}" class="px-4 py-2.5 rounded-xl bg-brand hover:bg-brand-hover text-white font-semibold text-xs">+ Add category</a>
        <form action="{{ route('categories.generate-ai') }}" method="POST" class="inline">
            @csrf
            <button class="px-4 py-2.5 rounded-xl border border-rule font-semibold text-xs">Generate categories with AI</button>
        </form>
    </x-ui.page-header>

    <x-ui.data-table
        :rows="$categories"
        :columns="[
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'slug', 'label' => 'Slug'],
            ['key' => 'products_count', 'label' => 'Products'],
        ]"
        search-placeholder="Search categories…"
        :bulk-destroy-route="route('categories.bulk-destroy')"
        edit-route="categories.edit"
        destroy-route="categories.destroy"
        empty-text="No categories yet. Add one if your catalogue is large enough to need filtering."
    />

    @if ($task && ! empty($task->summary['previous_assignments']))
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-ok-edge bg-ok-wash px-4 py-3 text-xs text-ok-text">
            <p><span class="font-bold">Categories applied.</span> Mercho created or reused categories and regrouped your products.</p>
            <form method="POST" action="{{ route('categories.undo-ai', $task) }}">
                @csrf
                <button class="rounded-lg border border-ok-edge bg-card px-3 py-1.5 font-bold">Undo</button>
            </form>
        </div>
    @elseif ($task)
        <div
            x-data="categoryAiPreview(@jsExpr([
                'statusUrl' => route('dashboard.ai.tasks.status', $task),
                'applyUrl' => route('categories.apply-ai', $task),
                'result' => $proposal,
            ]))"
            x-init="init()"
            class="app-modal fixed inset-0 z-50 flex items-center justify-center bg-ink/45 p-4"
        >
            <div class="app-modal-panel w-full max-w-2xl rounded-2xl border border-rule bg-card p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold">Proposed categories</h2>
                        <p class="text-xs text-ink-3">Review Mercho's category structure before it is applied.</p>
                    </div>
                    <a href="{{ route('categories.index') }}" class="text-ink-3 hover:text-ink" aria-label="Close">✕</a>
                </div>

                <p x-show="loading" class="mt-4 text-sm text-ink-3">Generating category suggestions…</p>
                <p x-show="error" x-text="error" class="mt-4 text-sm text-danger-text"></p>

                <div x-show="result" x-cloak class="mt-4 space-y-4">
                    <p class="text-sm text-ink-2" x-text="result.summary"></p>

                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-ink-3">Categories</p>
                        <ul class="mt-2 space-y-1 text-sm text-ink">
                            <template x-for="category in (result.categories || [])" :key="category.name">
                                <li>
                                    <span class="font-semibold" x-text="category.name"></span>
                                    <span x-show="category.parent" class="text-xs text-ink-3" x-text="' → ' + category.parent"></span>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-ink-3">Assignments</p>
                        <p class="mt-1 text-xs text-ink-3"><span x-text="(result.assignments || []).length"></span> item(s) assigned</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <a href="{{ route('categories.index') }}" class="rounded-xl border border-rule px-4 py-2 text-xs font-semibold text-ink-2">Close</a>
                    <form method="POST" :action="applyUrl">
                        @csrf
                        <button type="submit" :disabled="! result" class="rounded-xl bg-brand px-4 py-2 text-xs font-bold text-brand-ink disabled:opacity-40">Apply categories</button>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection