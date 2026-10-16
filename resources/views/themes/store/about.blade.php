<x-themes.shared.layout :page="$page">
  {{--
    Blocks render in the order the theme defines and only when they have content,
    so there is no enable/disable switch for a seller to manage and never an
    empty heading above an empty section.
  --}}
  @foreach ($blocks as $block)
    <x-dynamic-component
      :component="'themes.'.$page->theme->value.'.blocks.about.'.$block->key"
      :page="$page" :block="$block" />
  @endforeach
</x-themes.shared.layout>