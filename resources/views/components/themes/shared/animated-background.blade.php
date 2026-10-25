@props(['page'])

@if (($page->settings['animated_background'] ?? false) && ! ($page->settings['reduce_motion'] ?? false))
  <div class="theme-motion-field" aria-hidden="true"><span></span><span></span><span></span><i></i><i></i></div>
@endif
