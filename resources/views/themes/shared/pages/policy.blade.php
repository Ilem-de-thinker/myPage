@extends('layouts.platform')

@section('title', $policy['title'].' — mypage.ng')
@section('description', $policy['summary'])

@section('content')
  <section class="section-y">
    <div class="shell max-w-3xl">
      <p class="t-meta">mypage.ng legal</p>
      <h1 class="t-display-2 mt-2 text-[var(--ink)]">{{ $policy['title'] }}</h1>
      <p class="t-body mt-4 max-w-2xl text-[var(--ink-2)]">{{ $policy['summary'] }}</p>
      <p class="mt-3 text-xs text-[var(--ink-3)]">
        Effective {{ $slug === 'terms' ? $termsVersion : ($slug === 'privacy' ? $privacyVersion : now()->toDateString()) }}.
      </p>

      <div class="mt-10 space-y-8">
        @foreach ($policy['sections'] as $section)
          <section>
            <h2 class="text-base font-bold text-[var(--ink)]">{{ $section['heading'] }}</h2>
            <p class="t-body mt-2 leading-7 text-[var(--ink-2)]">{{ $section['body'] }}</p>
          </section>
        @endforeach
      </div>

      @if ($slug === 'privacy')
        <a href="{{ route('legal.data-request') }}" class="mt-10 inline-flex rounded-[var(--radius)] bg-[var(--brand)] px-5 py-3 text-sm font-bold text-[var(--brand-ink)]">Make a data request</a>
      @endif
    </div>
  </section>
@endsection
