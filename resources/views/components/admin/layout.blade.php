<!doctype html>
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
<body class="min-h-screen bg-surface text-ink antialiased">

<header class="border-b border-rule bg-card">
  <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6">
    <div class="flex items-center gap-4">
      <span class="font-display text-base font-extrabold tracking-tight">
        mypage<span class="text-brand">.ng</span>
        <span class="ml-2 rounded-md bg-ink px-2 py-0.5 align-middle text-[10px] font-bold uppercase tracking-wide text-white">
          Ops
        </span>
      </span>

      <nav class="hidden items-center gap-1 text-xs font-medium md:flex">
        <a href="{{ route('admin.index') }}" class="rounded-lg px-3 py-1.5 text-ink-2 hover:bg-tint">Overview</a>
        <a href="{{ route('admin.stores') }}" class="rounded-lg px-3 py-1.5 text-ink-2 hover:bg-tint">Stores</a>
        <a href="{{ route('admin.badges') }}" class="rounded-lg px-3 py-1.5 text-ink-2 hover:bg-tint">Badges</a>
        <a href="{{ route('admin.transactions') }}" class="rounded-lg px-3 py-1.5 text-ink-2 hover:bg-tint">Transactions</a>
        <a href="{{ route('admin.payouts') }}" class="rounded-lg px-3 py-1.5 text-ink-2 hover:bg-tint">Payouts</a>
        <a href="{{ route('admin.escrows') }}" class="rounded-lg px-3 py-1.5 text-ink-2 hover:bg-tint">Escrow</a>
        <a href="{{ route('admin.features') }}" class="rounded-lg px-3 py-1.5 text-ink-2 hover:bg-tint">Features</a>
        <a href="{{ route('admin.plans') }}" class="rounded-lg px-3 py-1.5 text-ink-2 hover:bg-tint">Plans</a>
        <a href="{{ route('admin.audit') }}" class="rounded-lg px-3 py-1.5 text-ink-2 hover:bg-tint">Audit</a>
      </nav>
    </div>

    <div class="flex items-center gap-3">
      <x-ui.theme-toggle />
      <a href="{{ route('dashboard') }}" class="text-xs font-semibold text-ink-3 hover:text-ink">Seller view</a>
      <span class="text-xs text-ink-3">{{ auth()->user()->name }}</span>
    </div>
  </div>
</header>

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