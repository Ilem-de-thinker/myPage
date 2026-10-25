@props(['label' => null, 'name' => null, 'error' => null])
<label {{ $attributes->only('class')->merge(['class' => 'block text-xs font-semibold text-ink-2']) }}>
    @if ($label)<span>{{ $label }}</span>@endif
    <input name="{{ $name }}" {{ $attributes->except('class')->merge(['class' => 'mt-2 min-h-11 w-full rounded-xl border border-rule-strong bg-card px-3 text-sm text-ink focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20']) }}>
    @if ($error)<span class="mt-1 block text-xs text-danger-text">{{ $error }}</span>@endif
</label>
