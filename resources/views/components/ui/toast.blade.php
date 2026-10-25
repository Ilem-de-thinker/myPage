@props(['type' => 'success', 'message' => null])
@if ($message)
    <div role="status" {{ $attributes->merge(['class' => 'rounded-xl border px-4 py-3 text-sm '.($type === 'error' ? 'border-danger-edge bg-danger-wash text-danger-text' : 'border-ok-edge bg-ok-wash text-ok-text')]) }}>{{ $message }}</div>
@endif
