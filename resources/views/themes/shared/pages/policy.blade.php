@extends('layouts.platform')

@section('title', $policy['title'].' — mypage.ng')
@section('description', $policy['summary'])

@section('content')
  <section class="section-y">
    <div class="shell max-w-5xl">
      @if ($policy['is_draft'] ?? false)
        <div class="mb-6 rounded-[var(--radius)] border border-[var(--warn-edge)] bg-[var(--warn-wash)] px-4 py-3 text-xs leading-5 text-[var(--warn-text)]">
          This page is a working draft prepared for review by a qualified Nigerian lawyer. It is not final legal advice.
        </div>
      @endif

      <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_16rem]">
        <div class="min-w-0">
          <p class="t-meta">mypage.ng legal</p>
          <h1 class="t-display-2 mt-2 text-[var(--ink)]">{{ $policy['title'] }}</h1>
          <p class="t-body mt-4 max-w-2xl text-[var(--ink-2)]">{{ $policy['summary'] }}</p>
          <p class="mt-3 text-xs text-[var(--ink-3)]">
            Effective {{ $effectiveAt?->format('j F Y') ?? ($slug === 'terms' ? $termsVersion : ($slug === 'privacy' ? $privacyVersion : now()->toDateString())) }}.
          </p>

          <div class="mt-10 space-y-8">
            @foreach ($policy['sections'] as $section)
              <section id="section-{{ $loop->index }}" class="scroll-mt-24">
                <h2 class="text-base font-bold text-[var(--ink)]">{{ $section['heading'] }}</h2>
                <p class="t-body mt-2 leading-7 text-[var(--ink-2)]">{{ $section['body'] }}</p>
              </section>
            @endforeach
          </div>

          @if ($slug === 'privacy')
            <a href="{{ route('legal.data-request') }}" class="mt-10 inline-flex rounded-[var(--radius)] bg-[var(--brand)] px-5 py-3 text-sm font-bold text-[var(--brand-ink)]">Make a data request</a>
          @endif
        </div>

        <aside class="hidden lg:block">
          <div class="sticky top-24 rounded-[var(--radius)] border border-[var(--rule)] bg-[var(--card)] p-5">
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--ink-3)]">On this page</p>
            <nav class="mt-3 space-y-2 text-xs" aria-label="Table of contents">
              @foreach ($policy['sections'] as $section)
                <a href="#section-{{ $loop->index }}" class="block leading-5 text-[var(--ink-2)] hover:text-[var(--ink)]">{{ $section['heading'] }}</a>
              @endforeach
            </nav>
          </div>
        </aside>
      </div>
    </div>
  </section>
@endsection
