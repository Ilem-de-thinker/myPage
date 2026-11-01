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

  <div class="mt-6 md:overflow-x-auto md:rounded-2xl md:border md:border-rule md:bg-card">
    <table class="block w-full text-left text-xs md:table md:min-w-[60rem]">
      <thead class="hidden border-b border-rule bg-surface text-[11px] uppercase tracking-wide text-ink-3 md:table-header-group">
        <tr>
          <th class="px-4 py-3 font-semibold">Seller</th>
          @foreach ($features as $feature)
            <th class="px-4 py-3 font-semibold">{{ ucfirst(str_replace('_', ' ', $feature)) }}</th>
          @endforeach
        </tr>
      </thead>

      <tbody class="block space-y-3 md:table-row-group md:space-y-0 md:divide-y md:divide-rule">
        @forelse ($sellers as $seller)
          <tr class="block rounded-2xl border border-rule bg-card p-4 md:table-row md:rounded-none md:border-0 md:bg-transparent md:p-0">
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Seller</span>
              <p class="font-bold text-ink">{{ $seller->name }}</p>
              <p class="mt-0.5 text-ink-3">{{ $seller->email }}</p>
              @if ($seller->isAdmin())
                <span class="mt-1 inline-block rounded-full bg-tint px-2 py-0.5 text-[10px] font-semibold text-ink-2">admin</span>
              @endif
            </td>

            @foreach ($features as $feature)
              @php
                  $on = $seller->resolved[$feature] ?? false;
                  $platformOn = (bool) config('mypage.features.'.$feature);
                  $overridden = \App\Domains\Billing\Services\Feature::hasOverride($feature, $seller->id);
              @endphp
              <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
                <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">{{ ucfirst(str_replace('_', ' ', $feature)) }}</span>
                <form method="POST" action="{{ route('admin.features.update') }}"
                      class="flex items-center justify-between gap-3 rounded-xl border border-rule px-3 py-2.5">
                  @csrf
                  <input type="hidden" name="seller_id" value="{{ $seller->id }}">
                  <input type="hidden" name="feature" value="{{ $feature }}">
                  <input type="hidden" name="enabled" value="{{ $on ? 0 : 1 }}">

                  <span class="min-w-0">
                    <span class="block text-[10px] text-ink-3">
                      {{ $overridden ? 'set for this seller' : ($platformOn ? 'on for everyone' : 'off for everyone') }}
                    </span>
                  </span>

                  <button title="{{ $on ? 'Turn off' : 'Turn on' }}"
                          class="relative h-6 w-11 shrink-0 rounded-full transition-colors {{ $on ? 'bg-emerald-500' : 'bg-rule-strong' }}">
                    <span class="absolute top-0.5 h-5 w-5 rounded-full bg-card shadow transition-all {{ $on ? 'left-[22px]' : 'left-0.5' }}"></span>
                  </button>
                </form>
              </td>
            @endforeach
          </tr>
        @empty
          <tr class="block rounded-2xl border border-rule bg-card p-4 md:table-row md:rounded-none md:border-0 md:bg-transparent md:p-0">
            <td colspan="{{ count($features) + 1 }}" class="block px-0 py-8 text-center text-ink-3 md:table-cell md:px-4 md:py-16">No sellers match that search.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if ($sellers->hasPages())
    <div class="mt-6">{{ $sellers->links() }}</div>
  @endif
</x-admin.layout>
