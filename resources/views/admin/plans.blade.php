<x-admin.layout title="Plans">
  <h1 class="font-display text-2xl font-extrabold tracking-tight">Plans</h1>
  <p class="mt-1 text-sm text-ink-2">
    What each plan costs, and whether {{ $gateway }} agrees. A plan not connected
    to the gateway is charged by hand, one period at a time.
  </p>

  <div class="mt-6 md:overflow-x-auto md:rounded-2xl md:border md:border-rule md:bg-card">
    <table class="block w-full text-left text-xs md:table md:min-w-[76rem]">
      <thead class="hidden border-b border-rule bg-surface text-[11px] uppercase tracking-wide text-ink-3 md:table-header-group">
        <tr>
          <th class="px-4 py-3 font-semibold">Plan</th>
          <th class="px-4 py-3 font-semibold">Price</th>
          <th class="px-4 py-3 font-semibold">Gateway</th>
          <th class="px-4 py-3 font-semibold">Limits</th>
          <th class="px-4 py-3 font-semibold">Actions</th>
        </tr>
      </thead>

      <tbody class="block space-y-3 md:table-row-group md:space-y-0 md:divide-y md:divide-rule">
        @foreach ($plans as $plan)
          @php
            $linked = $plan->gateway_plan_code !== null;
            $remote = $gatewayPlans[$plan->getKey()] ?? null;
            // Null remote on a linked plan means the gateway could not be read at all.
            $unreachable = $linked && $remote === null;
            $drift = $remote !== null && (int) $remote['amount_kobo'] !== $plan->price_kobo;
            $intervalDrift = $remote !== null && $remote['interval'] !== $plan->interval;
          @endphp

          <tr class="block rounded-2xl border {{ $drift || $intervalDrift || $unreachable ? 'border-danger-edge' : 'border-rule' }} bg-card p-4 md:table-row md:rounded-none md:border-0 md:bg-transparent md:p-0">
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Plan</span>
              <p class="font-bold text-ink">{{ $plan->name }}</p>
              <p class="mt-0.5 text-ink-3">{{ $plan->description }}</p>
              <p class="mt-1 text-[11px] text-ink-3">{{ $plan->slug }}</p>
            </td>

            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Price</span>
              <p class="text-lg font-extrabold text-ink">{{ \App\Shared\Support\Money::format($plan->price_kobo) }}</p>
              <p class="text-[11px] text-ink-3">per {{ rtrim($plan->interval, 'ly') }}</p>
            </td>

            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Gateway</span>
              <div class="rounded-xl border border-rule px-3 py-2.5">
                @if ($unreachable)
                  <p class="font-semibold text-danger-text">Could not read this plan from {{ $gateway }}.</p>
                  <p class="mt-1 text-ink-3">
                    The price above is what we quote, but it has not been confirmed against the gateway,
                    so treat it as unverified until this page can reach it.
                  </p>
                @elseif (! $linked)
                  <p class="font-semibold text-warn-text">Not connected to {{ $gateway }}.</p>
                  <p class="mt-1 text-ink-3">
                    No automatic billing. Sellers pay one period at a time by hand and nothing renews on its own.
                  </p>
                @elseif ($drift || $intervalDrift)
                  <p class="font-semibold text-danger-text">This plan is charging the wrong amount.</p>
                  <p class="mt-1 text-ink-3">
                    We quote
                    <span class="font-semibold text-ink">{{ \App\Shared\Support\Money::format($plan->price_kobo) }}</span>
                    per {{ rtrim($plan->interval, 'ly') }},
                    but {{ $gateway }} charges
                    <span class="font-semibold text-ink">{{ \App\Shared\Support\Money::format($remote['amount_kobo']) }}</span>
                    per {{ rtrim($remote['interval'], 'ly') }}.
                    The gateway charges its own amount, so sellers are being billed the second figure.
                  </p>
                @else
                  <p class="font-semibold text-ok-text">Agrees with {{ $gateway }}.</p>
                  <p class="mt-1 text-ink-3">
                    Charging {{ \App\Shared\Support\Money::format($remote['amount_kobo']) }} per {{ rtrim($remote['interval'], 'ly') }}
                    · <span class="font-mono">{{ $plan->gateway_plan_code }}</span>
                  </p>
                @endif
              </div>
            </td>

            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Limits</span>
              <p class="text-ink-2">
                @if ($plan->max_products !== null)
                  Up to {{ $plan->max_products }} products
                @else
                  Unlimited products
                @endif
              </p>
              <p class="mt-0.5 text-ink-3">
                @if ($plan->max_staff !== null)
                  {{ $plan->max_staff }} {{ \Illuminate\Support\Str::plural('staff member', $plan->max_staff) }}
                @else
                  Unlimited staff
                @endif
              </p>
              @if ($plan->trial_days)
                <p class="mt-0.5 text-ink-3">{{ $plan->trial_days }}-day trial</p>
              @endif
              <p class="mt-1 text-ink-3">
                {{ $plan->ai_monthly_credits === null ? 'Unlimited Mercho credits' : $plan->ai_monthly_credits.' Mercho credits / month' }}
              </p>
              <p class="mt-0.5 text-ink-3">{{ $plan->ai_max_images_per_request }} photos per Mercho request</p>
            </td>

            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Actions</span>
              <div class="space-y-2">
                @unless ($linked)
                  <form method="POST" action="{{ route('admin.plans.connect', $plan) }}">
                    @csrf
                    <button class="w-full rounded-xl bg-brand px-4 py-2.5 text-xs font-semibold text-brand-ink hover:bg-brand-hover">
                      Create on {{ $gateway }} and bill automatically
                    </button>
                  </form>
                @endunless

                <details class="group">
                  <summary class="cursor-pointer list-none rounded-xl border border-rule px-4 py-2 text-center text-xs font-semibold text-ink-2 hover:bg-surface">Change price</summary>
                  <form method="POST" action="{{ route('admin.plans.price', $plan) }}" class="mt-2 min-w-64 space-y-2">
                    @csrf
                    <div>
                      <label for="price-{{ $plan->slug }}" class="block text-[11px] font-semibold text-ink-3">New price (kobo)</label>
                      <input id="price-{{ $plan->slug }}" name="price_kobo" type="number" min="5000" step="100"
                             value="{{ old('price_kobo', $plan->price_kobo) }}"
                             class="mt-1 w-full rounded-xl border border-rule bg-card px-3 py-2 text-sm focus:border-brand focus:outline-none">
                    </div>

                    @if ($linked)
                      {{-- Off by default, because the gateway re-prices every current
                           subscriber the moment it is told to. This is the only way to
                           do that, and it says so on the button. --}}
                      <label class="flex items-start gap-2 text-[11px] text-warn-text">
                        <input type="checkbox" name="update_existing" value="1" class="mt-0.5 rounded border-rule-strong focus:border-brand">
                        <span>Also re-price current subscribers</span>
                      </label>
                    @endif

                    <button class="w-full rounded-xl border border-rule px-4 py-2.5 text-xs font-semibold text-ink-2 hover:bg-surface"
                            @if ($linked) onclick="return confirm('Change the price to this amount?')" @endif>
                      Save price
                    </button>
                  </form>
                </details>

                <details class="group">
                  <summary class="cursor-pointer list-none rounded-xl border border-rule px-4 py-2 text-center text-xs font-semibold text-ink-2 hover:bg-surface">Change Mercho limits</summary>
                  <form method="POST" action="{{ route('admin.plans.limits', $plan) }}" class="mt-2 min-w-64 space-y-2">
                    @csrf
                    <div>
                      <label for="ai-credits-{{ $plan->slug }}" class="block text-[11px] font-semibold text-ink-3">Monthly Mercho credits</label>
                      <input id="ai-credits-{{ $plan->slug }}" name="ai_monthly_credits" type="number" min="0" step="1"
                             value="{{ old('ai_monthly_credits', $plan->ai_monthly_credits) }}"
                             placeholder="Leave blank for unlimited"
                             class="mt-1 w-full rounded-xl border border-rule bg-card px-3 py-2 text-sm focus:border-brand focus:outline-none">
                      @error('ai_monthly_credits') <p class="mt-1 text-[11px] text-danger-text">{{ $message }}</p> @enderror
                    </div>
                    <div>
                      <label for="ai-images-{{ $plan->slug }}" class="block text-[11px] font-semibold text-ink-3">Photos per request</label>
                      <input id="ai-images-{{ $plan->slug }}" name="ai_max_images_per_request" type="number" min="1"
                             max="{{ config('ai.limits.absolute_max_photo_items', 20) }}" step="1"
                             value="{{ old('ai_max_images_per_request', $plan->ai_max_images_per_request) }}"
                             class="mt-1 w-full rounded-xl border border-rule bg-card px-3 py-2 text-sm focus:border-brand focus:outline-none">
                      @error('ai_max_images_per_request') <p class="mt-1 text-[11px] text-danger-text">{{ $message }}</p> @enderror
                    </div>
                    <button class="w-full rounded-xl border border-rule px-4 py-2.5 text-xs font-semibold text-ink-2 hover:bg-surface">
                      Save Mercho limits
                    </button>
                  </form>
                </details>
              </div>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @if ($plans->hasPages())
    <div class="mt-6">{{ $plans->links() }}</div>
  @endif
</x-admin.layout>