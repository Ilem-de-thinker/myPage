<!doctype html>
@php
  $platformName = \App\Domains\Admin\Models\PlatformSetting::value('platform_name', 'mypage.ng');
  $platformLogo = \App\Domains\Admin\Models\PlatformSetting::value('platform_logo_path');
  $adminNavigation = [
      ['route' => 'admin.index', 'pattern' => 'admin.index', 'label' => 'Overview', 'icon' => 'dashboard'],
      ['route' => 'admin.analytics', 'pattern' => 'admin.analytics', 'label' => 'Analytics', 'icon' => 'chart'],
      ['route' => 'admin.stores', 'pattern' => 'admin.stores*', 'label' => 'Stores', 'icon' => 'storefront'],
      ['route' => 'admin.badges', 'pattern' => 'admin.badges*', 'label' => 'Badges', 'icon' => 'badge'],
      ['route' => 'admin.transactions', 'pattern' => 'admin.transactions', 'label' => 'Transactions', 'icon' => 'receipt'],
      ['route' => 'admin.reports', 'pattern' => 'admin.reports*', 'label' => 'Reports', 'icon' => 'flag'],
      ['route' => 'admin.support', 'pattern' => 'admin.support*', 'label' => 'Support', 'icon' => 'life-buoy'],
      ['route' => 'admin.gateway-health', 'pattern' => 'admin.gateway-health', 'label' => 'Gateway health', 'icon' => 'shield'],
      ['route' => 'admin.features', 'pattern' => 'admin.features', 'label' => 'Features', 'icon' => 'spark'],
      ['route' => 'admin.settings', 'pattern' => 'admin.settings', 'label' => 'Settings', 'icon' => 'settings'],
      ['route' => 'admin.plans', 'pattern' => 'admin.plans*', 'label' => 'Plans', 'icon' => 'credit-card'],
      ['route' => 'admin.audit', 'pattern' => 'admin.audit', 'label' => 'Audit', 'icon' => 'database'],
  ];
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

<header class="border-b border-rule bg-card">
  <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6">
    <div class="flex items-center gap-4">
      <span class="flex items-center gap-2 font-display text-base font-extrabold tracking-tight">
        @if ($platformLogo)
          <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($platformLogo) }}" alt="" class="size-7 rounded-lg object-cover">
        @endif
        {{ $platformName }}
        <span class="ml-2 rounded-md bg-ink px-2 py-0.5 align-middle text-[10px] font-bold uppercase tracking-wide text-white">
          Ops
        </span>
      </span>

      <nav class="hidden items-center gap-1 text-xs font-medium xl:flex" aria-label="Admin navigation">
        @foreach ($adminNavigation as $item)
          @php($active = request()->routeIs($item['pattern']))
          <a href="{{ route($item['route']) }}"
             @class(['group inline-flex items-center gap-1.5 rounded-lg px-2.5 py-2 transition-colors', 'bg-brand-wash font-semibold text-brand-text' => $active, 'text-ink-2 hover:bg-tint hover:text-ink' => ! $active])
             @if($active) aria-current="page" @endif>
            <x-ui.icon :name="$item['icon']" class="size-3.5 shrink-0" />
            <span>{{ $item['label'] }}</span>
          </a>
        @endforeach
      </nav>
    </div>

    <div class="flex items-center gap-2 sm:gap-3">
      <button type="button" @click="adminNavOpen = true" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border border-rule text-ink-2 hover:bg-tint xl:hidden" aria-label="Open admin navigation" :aria-expanded="adminNavOpen">
        <x-ui.icon name="menu" size="size-5" />
      </button>
      <x-ui.theme-toggle />
      <a href="{{ route('dashboard') }}" class="hidden text-xs font-semibold text-ink-3 hover:text-ink sm:inline-flex sm:items-center sm:gap-1.5"><x-ui.icon name="storefront" class="size-3.5" />Seller view</a>
      <span class="hidden text-xs text-ink-3 sm:inline">{{ auth()->user()->name }}</span>
    </div>
  </div>
</header>

<div x-cloak x-show="adminNavOpen" x-transition.opacity @click="adminNavOpen = false" class="fixed inset-0 z-40 bg-ink/40 xl:hidden" aria-hidden="true"></div>
<aside x-cloak x-show="adminNavOpen" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" class="fixed inset-y-0 left-0 z-50 w-[min(20rem,calc(100vw-2rem))] overflow-y-auto border-r border-rule bg-card p-4 shadow-2xl xl:hidden" aria-label="Admin navigation">
  <div class="mb-4 flex items-center justify-between border-b border-rule pb-4">
    <span class="text-sm font-bold text-ink">Operations menu</span>
    <button type="button" @click="adminNavOpen = false" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl text-ink-2 hover:bg-tint" aria-label="Close admin navigation">
      <x-ui.icon name="close" class="size-5" />
    </button>
  </div>
  <nav @click="adminNavOpen = false" class="grid gap-1">
    @foreach ($adminNavigation as $item)
      <x-ui.nav-link :href="route($item['route'])" :label="$item['label']" :icon="$item['icon']" :active="request()->routeIs($item['pattern'])" />
    @endforeach
  </nav>
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
