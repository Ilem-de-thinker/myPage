{{--
    The booking widget is rendered from real, bookable services rather than
    from stored content, so it is not a block a seller can fill in or leave
    empty. Only the introduction above it is content.

    Ported from new_ui/service — the design's page header, package chooser,
    calendar frame, slot chips and sticky summary are now the widget, around the
    same endpoints and the same POST body it always used.
--}}
<x-themes.shared.layout :page="$page">
  @foreach ($blocks as $block)
    <x-themes.service.blocks.booking.summary :page="$page" :block="$block" />
  @endforeach

  <x-themes.service.blocks.booking.widget
      :page="$page"
      :services="$services ?? null"
      :staff="$staff ?? null" />
</x-themes.shared.layout>