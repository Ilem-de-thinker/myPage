<x-admin.layout title="Features">
  <h1 class="font-display text-2xl font-extrabold tracking-tight">Features</h1>
  <p class="mt-1 text-sm text-ink-2">
    Turn a feature on or off for one seller. The dot shows what actually happens
    now, which is not always what the button says.
  </p>

  @if ($locked)
    <div class="mt-5 rounded-xl border border-danger-edge bg-danger-wash px-4 py-3 text-sm text-danger-text">
      <span class="font-semibold">Everything is switched off.</span>
      FEATURES_LOCKED is set, so no flag in this panel has any effect until it
      is cleared. That is the panic switch, not a bug.
    </div>
  @endif

  <form method="GET" action="{{ route('admin.features') }}" class="mt-5 max-w-sm">
    <input type="search" name="q" value="{{ $search }}" placeholder="Search by email"
           class="w-full rounded-xl border border-rule bg-card px-4 py-2.5 text-sm focus:border-brand focus:outline-none">
  </form>

  <div class="mt-6 space-y-3">
    @forelse ($sellers as $seller)
      <div class="rounded-2xl border border-rule bg-card p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="min-w-0">
            <p class="text-sm font-bold text-ink">{{ $seller->name }}</p>
            <p class="mt-0.5 text-xs text-ink-3">{{ $seller->email }}</p>
          </div>
          @if ($seller->is_admin)
            <span class="rounded-full bg-tint px-2 py-0.5 text-[10px] font-semibold text-ink-2">admin</span>
          @endif
        </div>

        <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
          @foreach ($features as $feature)
            @php
                $on = $seller->resolved[$feature] ?? false;
                $platformOn = (bool) config('mypage.features.'.$feature);
                $overridden = \App\Domains\Billing\Services\Feature::hasOverride($feature, $seller->id);
            @endphp

            <form method="POST" action="{{ route('admin.features.update') }}"
                  class="flex items-center justify-between gap-3 rounded-xl border border-rule px-3 py-2.5">
              @csrf
              <input type="hidden" name="seller_id" value="{{ $seller->id }}">
              <input type="hidden" name="feature" value="{{ $feature }}">
              <input type="hidden" name="enabled" value="{{ $on ? 0 : 1 }}">

              <span class="min-w-0">
                <span class="block truncate text-xs font-semibold text-ink">{{ ucfirst(str_replace('_', ' ', $feature)) }}</span>
                <span class="block text-[10px] text-ink-3">
                  {{ $overridden ? 'set for this seller' : ($platformOn ? 'on for everyone' : 'off for everyone') }}
                </span>
              </span>

              <button title="{{ $on ? 'Turn off' : 'Turn on' }}"
                      class="relative h-6 w-11 shrink-0 rounded-full transition-colors {{ $on ? 'bg-emerald-500' : 'bg-rule-strong' }}">
                <span class="absolute top-0.5 h-5 w-5 rounded-full bg-card shadow transition-all {{ $on ? 'left-[22px]' : 'left-0.5' }}"></span>
              </button>
            </form>
          @endforeach
        </div>
      </div>
    @empty
      <div class="rounded-2xl border border-rule bg-card px-6 py-16 text-center">
        <p class="text-sm text-ink-3">No sellers match that search.</p>
      </div>
    @endforelse
  </div>

  @if ($sellers->hasPages())
    <div class="mt-6">{{ $sellers->links() }}</div>
  @endif
</x-admin.layout>
