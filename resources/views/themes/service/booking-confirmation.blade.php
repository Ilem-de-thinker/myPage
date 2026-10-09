<x-themes.shared.layout :page="$page">
  @foreach ($page->sections->where('page', 'booking-confirmation')->where('is_enabled', true) as $section)
    <x-dynamic-component
      :component="'themes.'.$page->theme->value.'.sections.booking-confirmation.'.$section->key"
      :page="$page" :section="$section" />
  @endforeach
</x-themes.shared.layout>