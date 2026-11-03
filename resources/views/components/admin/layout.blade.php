<!doctype html>
@php
  $platformName = \App\Domains\Admin\Models\PlatformSetting::value('platform_name', 'mypage.ng');
  $platformLogo = \App\Domains\Admin\Models\PlatformSetting::value('platform_logo_path');
  $adminNavigation = array_values(array_filter([
      ['route' => 'admin.index', 'pattern' => 'admin.index', 'label' => 'Overview', 'icon' => 'dashboard'],
      ['route' => 'admin.analytics', 'pattern' => 'admin.analytics', 'label' => 'Analytics', 'icon' => 'chart'],
      ['route' => 'admin.stores', 'pattern' => 'admin.stores*', 'label' => 'Stores', 'icon' => 'storefront'],
      ['route' => 'admin.badges', 'pattern' => 'admin.badges*', 'label' => 'Badges', 'icon' => 'badge'],
      ['route' => 'admin.transactions', 'pattern' => 'admin.transactions', 'label' => 'Transactions', 'icon' => 'receipt'],
      ['route' => 'admin.reports', 'pattern' => 'admin.reports*', 'label' => 'Reports', 'icon' => 'flag'],
      ['route' => 'admin.support', 'pattern' => 'admin.support*', 'label' => 'Support', 'icon' => 'life-buoy'],
      ['route' => 'admin.data-requests', 'pattern' => 'admin.data-requests*', 'label' => 'Data requests', 'icon' => 'shield'],
      ['route' => 'admin.gateway-health', 'pattern' => 'admin.gateway-health', 'label' => 'Gateway health', 'icon' => 'shield', 'core' => true],
      ['route' => 'admin.features', 'pattern' => 'admin.features', 'label' => 'Features', 'icon' => 'spark', 'core' => true],
      ['route' => 'admin.settings', 'pattern' => 'admin.settings', 'label' => 'Settings', 'icon' => 'settings', 'core' => true],
      ['route' => 'admin.policies', 'pattern' => 'admin.policies*', 'label' => 'Policies', 'icon' => 'receipt', 'core' => true],
      ['route' => 'admin.ai-settings', 'pattern' => 'admin.ai-settings*', 'label' => 'AI', 'icon' => 'spark', 'core' => true],
      ['route' => 'admin.plans', 'pattern' => 'admin.plans*', 'label' => 'Plans', 'icon' => 'credit-card', 'core' => true],
      ['route' => 'admin.manual-subscriptions', 'pattern' => 'admin.manual-subscriptions*', 'label' => 'Manual transfers', 'icon' => 'receipt', 'core' => true],
      ['route' => 'admin.audit', 'pattern' => 'admin.audit', 'label' => 'Audit', 'icon' => 'database'],
      ['route' => 'admin.team', 'pattern' => 'admin.team*', 'label' => 'Team', 'icon' => 'users', 'core' => true],
  ], fn (array $item): bool => ! ($item['core'] ?? false) || auth()->user()->isCoreAdmin()));
@endphp
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  {{-- An internal panel has no business in a search index. --}}
  <meta name="robots" content="noindex, nofollow">
  <title>{{ $title ?? 'Admin' }} — mypage.ng ops</title>
  <x-ui.theme-boot />
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="{ adminNavOpen: false }" @keydown.escape.window="adminNavOpen = false" class="min-h-screen bg-surface text-ink antialiased">

<header class="sticky top-0 z-30 border-b border-rule bg-card">
  {{-- Secondary bar: identity, account, and view switching. --}}
  <div class="border-b border-rule bg-surface/70">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-2.5 sm:px-6">
      <a href="{{ route('admin.index') }}" class="flex items-center gap-2 font-display text-base font-extrabold tracking-tight">
        @if ($platformLogo)
          <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($platformLogo) }}" alt="" class="size-7 rounded-lg object-cover">
        @endif
        {{ $platformName }}
        <span class="ml-2 rounded-md bg-ink px-2 py-0.5 align-middle text-[10px] font-bold uppercase tracking-wide text-paper">
          Ops
        </span>
      </a>

      <div class="flex items-center gap-2 sm:gap-3">
        <span class="hidden text-xs text-ink-3 sm:inline">{{ auth()->user()->name }}</span>
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-3 hover:text-ink">
          <x-ui.icon name="storefront" class="size-3.5" />
          <span class="hidden sm:inline">Seller view</span>
        </a>
        <x-ui.theme-toggle />
      </div>
    </div>
  </div>

  {{-- Primary bar: every admin destination, with the current one highlighted. --}}
  <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 sm:px-6">
    <button type="button" @click="adminNavOpen = true" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border border-rule text-ink-2 hover:bg-tint xl:hidden" aria-label="Open admin navigation" :aria-expanded="adminNavOpen">
      <x-ui.icon name="menu" class="size-5" />
    </button>
    <span class="text-xs font-semibold text-ink-3 xl:hidden">Operations</span>

    <nav class="hidden min-w-0 flex-1 items-center gap-1 overflow-x-auto py-2 text-xs font-medium xl:flex" aria-label="Primary admin navigation">
      @foreach ($adminNavigation as $item)
        @php($active = request()->routeIs($item['pattern']))
        <a href="{{ route($item['route']) }}"
           @class([
               'group inline-flex shrink-0 items-center gap-1.5 rounded-lg px-2.5 py-2 transition-colors',
               'bg-brand-wash font-semibold text-brand-text' => $active,
               'text-ink-2 hover:bg-tint hover:text-ink' => ! $active,
           ])
           @if($active) aria-current="page" @endif>
          <x-ui.icon :name="$item['icon']" class="size-3.5 shrink-0 {{ $active ? 'text-brand-text' : 'text-ink-3 group-hover:text-ink-2' }}" />
          <span>{{ $item['label'] }}</span>
        </a>
      @endforeach
    </nav>
  </div>
</header>

<div x-cloak x-show="adminNavOpen" x-transition.opacity @click="adminNavOpen = false" class="fixed inset-0 z-40 bg-black/50 xl:hidden" aria-hidden="true"></div>
<aside x-cloak x-show="adminNavOpen"
       x-transition:enter="transition duration-200 ease-out"
       x-transition:enter-start="-translate-x-full"
       x-transition:enter-end="translate-x-0"
       x-transition:leave="transition duration-150 ease-in"
       x-transition:leave-start="translate-x-0"
       x-transition:leave-end="-translate-x-full"
       class="fixed inset-y-0 left-0 z-50 flex w-[min(20rem,calc(100vw-2rem))] flex-col border-r border-rule bg-card shadow-2xl xl:hidden"
       role="dialog" aria-modal="true" aria-label="Admin navigation">
  <div class="flex items-center justify-between border-b border-rule px-4 py-4">
    <span class="flex items-center gap-2 font-display text-sm font-extrabold text-ink">
      {{ $platformName }}
      <span class="rounded-md bg-ink px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-paper">Ops</span>
    </span>
    <button type="button" @click="adminNavOpen = false" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl text-ink-2 hover:bg-tint" aria-label="Close admin navigation">
      <x-ui.icon name="close" class="size-5" />
    </button>
  </div>

  <div class="flex-1 overflow-y-auto p-4">
    <p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-wider text-ink-3">Primary</p>
    <nav @click="adminNavOpen = false" class="grid gap-1" aria-label="Primary admin navigation">
      @foreach ($adminNavigation as $item)
        <x-ui.nav-link :href="route($item['route'])" :label="$item['label']" :icon="$item['icon']" :active="request()->routeIs($item['pattern'])" />
      @endforeach
    </nav>
  </div>

  <div class="border-t border-rule p-4">
    <p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-wider text-ink-3">Secondary</p>
    <nav class="grid gap-1" aria-label="Secondary admin navigation">
      <x-ui.nav-link :href="route('dashboard')" label="Seller view" icon="storefront" />
    </nav>
    <div class="mt-4 flex items-center justify-between gap-3 px-3">
      <span class="truncate text-xs text-ink-3">{{ auth()->user()->name }}</span>
      <x-ui.theme-toggle />
    </div>
  </div>
</aside>

<main class="mx-auto max-w-7xl px-4 py-6 sm:px-8 sm:py-8">
  @if (session('status'))
    <div class="mb-5 rounded-xl border border-ok-edge bg-ok-wash px-4 py-3 text-sm text-ok-text">
      {{ session('status') }}
    </div>
  @endif

  @if ($errors->any())
    <div class="mb-5 rounded-xl border border-danger-edge bg-danger-wash px-4 py-3 text-sm text-danger-text">
      <ul class="space-y-1">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  {{ $slot }}
</main>
</body>
</html>
