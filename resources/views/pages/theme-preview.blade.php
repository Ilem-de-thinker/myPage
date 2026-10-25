@extends('layouts.platform')

@section('title', 'Theme preview — mypage.ng')

@section('content')
    <div x-data="themePreview(@jsExpr(['presets' => collect(config('themes.presets'))->map(fn (array $preset): array => [
        'label' => $preset['label'],
        'description' => $preset['description'],
        'business_types' => $preset['business_types'],
        'font_label' => $preset['font_label'] ?? 'Theme default',
        'composition' => $preset['composition'],
        'font' => $preset['font'],
    ])->all()]))" class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="flex flex-col justify-between gap-6 border-b border-rule pb-8 md:flex-row md:items-end">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand">Theme studio</p>
                <h1 class="mt-2 text-4xl font-extrabold tracking-tight text-ink">Find the right storefront rhythm.</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-ink-2">Preview the locked layout directions with a live merchant colour. The production storefront derives the complete palette from the same engine.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <label class="flex items-center gap-2 text-xs font-semibold text-ink-2">Brand colour <input type="color" x-model="color" class="size-11 cursor-pointer rounded-lg border border-rule"></label>
                <div class="flex rounded-xl border border-rule p-1" role="group" aria-label="Preview business type">
                    <button type="button" @click="selectMode('store')" :class="mode === 'store' ? 'bg-brand text-brand-ink' : 'text-ink-2'" class="min-h-11 rounded-lg px-3 text-xs font-bold">Store</button>
                    <button type="button" @click="selectMode('service')" :class="mode === 'service' ? 'bg-brand text-brand-ink' : 'text-ink-2'" class="min-h-11 rounded-lg px-3 text-xs font-bold">Service</button>
                </div>
                <div class="flex flex-wrap rounded-xl border border-rule p-1" role="group" aria-label="Preview width">
                    <template x-for="width in ['360', '390', '430', '768', 'desktop']" :key="width">
                        <button type="button" @click="viewport = width" :class="viewport === width ? 'bg-brand text-brand-ink' : 'text-ink-2'" class="min-h-11 rounded-lg px-2.5 text-[11px] font-bold" x-text="width === 'desktop' ? 'Desktop' : (width === '768' ? 'Tablet' : width + 'px')"></button>
                    </template>
                </div>
            </div>
        </div>

        <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (config('themes.presets') as $key => $preset)
                <button type="button" @click="selectPreset('{{ $key }}')" :class="preset === '{{ $key }}' ? 'border-brand bg-brand-wash' : 'border-rule bg-card'" class="rounded-2xl border p-4 text-left transition hover:border-brand-edge">
                    <span class="text-sm font-bold text-ink">{{ $preset['label'] }}</span>
                    <span class="mt-1 block text-xs leading-5 text-ink-3">{{ $preset['description'] }}</span>
                </button>
            @endforeach
        </div>

        <div class="mt-10 flex justify-center rounded-3xl bg-surface p-4 sm:p-8">
            <div :style="frameStyle" class="overflow-hidden rounded-2xl border border-rule bg-card shadow-xl transition-all">
                <div :data-theme-preset="preset" :data-preview-mode="mode" :style="previewStyle" class="min-h-[620px] text-ink">
                    <div class="flex items-center justify-between border-b border-rule px-5 py-4"><span class="font-bold">Aderonke Atelier</span><span class="rounded-full bg-brand px-3 py-2 text-[11px] font-bold text-brand-ink" x-text="mode === 'store' ? 'Shop' : 'Book'">Shop</span></div>
                    <div class="border-b border-rule px-5 py-3 text-[11px] text-ink-3"><span x-text="selected().label"></span><span aria-hidden="true"> · </span><span x-text="selected().font_label"></span><span aria-hidden="true"> · </span><span x-text="selected().composition.hero + ' hero'" class="capitalize"></span></div>
                    <template x-if="mode === 'store'">
                        <div>
                            <section class="grid gap-6 px-5 py-12 sm:grid-cols-2 sm:items-end"><div><p class="text-xs font-bold uppercase tracking-[0.15em] text-brand">New collection</p><h2 class="mt-3 font-heading text-4xl font-extrabold tracking-tight text-ink">Objects with a point of view.</h2></div><p class="text-sm leading-6 text-ink-2">A considered storefront for Nigerian makers, studios and businesses.</p></section>
                            <section class="border-t border-rule px-5 py-8"><div class="flex items-center justify-between"><h3 class="font-bold text-ink">Selected pieces</h3><span class="text-xs text-ink-3">Swipe on mobile</span></div><div class="mobile-snap-row mt-5 gap-3"><div class="rounded-2xl border border-rule bg-brand-wash p-4"><div class="aspect-[4/3] rounded-xl bg-brand-wash-2"></div><p class="mt-3 text-sm font-bold">Hand-finished tote</p><p class="mt-1 text-xs text-brand">₦48,000</p></div><div class="rounded-2xl border border-rule bg-brand-wash p-4"><div class="aspect-[4/3] rounded-xl bg-brand-wash-2"></div><p class="mt-3 text-sm font-bold">Studio ceramic set</p><p class="mt-1 text-xs text-brand">₦72,000</p></div></div></section>
                        </div>
                    </template>
                    <template x-if="mode === 'service'">
                        <div>
                            <section class="grid gap-6 px-5 py-12 sm:grid-cols-2 sm:items-end"><div><p class="text-xs font-bold uppercase tracking-[0.15em] text-brand">Private appointments</p><h2 class="mt-3 font-heading text-4xl font-extrabold tracking-tight text-ink">Work that leaves you lighter.</h2></div><p class="text-sm leading-6 text-ink-2">Clear services, considered care and a simple way to find a time in Lagos.</p></section>
                            <section class="border-t border-rule px-5 py-8"><div class="rounded-2xl border border-rule bg-brand-wash p-5"><p class="text-xs font-bold text-brand">Next available</p><div class="mt-2 flex items-end justify-between gap-4"><div><p class="font-heading text-2xl font-bold text-ink">Tuesday, 10:00</p><p class="mt-1 text-xs text-ink-2">60-minute consultation · ₦35,000</p></div><span class="rounded-xl bg-brand px-3 py-2 text-xs font-bold text-brand-ink">Book now</span></div></div></section>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
@endsection
