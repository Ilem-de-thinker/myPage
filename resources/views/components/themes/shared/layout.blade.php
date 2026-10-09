@props(['page'])

@php $fonts = config('themes.font_pairs.'.($page->font_pair ?? 'modern')); @endphp
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $title ?? $page->name }}</title>
  <meta name="description" content="{{ $description ?? $page->tagline }}">
  <link rel="canonical" href="{{ url()->current() }}">
  <meta property="og:title" content="{{ $page->name }}">
  <meta property="og:description" content="{{ $description ?? $page->tagline }}">
  <style>
    :root{
      --brand: {{ $page->brand_color }};
      --brand-contrast: {{ \App\Shared\Support\Color::contrast($page->brand_color) }};
      --font-heading-stack: {!! $fonts['heading'] !!};
      --font-body-stack: {!! $fonts['body'] !!};
    }
  </style>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-slate-900" style="font-family: var(--font-body-stack)">
  <nav class="bg-white border-b sticky top-0 z-10">
    <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
      <a href="{{ url('/') }}" class="font-bold">{{ $page->name }}</a>
      <div class="flex gap-4 text-sm">
        <a href="{{ url('/') }}">Home</a>
        @if ($page->theme->value === 'store')
          <a href="{{ url('/products') }}">Products</a>
        @else
          <a href="{{ url('/services') }}">Services</a>
        @endif
        <a href="{{ url('/about') }}">About</a>
        <a href="{{ url('/contact') }}">Contact</a>
      </div>
    </div>
  </nav>
  {{ $slot }}
  <footer class="border-t mt-16 py-6 text-center text-xs text-slate-400">
    {{ $page->name }} · Powered by mypage.ng
  </footer>
</body>
</html>
