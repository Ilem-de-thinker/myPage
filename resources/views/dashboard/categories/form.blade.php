@extends('layouts.app')

@section('content')
    <x-ui.page-header
        :title="$category ? 'Edit category' : 'Add category'"
        :back="route('categories.index')"
        back-label="Back to categories"
        width="max-w-2xl mx-auto"
    />

    <form method="POST"
          action="{{ $category ? route('categories.update', $category) : route('categories.store') }}"
          class="bg-white p-6 rounded-2xl border border-slate-200 space-y-5 max-w-2xl mx-auto">
        @csrf
        @if ($category) @method('PUT') @endif

        <div>
            <label for="name" class="block text-xs font-semibold text-slate-700 mb-1.5">Name</label>
            <input id="name" name="name" type="text" required maxlength="120"
                   value="{{ old('name', $category?->name) }}"
                   placeholder="e.g. Fabrics"
                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:border-brand">
            @error('name') <p class="text-red-600 text-[11px] mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="pt-2 flex justify-end gap-3">
            <a href="{{ route('categories.index') }}" class="px-4 py-2.5 text-sm font-semibold text-slate-500">Cancel</a>
            <button class="px-6 py-3 rounded-xl bg-brand hover:bg-brand-dark text-white font-semibold text-sm">
                {{ $category ? 'Save category' : 'Add category' }}
            </button>
        </div>
    </form>
@endsection