@props(['type', 'id'])

<details class="mt-4 text-xs text-[var(--platform-ink-muted)]">
    <summary class="cursor-pointer font-semibold">Report this listing</summary>
    <form method="POST" action="{{ route('directory.report', [$type, $id]) }}" class="mt-3 space-y-2 rounded-xl border border-[var(--platform-line)] bg-[var(--platform-paper)] p-3">
        @csrf
        <select name="category" required class="min-h-10 w-full rounded-lg border border-[var(--platform-line)] bg-transparent px-2 text-xs">
            <option value="">Reason</option><option value="misleading_listing">Misleading listing</option><option value="not_as_described">Not as described</option><option value="fraud">Fraud concern</option><option value="other">Other</option>
        </select>
        <textarea name="body" required minlength="20" maxlength="2000" rows="3" placeholder="Tell us what needs review" class="w-full rounded-lg border border-[var(--platform-line)] bg-transparent p-2 text-xs"></textarea>
        <button class="rounded-lg bg-[var(--platform-ink)] px-3 py-2 text-xs font-bold text-white">Send report</button>
    </form>
</details>
