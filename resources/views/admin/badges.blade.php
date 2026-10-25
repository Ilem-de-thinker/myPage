<x-admin.layout title="Badge review">
  <h1 class="font-display text-2xl font-extrabold tracking-tight">Verified badge</h1>
  <p class="mt-1 text-sm text-ink-2">Sellers who have proved they are a real business.</p>

  <div class="mt-5 flex gap-2 text-xs">
    @foreach (\App\Domains\Admin\Models\BadgeStatus::cases() as $status)
      <a href="{{ ($activeStatus ?? 'pending') === $status->value ? route('admin.badges') : route('admin.badges', ['status' => $status->value]) }}"
         @class([
             'rounded-lg px-3 py-1.5 font-semibold',
             'bg-ink text-white' => ($activeStatus ?? 'pending') === $status->value,
             'border border-rule text-ink-2 hover:bg-surface' => ($activeStatus ?? 'pending') !== $status->value,
         ])>
        {{ $status->label() }}
        @if ($count = $counts[$status->value] ?? 0)
          <span class="ml-1 opacity-70">{{ $count }}</span>
        @endif
      </a>
    @endforeach
  </div>

  <div class="mt-6 grid gap-4 lg:grid-cols-2">
    @forelse ($applications as $application)
      <div class="rounded-2xl border border-rule bg-card p-5">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-sm font-bold text-ink">{{ $application->business_name }}</p>
            <p class="mt-0.5 text-xs text-ink-3">
              {{ $application->storePage?->name }} &middot; /{{ $application->storePage?->slug }}
            </p>
            <p class="mt-0.5 text-xs text-ink-3">
              {{ $application->seller?->name }} &middot; {{ $application->seller?->email }}
              @if ($application->seller?->phone)
                &middot; {{ $application->seller->phone }}
              @endif
            </p>
          </div>

          <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $application->status->badgeClasses() }}">
            {{ $application->status->label() }}
          </span>
        </div>

        <dl class="mt-4 grid gap-2 border-t border-rule pt-3 text-xs sm:grid-cols-2">
          <div><dt class="text-ink-3">Registered legal name</dt><dd class="font-semibold text-ink">{{ $application->registered_legal_name ?: 'Not supplied' }}</dd></div>
          <div><dt class="text-ink-3">CAC/RC or ID number</dt><dd class="font-mono font-semibold text-ink">{{ $application->registration_number ?: 'Not supplied' }}</dd></div>
          <div><dt class="text-ink-3">Document expiry</dt><dd class="font-semibold text-ink">{{ $application->document_expires_at?->format('j M Y') ?: 'Not applicable / not supplied' }}</dd></div>
          <div><dt class="text-ink-3">Retention until</dt><dd class="font-semibold text-ink">{{ $application->retention_until?->format('j M Y') ?: '—' }}</dd></div>
        </dl>

        <dl class="mt-4 space-y-1 border-t border-rule pt-3 text-xs">
          <div class="flex justify-between">
            <dt class="text-ink-3">Document</dt>
            <dd class="font-semibold text-ink">{{ strtoupper($application->document_type) }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-ink-3">Submitted</dt>
            <dd class="font-semibold text-ink">{{ $application->submitted_at?->format('j M Y') ?? '—' }}</dd>
          </div>
        </dl>

        @if ($application->note)
          <p class="mt-3 rounded-lg bg-surface p-3 text-xs text-ink-2">{{ $application->note }}</p>
        @endif

        @if ($application->hasDocument())
          <a href="{{ route('admin.badges.document', $application) }}" target="_blank" rel="noopener"
             class="mt-3 inline-block text-xs font-semibold text-brand-text hover:underline">
            View uploaded KYC document securely
          </a>
        @else
          <p class="mt-3 text-xs font-semibold text-danger-text">The retained document is no longer available.</p>
        @endif

        @if ($application->isPending())
          <div class="mt-4 space-y-3 border-t border-rule pt-4">
            <form method="POST" action="{{ route('admin.badges.decide', $application) }}">
              @csrf
              <input type="hidden" name="decision" value="approve">
              <label class="block">
                <span class="text-xs font-semibold text-ink">Note (optional)</span>
                <input type="text" name="note" class="mt-1 w-full rounded-lg border border-rule px-3 py-2 text-sm">
              </label>
              <button class="mt-2 w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-semibold text-white hover:bg-emerald-700">
                Approve and badge the page
              </button>
            </form>

            <form method="POST" action="{{ route('admin.badges.decide', $application) }}">
              @csrf
              <input type="hidden" name="decision" value="reject">
              <label class="block">
                <span class="text-xs font-semibold text-ink">Why are you rejecting?</span>
                <textarea name="rejection_reason" rows="2" required
                          class="mt-1 w-full rounded-lg border border-rule px-3 py-2 text-sm"
                          placeholder="The CAC certificate is for a different business name"></textarea>
                <span class="mt-1 block text-[11px] text-ink-3">The applicant sees this, so it has to be usable.</span>
              </label>
              <button class="mt-2 w-full rounded-xl border border-danger-edge px-4 py-2.5 text-xs font-semibold text-danger-text hover:bg-danger-wash">
                Reject
              </button>
            </form>
          </div>
        @else
          <div class="mt-4 border-t border-rule pt-3 text-xs">
            @if ($application->rejection_reason)
              <p class="text-ink-2">Rejected: {{ $application->rejection_reason }}</p>
            @endif
            <p class="mt-1 text-ink-3">
              Decided {{ $application->reviewed_at?->diffForHumans() }}
              @if ($application->reviewed_by_seller_id)
                by admin #{{ $application->reviewed_by_seller_id }}
              @endif
            </p>
          </div>
        @endif
      </div>
    @empty
      <div class="col-span-full rounded-2xl border border-rule bg-card px-6 py-16 text-center">
        <p class="text-sm text-ink-3">Nothing waiting.</p>
        <p class="mt-1 text-xs text-ink-3">Badge applications appear here as sellers submit them.</p>
      </div>
    @endforelse
  </div>

  @if ($applications->hasPages())
    <div class="mt-6">{{ $applications->links() }}</div>
  @endif
</x-admin.layout>
