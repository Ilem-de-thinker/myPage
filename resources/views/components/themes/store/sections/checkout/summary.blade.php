@props(['page', 'section'])
<section class="max-w-5xl mx-auto px-4 py-12">
  <h2 class="text-2xl font-bold mb-3" style="font-family: var(--font-heading-stack)">{{ $section->content['heading'] ?? \Illuminate\Support\Str::headline($section->key) }}</h2>
  @if (! empty($section->content['image']))
    <img src="{{ \Illuminate\Support\Facades\Storage::url($section->content['image']) }}" alt="" class="rounded-xl my-4 max-w-full">
  @endif
  <div class="prose max-w-none">{!! \App\Shared\Support\Html::sanitize($section->content['body'] ?? '') !!}</div>
</section>