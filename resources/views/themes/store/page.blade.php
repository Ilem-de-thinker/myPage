{{--
    Blocks render in the order the theme defines and only when they have content,
    so there is no enable/disable switch for a seller to manage and never an
    empty heading above an empty section.
--}}
<x-themes.shared.layout :page="$page" :structured-products="$products ?? null">
  @foreach ($blocks as $block)
    <x-dynamic-component
      :component="'themes.'.$page->theme->value.'.blocks.home.'.$block->key"
      :page="$page" :block="$block" :products="$products ?? null" />
  @endforeach
</x-themes.shared.layout>