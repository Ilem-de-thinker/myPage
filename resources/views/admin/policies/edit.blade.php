<x-admin.layout title="Edit policy">
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <a href="{{ route('admin.policies') }}" class="text-xs font-semibold text-ink-3 hover:text-ink">← All policies</a>
      <h1 class="mt-2 font-display text-3xl font-extrabold tracking-tight">{{ $policy->title }}</h1>
      <p class="mt-2 text-sm text-ink-2">{{ $policy->slug }}</p>
    </div>
    <a href="{{ route('legal.policy', $policy->slug) }}" target="_blank" class="text-xs font-semibold text-brand-text hover:underline">View public page</a>
  </div>

  <form method="POST" action="{{ route('admin.policies.update', $policy) }}" class="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
    @csrf

    <div class="space-y-6">
      <section class="rounded-2xl border border-rule bg-card p-6">
        <h2 class="text-base font-bold">Page copy</h2>
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
          <label class="block sm:col-span-2">
            <span class="text-xs font-semibold">Title</span>
            <input name="title" value="{{ old('title', $policy->title) }}" required
                   class="mt-1 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
            @error('title') <span class="mt-1 block text-xs text-danger-text">{{ $message }}</span> @enderror
          </label>
          <label class="block sm:col-span-2">
            <span class="text-xs font-semibold">Summary</span>
            <textarea name="summary" rows="2" required
                      class="mt-1 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 text-sm focus:border-brand focus:outline-none">{{ old('summary', $policy->summary) }}</textarea>
            @error('summary') <span class="mt-1 block text-xs text-danger-text">{{ $message }}</span> @enderror
          </label>
          <label class="block">
            <span class="text-xs font-semibold">Version</span>
            <input name="version" value="{{ old('version', $policy->version) }}" required
                   class="mt-1 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
          </label>
          <label class="block">
            <span class="text-xs font-semibold">Effective date</span>
            <input type="date" name="effective_at" value="{{ old('effective_at', $policy->effective_at?->format('Y-m-d')) }}"
                   class="mt-1 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
          </label>
        </div>
      </section>

      <section class="rounded-2xl border border-rule bg-card p-6">
        <h2 class="text-base font-bold">Sections</h2>
        <p class="mt-1 text-sm text-ink-3">A JSON array of objects with <code>heading</code> and <code>body</code> keys.</p>
        <textarea name="sections" rows="22" required spellcheck="false"
                  class="mt-4 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 font-mono text-xs focus:border-brand focus:outline-none">{{ old('sections', $sectionsJson) }}</textarea>
        @error('sections') <span class="mt-1 block text-xs text-danger-text">{{ $message }}</span> @enderror
      </section>
    </div>

    <aside class="space-y-6">
      <section class="rounded-2xl border border-rule bg-card p-6">
        <h2 class="text-base font-bold">Publication</h2>
        <label class="mt-4 flex cursor-pointer items-start gap-3 rounded-xl border border-rule p-4">
          <input type="hidden" name="published" value="0">
          <input type="checkbox" name="published" value="1" @checked(old('published', $policy->published_at !== null)) class="mt-0.5 size-4 accent-brand">
          <span>
            <span class="block text-sm font-semibold">Published</span>
            <span class="mt-1 block text-xs text-ink-3">Only published rows are served publicly.</span>
          </span>
        </label>
        <label class="mt-3 flex cursor-pointer items-start gap-3 rounded-xl border border-rule p-4">
          <input type="hidden" name="is_draft" value="0">
          <input type="checkbox" name="is_draft" value="1" @checked(old('is_draft', $policy->is_draft)) class="mt-0.5 size-4 accent-brand">
          <span>
            <span class="block text-sm font-semibold">Draft for lawyer review</span>
            <span class="mt-1 block text-xs text-ink-3">Shows a review banner on the public page.</span>
          </span>
        </label>
        <button class="mt-5 w-full rounded-xl bg-brand px-4 py-2.5 text-sm font-bold text-white">Save policy</button>
      </section>
    </aside>
  </form>
</x-admin.layout>
