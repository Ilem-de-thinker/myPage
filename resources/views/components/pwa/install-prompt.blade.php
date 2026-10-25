@props(['name', 'manifest', 'worker'])

<div x-data="pwaInstallPrompt"
     x-init="init()"
     data-worker="{{ $worker }}"
     class="relative z-[60]">
    <div x-cloak x-show="showInstall" x-transition class="fixed inset-x-4 bottom-4 rounded-2xl border border-[var(--brand-edge)] bg-[var(--card)] p-4 text-[var(--ink)] shadow-[var(--shadow-lift)] sm:left-auto sm:right-6 sm:max-w-sm">
        <div class="flex items-start gap-3">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[var(--brand)] font-bold text-[var(--brand-ink)]">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold">Add {{ $name }} to your home screen</p>
                <p x-show="!showIos" class="mt-1 text-xs leading-5 text-[var(--ink-2)]">Open it faster like an app, even when your connection is unreliable.</p>
                <p x-show="showIos" class="mt-1 text-xs leading-5 text-[var(--ink-2)]">Tap Share, then choose Add to Home Screen.</p>
                <div class="mt-3 flex gap-2">
                    <button x-show="!showIos" type="button" @click="install()" class="min-h-10 rounded-lg bg-[var(--brand)] px-3 py-2 text-xs font-bold text-[var(--brand-ink)]">Add app</button>
                    <button type="button" @click="dismiss()" class="min-h-10 rounded-lg border border-[var(--rule)] px-3 py-2 text-xs font-semibold text-[var(--ink-2)]">Not now</button>
                </div>
            </div>
        </div>
    </div>
    <div x-cloak x-show="updateReady" class="fixed inset-x-4 bottom-4 rounded-xl bg-[var(--ink)] px-4 py-3 text-xs font-semibold text-[var(--paper)] shadow-xl sm:left-auto sm:right-6 sm:max-w-sm">
        A new version is ready. <button type="button" onclick="window.location.reload()" class="ml-1 underline">Refresh</button>
    </div>
</div>
