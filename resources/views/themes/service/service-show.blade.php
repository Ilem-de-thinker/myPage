<x-themes.shared.layout :page="$page">
  @foreach ($page->sections->where('page', 'service-show')->where('is_enabled', true) as $section)
    <x-dynamic-component
      :component="'themes.'.$page->theme->value.'.sections.service-show.'.$section->key"
      :page="$page" :section="$section" />
  @endforeach
</x-themes.shared.layout>