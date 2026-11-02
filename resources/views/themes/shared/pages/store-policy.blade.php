<x-themes.shared.layout :page="$page">
  <div class="shell max-w-3xl py-16">
    <p class="text-xs font-bold uppercase tracking-[0.18em] text-[var(--brand-text)]">{{ $page->name }}</p>
    <h1 class="t-display-2 mt-2 text-[var(--ink)]">{{ $policy['title'] }}</h1>
    @if ($policy['summary'])
      <p class="t-body mt-4 text-[var(--ink-2)]">{{ $policy['summary'] }}</p>
    @endif

    <div class="mt-10 space-y-8">
      @foreach ($policy['sections'] as $section)
        <section>
          <h2 class="text-base font-bold text-[var(--ink)]">{{ $section['heading'] }}</h2>
          <p class="t-body mt-2 leading-7 text-[var(--ink-2)]">{{ $section['body'] }}</p>
        </section>
      @endforeach
    </div>

    <div class="mt-10 flex flex-wrap gap-4 border-t border-[var(--rule)] pt-6 text-xs text-[var(--ink-3)]">
      <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.contact', ['slug' => $page->slug]) }}" class="hover:text-[var(--ink)]">Contact this store</a>
      <a href="{{ \App\Shared\Support\StoreUrl::platform('legal.policy', ['policy' => 'terms']) }}" class="hover:text-[var(--ink)]">Platform terms</a>
      <a href="{{ \App\Shared\Support\StoreUrl::platform('legal.policy', ['policy' => 'privacy']) }}" class="hover:text-[var(--ink)]">Platform privacy</a>
    </div>
  </div>
</x-themes.shared.layout>
