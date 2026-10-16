<x-admin.layout title="Plans">
  <h1 class="font-display text-2xl font-extrabold tracking-tight">Plans</h1>
  <p class="mt-1 text-sm text-ink-2">
    What each plan costs, and whether {{ $gateway }} agrees. A plan not connected
    to the gateway is charged by hand, one period at a time.
  </p>

  <div class="mt-6 space-y-4">
    @foreach ($plans as $plan)
      @php
        $linked = $plan->gateway_plan_code !== null;
        $remote = $gatewayPlans[$plan->getKey()] ?? null;
        // Null remote on a linked plan means the gateway could not be read at all.
        $unreachable = $linked && $remote === null;
        $drift = $remote !== null && (int) $remote['amount_kobo'] !== $plan->price_kobo;
        $intervalDrift = $remote !== null && $remote['interval'] !== $plan->interval;
      @endphp

      <div class="rounded-2xl border {{ $drift || $intervalDrift || $unreachable ? 'border-danger-edge' : 'border-rule' }} bg-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0">
            <p class="text-sm font-bold text-ink">{{ $plan->name }}</p>
            <p class="mt-0.5 text-xs text-ink-3">{{ $plan->description }}</p>
            <p class="mt-1 text-[11px] text-ink-3">
              {{ $plan->slug }}
              @if ($plan->max_products !== null)
                · up to {{ $plan->max_products }} products
              @else
                · unlimited products
              @endif
              @if ($plan->trial_days)
                · {{ $plan->trial_days }}-day trial
              @endif
            </p>
          </div>

          <div class="text-right">
            <p class="text-xl font-extrabold text-ink">{{ \App\Shared\Support\Money::format($plan->price_kobo) }}</p>
            <p class="text-[11px] text-ink-3">per {{ rtrim($plan->interval, 'ly') }}</p>
          </div>
        </div>

        {{-- The whole point of the page: what we quote against what is charged. --}}
        <div class="mt-4 rounded-xl border border-rule px-4 py-3 text-xs">
          @if ($unreachable)
            <p class="font-semibold text-danger-text">Could not read this plan from {{ $gateway }}.</p>
            <p class="mt-1 text-ink-3">
              The price below is what we quote, but it has not been confirmed against the gateway,
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

        <div class="mt-4 flex flex-wrap items-end gap-3">
          @unless ($linked)
            <form method="POST" action="{{ route('admin.plans.connect', $plan) }}">
              @csrf
              <button class="rounded-xl bg-brand px-4 py-2.5 text-xs font-semibold text-white hover:bg-brand-hover">
                Create on {{ $gateway }} and bill automatically
              </button>
            </form>
          @endunless

          <form method="POST" action="{{ route('admin.plans.price', $plan) }}"
                class="flex flex-wrap items-end gap-2">
            @csrf
            <div>
              <label for="price-{{ $plan->slug }}" class="block text-[11px] font-semibold text-ink-3">New price (kobo)</label>
              <input id="price-{{ $plan->slug }}" name="price_kobo" type="number" min="5000" step="100"
                     value="{{ old('price_kobo', $plan->price_kobo) }}"
                     class="mt-1 w-40 rounded-xl border border-rule bg-card px-3 py-2 text-sm focus:border-brand focus:outline-none">
            </div>

            @if ($linked)
              {{-- Off by default, because the gateway re-prices every current
                   subscriber the moment it is told to. This is the only way to
                   do that, and it says so on the button. --}}
              <label class="flex items-center gap-2 pb-2.5 text-[11px] text-warn-text">
                <input type="checkbox" name="update_existing" value="1"
                       class="rounded border-rule-strong focus:border-brand">
                Also re-price current subscribers
              </label>
            @endif

            <button class="rounded-xl border border-rule px-4 py-2.5 text-xs font-semibold text-ink-2 hover:bg-surface"
                    @if ($linked) onclick="return confirm('Change the price to this amount?')" @endif>
              Save price
            </button>
          </form>
        </div>
      </div>
    @endforeach
  </div>
</x-admin.layout>