@props(['page'])

@php
    /*
      The seller's tokens, printed into the document head.

      One component, used by every storefront page and by the admin preview,
      so the page a seller sees and the page a visitor sees are built from the
      same derivation (App\Shared\Support\ThemeTokens). The custom properties
      are written as a single inline <style> rather than a class per token:
      a stylesheet request cannot be cached per tenant, and a token that is
      only ever read through var() costs nothing to change at runtime.

      Both surfaces ship, not one. The inline :root block has the same
      specificity as the stylesheet's own dark rules, so a stylesheet-only
      switcher could never fire here; ThemeTokens emits the alternate surface
      behind [data-theme] instead, which is what the in-page toggle writes.

      The font <link> is emitted from the same call, because a font choice
      made in the customiser has to actually load before the page paints.
    */
    $theme = \App\Shared\Support\ThemeTokens::switchableCss($page);
@endphp

@if ($theme['font_link'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ $theme['font_link'] }}" rel="stylesheet">
@endif

<style>{!! $theme['css'] !!}</style>