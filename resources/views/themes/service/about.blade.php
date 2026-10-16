{{--
    Ported from new_ui/service — about.html is two sections: the studio itself
    and the standards it works to. Both live in the one "story" block the theme
    declares for this page, so a seller who writes nothing still gets a full page.
--}}
<x-themes.shared.layout :page="$page">
  @foreach ($blocks as $block)
    <x-dynamic-component
      :component="'themes.'.$page->theme->value.'.blocks.about.'.$block->key"
      :page="$page" :block="$block" />
  @endforeach
</x-themes.shared.layout>