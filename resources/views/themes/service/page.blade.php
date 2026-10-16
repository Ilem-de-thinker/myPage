{{--
    Blocks render in the order the theme defines and only when they have content,
    so there is no enable/disable switch for a seller to manage and never an
    empty heading above an empty section.

    Ported from new_ui/service — index.html is this page's five sections, and
    each one is either a block (hero, services, booking, reviews) or real rows
    the seller already has (team, gallery, about, contact). The theme decides
    which is which; nothing here is draggable.
--}}
<x-themes.shared.layout :page="$page">
  @foreach ($blocks as $block)
    <x-dynamic-component
      :component="'themes.'.$page->theme->value.'.blocks.home.'.$block->key"
      :page="$page" :block="$block" />
  @endforeach
</x-themes.shared.layout>