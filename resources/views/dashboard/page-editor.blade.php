@extends('layouts.app')

@section('content')
    <x-ui.page-header title="Edit page" :subtitle="'Content for '.$page->name" :back="route('dashboard')" back-label="Back to dashboard" />

    <div x-data="{ tab: 'home' }">
        <div class="flex gap-2 mb-6 overflow-x-auto">
            @foreach (array_keys($page->theme->pages()) as $pageKey)
                <button type="button" @click="tab = '{{ $pageKey }}'"
                        :class="tab === '{{ $pageKey }}' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-600'"
                        class="px-4 py-2 rounded-full text-sm font-semibold whitespace-nowrap">{{ ucfirst($pageKey) }}</button>
            @endforeach
        </div>

        <form method="POST" action="{{ route('page.editor.update') }}" enctype="multipart/form-data" id="page-editor-form">
            @csrf

            @php $n = 0; @endphp
            @foreach ($page->sections->groupBy('page') as $pageKey => $sections)
                <div x-show="tab === '{{ $pageKey }}'">
                    @foreach ($sections as $section)
                        <div data-section class="bg-white rounded-2xl border border-slate-200 p-5 mb-4">
                            <div class="flex items-center justify-between mb-3">
                                <label class="flex items-center gap-2 text-sm font-semibold text-ink">
                                    <input type="checkbox" name="sections[{{ $n }}][enabled]" value="1" {{ $section->is_enabled ? 'checked' : '' }} class="rounded border-slate-300 text-brand focus:ring-brand">
                                    {{ Str::headline($section->key) }}
                                </label>
                                <div class="flex gap-1">
                                    <button type="button" data-move="up" class="px-2 py-1 text-slate-400 hover:text-ink text-xs">↑</button>
                                    <button type="button" data-move="down" class="px-2 py-1 text-slate-400 hover:text-ink text-xs">↓</button>
                                </div>
                            </div>

                            <input type="hidden" name="sections[{{ $n }}][id]" value="{{ $section->id }}">

                            <div class="grid grid-cols-1 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Heading</label>
                                    <input type="text" name="sections[{{ $n }}][heading]" value="{{ $section->content['heading'] ?? '' }}">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Body</label>
                                    <textarea name="sections[{{ $n }}][body]" data-richtext rows="4">{{ $section->content['body'] ?? '' }}</textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Image</label>
                                    <input type="file" name="sections[{{ $n }}][image]" accept="image/*" class="text-sm">
                                    @if (! empty($section->content['image']))
                                        <img src="{{ Storage::url($section->content['image']) }}" class="mt-2 h-20 rounded-lg">
                                    @endif
                                </div>
                            </div>
                        </div>
                        @php $n++; @endphp
                    @endforeach
                </div>
            @endforeach

            <button class="mt-2 w-full sm:w-auto px-8 py-3.5 bg-brand hover:bg-brand-dark text-white font-bold text-sm rounded-xl">Save page</button>
        </form>
    </div>

    <script>
        document.querySelectorAll('[data-move]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const row = btn.closest('[data-section]');
                const container = row.parentElement;
                if (btn.dataset.move === 'up' && row.previousElementSibling) {
                    container.insertBefore(row, row.previousElementSibling);
                } else if (btn.dataset.move === 'down' && row.nextElementSibling) {
                    container.insertBefore(row.nextElementSibling, row);
                }
            });
        });
    </script>
@endsection
