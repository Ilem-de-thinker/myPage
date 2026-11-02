@extends('layouts.platform')

@section('title', 'Updated policies — mypage.ng')

@section('content')
  <section class="section-y">
    <div class="shell max-w-2xl">
      <p class="t-meta">mypage.ng legal</p>
      <h1 class="t-display-2 mt-2 text-[var(--ink)]">We updated our policies</h1>
      <p class="t-body mt-4 text-[var(--ink-2)]">
        Please review and accept the current versions before continuing to your dashboard.
      </p>

      <div class="mt-8 space-y-3 rounded-[var(--radius)] border border-[var(--rule)] bg-[var(--card)] p-6">
        @foreach ($pending as $slug => $version)
          <div class="flex items-center justify-between gap-4 border-b border-[var(--rule)] pb-3 last:border-0 last:pb-0">
            <div>
              <p class="text-sm font-semibold text-[var(--ink)]">{{ ucfirst($slug) }} policy</p>
              <p class="text-xs text-[var(--ink-3)]">Version {{ $version }}</p>
            </div>
            <a href="{{ route('legal.policy', $slug) }}" class="text-xs font-semibold text-[var(--brand-text)] hover:underline">Read</a>
          </div>
        @endforeach
      </div>

      <form method="POST" action="{{ route('legal.consent.accept') }}" class="mt-6 space-y-4">
        @csrf
        <label class="flex items-start gap-3 text-xs text-[var(--ink-2)]">
          <input type="checkbox" name="accepted" value="1" required class="mt-0.5 size-4 rounded border-[var(--rule)]">
          <span>I have read and accept the updated Terms of Service and Privacy Policy.</span>
        </label>
        @error('accepted')
          <p class="text-xs text-[var(--danger-text)]">{{ $message }}</p>
        @enderror
        <button class="rounded-[var(--radius)] bg-[var(--brand)] px-5 py-3 text-sm font-bold text-[var(--brand-ink)]">Accept and continue</button>
      </form>
    </div>
  </section>
@endsection
