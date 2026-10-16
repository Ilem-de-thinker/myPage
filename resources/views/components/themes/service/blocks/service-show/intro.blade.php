@props(['page', 'block', 'service'])

@php
    /*
      The seller's optional framing for this service.

      Only that. The page heading is the service's own name and the price and
      deposit panel belong to the details block below, so this contributes at
      most a line of context above them — never a second <h1> competing with
      the service name for the same job.
    */
    $heading = $block->field('heading');
    $note = $block->field('body');
@endphp

@if ($heading || $note)
    <section class="shell pt-12">
        @if ($heading)
            <p class="t-meta">{{ $heading }}</p>
        @endif

        @if ($note)
            <p class="t-body text-[var(--ink-2)] t-measure mt-2">{{ $note }}</p>
        @endif
    </section>
@endif