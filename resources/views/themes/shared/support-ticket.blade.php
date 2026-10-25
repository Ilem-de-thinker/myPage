<x-themes.shared.layout :page="$page">
    <div class="shell py-12">
        <div class="mx-auto max-w-3xl">
            <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.show', ['slug' => $page->slug]) }}" class="text-xs font-semibold text-[var(--ink-3)] hover:text-[var(--ink)]">← Back to {{ $page->name }}</a>

            <div class="mt-6 flex flex-col justify-between gap-4 border-b border-[var(--rule)] pb-6 sm:flex-row sm:items-end">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[var(--brand-text)]">mypage.ng support ticket</p>
                    <h1 class="mt-2 t-display-2 text-[var(--ink)]">{{ $ticket->subject }}</h1>
                    <p class="mt-2 font-mono text-xs font-bold text-[var(--brand-text)]">{{ $ticket->ticket_number }}</p>
                </div>
                <span class="h-fit rounded-full border border-[var(--brand-edge)] bg-[var(--brand-wash)] px-3 py-1 text-xs font-semibold text-[var(--brand-text)]">{{ $ticket->status->label() }}</span>
            </div>

            <div class="mt-6 space-y-3">
                @foreach ($ticket->messages as $message)
                    <article class="rounded-2xl border border-[var(--rule)] {{ $message->sender_type === 'admin' ? 'bg-[var(--brand-wash)]' : 'bg-[var(--card)]' }} p-5">
                        <div class="flex items-center justify-between gap-3 text-[10px] font-semibold text-[var(--ink-3)]">
                            <span>{{ $message->sender_type === 'admin' ? 'mypage.ng support' : 'You' }}</span>
                            <time datetime="{{ $message->created_at?->toIso8601String() }}">{{ $message->created_at?->format('j M Y, g:ia') }}</time>
                        </div>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-[var(--ink-2)]">{{ $message->body }}</p>
                    </article>
                @endforeach
            </div>

            <form method="POST" action="{{ \App\Shared\Support\StoreUrl::path('storefront.support.reply', ['slug' => $page->slug, 'ticketNumber' => $ticket->ticket_number, 'token' => $token]) }}" class="mt-6 rounded-2xl border border-[var(--rule)] bg-[var(--surface)] p-5">
                @csrf
                <label class="block text-xs font-semibold text-[var(--ink)]">Reply to this ticket
                    <textarea name="body" required minlength="2" maxlength="4000" rows="4" class="mt-2 w-full rounded-xl border border-[var(--rule)] bg-[var(--paper)] p-3 text-sm text-[var(--ink)]" placeholder="Add more information or ask a follow-up question."></textarea>
                </label>
                <button class="mt-3 rounded-xl bg-[var(--brand)] px-4 py-3 text-xs font-bold text-[var(--brand-ink)]">Send reply</button>
            </form>
        </div>
    </div>
</x-themes.shared.layout>
