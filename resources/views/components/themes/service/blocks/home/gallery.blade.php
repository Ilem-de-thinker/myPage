@props(['page', 'block'])

@php
    // The gallery shows the photos the seller already uploaded against their
    // services rather than a second upload form. One set of images, used twice.
    $photos = \App\Domains\Booking\Models\Service::withoutGlobalScopes()
        ->where('store_page_id', $page->id)
        ->where('is_active', true)
        ->whereNotNull('image_path')
        ->orderBy('position')
        ->get();

    $heading = $block->field('heading');
    $preset = $page->settings['theme_preset'] ?? 'minimal-swiss';
@endphp

@if ($photos->isNotEmpty())
  <section class="shell section-y">
    @if ($heading)
      <h2 class="t-display-2 mb-8">{{ $heading }}</h2>
    @endif

    {{-- No caption bar laid over the picture: the name sits under the frame,
         so the photograph is never half covered by a dark rectangle. --}}
    <div data-gallery-style="{{ $preset }}" class="theme-gallery grid grid-cols-2 gap-4 md:grid-cols-3 md:gap-5">
      @foreach ($photos as $service)
        <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.service', ['slug' => $page->slug, 'serviceSlug' => $service->slug]) }}"
           class="plate group block">
          <div class="plate-media bg-[var(--brand-wash)]">
            <img src="{{ \Illuminate\Support\Facades\Storage::url($service->image_path) }}"
                 alt="{{ $service->name }}" width="600" height="600" class="aspect-square w-full object-cover" loading="lazy" decoding="async">
          </div>
          <p class="t-title mt-3">{{ $service->name }}</p>
        </a>
      @endforeach
    </div>
  </section>
@endif
