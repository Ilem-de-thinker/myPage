{{--
    The service itself is the page. A seller who has written no introduction
    still gets a full detail page, so this is rendered unconditionally and the
    intro block above it is the only optional part.

    The booking form lives here rather than behind a link to /book: a customer
    decides on a service page, so that is where the times should be. The
    standalone booking page stays for someone who has not chosen yet.
--}}
<x-themes.shared.layout :page="$page">
  @foreach ($blocks as $block)
    <x-themes.service.blocks.service-show.intro :page="$page" :block="$block" :service="$service" />
  @endforeach

  <x-themes.service.blocks.service-show.details :page="$page" :service="$service" />

  {{--
      Reviews.

      Below the booking form, not above it: on this page the decision is the
      booking, and a wall of reviews between the price and the calendar would be
      someone else's opinion standing in the way of the buyer's own. The rows are
      approved-only — Review::published() in the controller's eager load.
  --}}
  <section class="shell max-w-3xl pb-16">
    <x-ui.review-list
        :reviews="$service->reviews"
        :rating="$service->rating"
        :count="$service->review_count"
        heading="Reviews for this service" />

    <div class="mt-8">
      <x-ui.review-form
          :action="route('storefront.service.review', ['slug' => $page->slug, 'serviceSlug' => $service->slug])"
          :reviewable="$service"
          :subject="$service->name" />
    </div>
  </section>

  @if ($service->is_active)
    <div id="book" class="scroll-mt-20">
      <x-themes.service.blocks.booking.widget
          :page="$page"
          :services="collect([$service])"
          :staff="$service->staff" />
    </div>
  @endif
</x-themes.shared.layout>