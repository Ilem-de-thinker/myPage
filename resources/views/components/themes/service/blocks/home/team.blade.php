@props(['page', 'block'])

@php
    // Staff the seller has paused are still in their dashboard but must not be
    // offered as the person a customer will sit with.
    $staff = \App\Domains\Booking\Models\StaffMember::withoutGlobalScopes()
        ->where('store_page_id', $page->id)
        ->where('is_active', true)
        ->orderBy('name')
        ->get();

    $heading = $block->field('heading');
@endphp

@if ($staff->isNotEmpty())
  <section class="shell section-y">
    @if ($heading)
      <h2 class="t-display-2 mb-8">{{ $heading }}</h2>
    @endif

    {{-- People, left aligned in a row. The circle carries the face; everything
         around it is the name and what they do. --}}
    <div class="grid grid-cols-2 gap-x-4 gap-y-9 md:grid-cols-4 md:gap-x-6">
      @foreach ($staff as $index => $member)
        <div @class([
          'border-t border-[var(--rule)] pt-4',
          'md:border-l md:border-[var(--rule)] md:pl-5' => $index > 0,
          'border-l border-[var(--rule)] pl-4' => $index % 2 === 1,
        ])>
          @if ($member->photo_path)
            <img src="{{ \Illuminate\Support\Facades\Storage::url($member->photo_path) }}"
                 alt="{{ $member->name }}" class="h-20 w-20 rounded-full object-cover" loading="lazy" decoding="async">
          @else
            <span class="flex h-20 w-20 items-center justify-center rounded-full bg-[var(--brand-wash)] text-2xl font-bold"
                  style="color: var(--brand-text)" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($member->name, 0, 1)) }}</span>
          @endif

          <p class="t-title mt-3">{{ $member->name }}</p>

          @if ($member->bio)
            <p class="t-body mt-1 line-clamp-3 text-[color:var(--ink-2)]">{{ \Illuminate\Support\Str::limit($member->bio, 90) }}</p>
          @endif
        </div>
      @endforeach
    </div>
  </section>
@endif
