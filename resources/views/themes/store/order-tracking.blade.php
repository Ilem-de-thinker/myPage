<x-themes.shared.layout :page="$page">
  @foreach ($page->sections->where('page', 'order-tracking')->where('is_enabled', true) as $section)
    <x-dynamic-component
      :component="'themes.'.$page->theme->value.'.sections.order-tracking.'.$section->key"
      :page="$page" :section="$section" />
  @endforeach
</x-themes.shared.layout>