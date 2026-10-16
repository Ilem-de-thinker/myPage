{{--
    The contact details are the seller's business details, so they render whether
    or not they wrote anything on this page. Only the intro copy is a block.

    The design's body-level x-data becomes a wrapper around the slot rather than
    the body: the layout's <body> already owns the nav state, and re-declaring it
    here would shadow the header's copy of it. What is left is the inquiry form's
    own state, which the form in the details block binds to.
--}}
<x-themes.shared.layout :page="$page">
  @php
      // The same link builder the rest of the page uses, so the handoff opens the
      // studio's real number rather than a typed one.
      $desk = trim((string) $page->whatsapp_number) !== ''
          ? 'https://wa.me/'.ltrim((string) $page->whatsapp_number, '+').'?text='
          : '';
  @endphp

  <div x-data="{
        name: '',
        phone: '',
        date: '',
        msg: '',
        submitted: false,
        submitInquiry() {
          this.submitted = true;
          const text = encodeURIComponent(`Hello {{ $page->name }},\nName: ${this.name}\nPhone: ${this.phone}\nEvent Date: ${this.date}\nInquiry: ${this.msg}`);
          window.open(@js($desk) + text, '_blank');
        }
      }">

    @foreach ($blocks as $block)
      @if ($block->key === 'intro')
        <x-themes.service.blocks.contact.intro :page="$page" :block="$block" />
      @endif
    @endforeach

    <x-themes.service.blocks.contact.details :page="$page" />
  </div>
</x-themes.shared.layout>