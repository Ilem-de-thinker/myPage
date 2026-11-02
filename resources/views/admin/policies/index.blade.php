<x-admin.layout title="Policies">
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <p class="text-xs font-semibold uppercase tracking-[0.18em] text-brand-text">Legal</p>
      <h1 class="mt-2 font-display text-3xl font-extrabold tracking-tight">Policies</h1>
      <p class="mt-2 max-w-2xl text-sm text-ink-2">
        Published copy is live on the platform. Bump the version to ask sellers to re-accept it.
      </p>
    </div>
  </div>

  <div class="mt-8 overflow-hidden rounded-2xl border border-rule bg-card">
    <table class="w-full text-left text-xs">
      <thead class="border-b border-rule bg-surface text-[11px] uppercase tracking-wide text-ink-3">
        <tr>
          <th class="px-4 py-3 font-semibold">Policy</th>
          <th class="px-4 py-3 font-semibold">Version</th>
          <th class="px-4 py-3 font-semibold">State</th>
          <th class="px-4 py-3 font-semibold">Effective</th>
          <th class="px-4 py-3"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-rule">
        @foreach ($policies as $policy)
          <tr>
            <td class="px-4 py-4">
              <p class="font-bold text-ink">{{ $policy->title }}</p>
              <p class="mt-0.5 text-ink-3">{{ $policy->slug }}</p>
            </td>
            <td class="px-4 py-4 text-ink-2">{{ $policy->version }}</td>
            <td class="px-4 py-4">
              @if ($policy->published_at)
                <span class="rounded-full bg-ok-wash px-2 py-0.5 text-[10px] font-bold text-ok-text">Published</span>
              @else
                <span class="rounded-full bg-surface px-2 py-0.5 text-[10px] font-bold text-ink-3">Unpublished</span>
              @endif
              @if ($policy->is_draft)
                <span class="ml-1 rounded-full bg-warn-wash px-2 py-0.5 text-[10px] font-bold text-warn-text">Draft text</span>
              @endif
            </td>
            <td class="px-4 py-4 text-ink-2">{{ $policy->effective_at?->format('j M Y') ?? '—' }}</td>
            <td class="px-4 py-4 text-right">
              <a href="{{ route('admin.policies.edit', $policy) }}" class="font-semibold text-brand-text hover:underline">Edit</a>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</x-admin.layout>
