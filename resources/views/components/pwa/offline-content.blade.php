@props(['name', 'whatsapp' => null])

<section class="mx-auto flex min-h-[70dvh] max-w-2xl items-center px-4 py-16 sm:px-6">
    <div class="w-full rounded-3xl border border-[var(--rule)] bg-[var(--card)] p-8 text-center shadow-[var(--shadow-lift)] sm:p-12">
        <div class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-[var(--brand)] text-2xl font-extrabold text-[var(--brand-ink)]" aria-hidden="true">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</div>
        <p class="mt-6 text-xs font-bold uppercase tracking-[0.15em] text-[var(--brand-text)]">{{ $name }}</p>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-[var(--ink)]">You’re offline</h1>
        <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-[var(--ink-2)]">The page will be available again when your connection returns. Your saved app is still installed and ready.</p>
        <div class="mt-7 flex flex-wrap justify-center gap-3">
            <button type="button" onclick="window.location.reload()" class="min-h-11 rounded-xl bg-[var(--brand)] px-5 py-3 text-xs font-bold text-[var(--brand-ink)]">Try again</button>
            @if ($whatsapp)
                <a href="https://wa.me/{{ preg_replace('/\D+/', '', $whatsapp) }}" class="inline-flex min-h-11 items-center rounded-xl border border-[var(--brand-edge)] bg-[var(--brand-wash)] px-5 py-3 text-xs font-bold text-[var(--brand-text)]">WhatsApp {{ $name }}</a>
            @endif
        </div>
    </div>
</section>
