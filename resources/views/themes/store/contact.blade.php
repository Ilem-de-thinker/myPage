{{--
    The contact details are the seller's business details, so they render whether
    or not they wrote anything on this page. Only the intro copy is a block.
--}}
<x-themes.shared.layout :page="$page">
  @php
    // The intro block is drawn first, and it is also the only block on this page
    // that carries a field the details below cannot get any other way — the
    // opening hours. Handed on rather than re-queried.
    $introBlock = $blocks->firstWhere('key', 'intro');
  @endphp

  @foreach ($blocks as $block)
    @if ($block->key === 'intro')
      <x-themes.store.blocks.contact.intro :page="$page" :block="$block" />
    @endif
  @endforeach

  <x-themes.store.blocks.contact.details :page="$page" :block="$introBlock" />
</x-themes.shared.layout>