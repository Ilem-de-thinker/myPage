<x-admin.layout title="Stores">
  <h1 class="font-display text-2xl font-extrabold tracking-tight">Stores</h1>
  <p class="mt-1 text-sm text-ink-2">Every page on the platform.</p>

  <form method="GET" action="{{ route('admin.stores') }}" class="mt-5 max-w-sm">
    <input type="search" name="q" value="{{ $search }}" placeholder="Search by name or slug"
           class="w-full rounded-xl border border-rule bg-card px-4 py-2.5 text-sm focus:border-brand focus:outline-none">
  </form>

  <div class="mt-6 space-y-3">
    @forelse ($pages as $page)
      <div class="rounded-2xl border border-rule bg-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-sm font-bold text-ink">
              {{ $page->name }}
              <span class="ml-2 text-xs font-normal text-ink-3">/{{ $page->slug }}</span>
            </p>
            <p class="mt-0.5 text-xs text-ink-3">
              {{ ucfirst($page->theme->value) }} theme &middot;
              {{ $page->seller?->name }} ({{ $page->seller?->email }})
            </p>
            <p class="mt-0.5 text-xs text-ink-3">
              Joined {{ $page->created_at->format('j M Y') }}
              @if ($page->published_at)
                &middot; live since {{ $page->published_at->format('j M Y') }}
              @endif
            </p>
          </div>

          <span @class([
              'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold',
              'bg-ok-wash text-ok-text' => $page->isPublished(),
              'bg-tint text-ink-2' => $page->status->value === 'draft',
              'bg-danger-wash text-danger-text' => $page->status->value === 'suspended',
          ])>
            {{ $page->status->value }}
          </span>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-rule pt-4">
          @if ($page->status->value === 'suspended')
            <form method="POST" action="{{ route('admin.stores.reinstate', $page) }}">
              @csrf
              <input type="hidden" name="note" value="Reinstated by admin">
              <button class="rounded-xl border border-rule px-4 py-2 text-xs font-semibold text-ink hover:bg-surface">
                Reinstate as draft
              </button>
            </form>
          @else
            <form method="POST" action="{{ route('admin.stores.suspend', $page) }}" class="flex flex-1 flex-wrap items-end gap-2">
              @csrf
              <label class="min-w-48 flex-1">
                <span class="text-[11px] font-semibold text-ink-2">Reason (required, kept on the audit log)</span>
                <input type="text" name="reason" required
                       class="mt-1 w-full rounded-lg border border-rule px-3 py-2 text-sm"
                       placeholder="Chargeback fraud">
              </label>
              <button class="rounded-xl bg-red-600 px-4 py-2 text-xs font-semibold text-white hover:bg-red-700">
                Suspend
              </button>
            </form>
          @endif
        </div>
      </div>
    @empty
      <div class="rounded-2xl border border-rule bg-card px-6 py-16 text-center">
        <p class="text-sm text-ink-3">No stores match that search.</p>
      </div>
    @endforelse
  </div>

  @if ($pages->hasPages())
    <div class="mt-6">{{ $pages->links() }}</div>
  @endif
</x-admin.layout>