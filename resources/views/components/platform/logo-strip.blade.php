@props([
    // Real merchants, passed in. Read from the database by the page so a fresh
    // install shows one business rather than a row of invented ones.
    'merchants' => [],
    'caption' => null,
])

@php
    $caption ??= $merchants->isNotEmpty()
        ? 'Trusted by verified merchants across Nigeria'
        : 'Be the first verified merchant on mypage.ng';
@endphp

@if ($merchants->isNotEmpty())
    <section class="py-12 border-y border-[var(--rule)] bg-[var(--surface)]/50">
        <div class="shell">
            <p class="t-meta text-center mb-6">{{ $caption }}</p>
            <div @class([
                'grid gap-6 items-center justify-items-center opacity-85',
                'grid-cols-2' => $merchants->count() <= 2,
                'sm:grid-cols-3' => $merchants->count() === 3,
                'sm:grid-cols-4' => $merchants->count() === 4,
                'sm:grid-cols-5' => $merchants->count() === 5,
                'lg:grid-cols-6' => $merchants->count() >= 6,
            ])>
                @foreach ($merchants as $merchant)
                    <a href="{{ \App\Shared\Support\StoreUrl::home($merchant) }}" class="text-center block">
                        <span class="font-extrabold text-sm tracking-tight text-[var(--ink)] block truncate">{{ $merchant->name }}</span>
                        <span class="t-meta text-[10px]">{{ $merchant->locationLabel() }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif