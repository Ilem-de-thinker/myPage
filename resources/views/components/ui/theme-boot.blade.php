{{--
    Applies a stored light/dark choice before the first paint.

    Head only, and inline, and that is the whole point: a switcher bound to
    DOMContentLoaded would show a white page to somebody who chose dark and then
    flip it. The flash is the reason a visitor stops trusting the switch, so it
    has to be gone before there is anything to see.

    This half of the switch has no Alpine and no markup — it is one attribute on
    <html>. x-ui.theme-toggle is the other half and does the writing.

    Nothing is written to storage here: an absent value leaves the page on the
    seller's own surface mode (or the OS preference on a platform page), which
    is the right default for somebody who has not chosen.
--}}
@php
    $key = 'theme-mode';
@endphp

<script>
    (function () {
        try {
            var stored = localStorage.getItem(@js($key));
            if (stored === 'light' || stored === 'dark') {
                document.documentElement.setAttribute('data-theme', stored);
            }
        } catch (error) {
            // Private browsing, or storage disabled. The seller's surface mode
            // is still a perfectly good default.
        }
    })();
</script>