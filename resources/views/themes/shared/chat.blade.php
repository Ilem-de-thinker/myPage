<x-themes.shared.layout :page="$page">
    <section x-data="buyerChat(@jsExpr(['endpoint' => \App\Shared\Support\StoreUrl::path('storefront.chat.store', ['slug' => $page->slug, 'thread' => $thread->id]), 'csrf' => csrf_token(), 'token' => $token]))" class="shell mx-auto max-w-2xl py-8">
        <div class="overflow-hidden rounded-[var(--radius-lg)] border border-[var(--rule)] bg-[var(--card)] shadow-[var(--shadow-rest)]">
            <header class="border-b border-[var(--rule)] bg-[var(--brand-wash)] px-4 py-4"><p class="text-sm font-bold text-[var(--ink)]">Message {{ $page->name }}</p><p class="mt-1 text-xs text-[var(--ink-2)]">Your conversation is private to this business.</p></header>
            <div class="min-h-[50vh] space-y-3 overflow-y-auto p-4">
                @foreach ($thread->messages as $message)
                    <div class="flex {{ $message->sender_type === 'buyer' ? 'justify-end' : 'justify-start' }}"><p class="max-w-[85%] rounded-2xl px-4 py-3 text-sm {{ $message->sender_type === 'buyer' ? 'bg-[var(--brand)] text-[var(--brand-ink)]' : 'bg-[var(--surface)] text-[var(--ink)]' }}">{{ $message->body }}</p></div>
                @endforeach
            </div>
            <form @submit.prevent="send" class="flex gap-2 border-t border-[var(--rule)] p-3"><label class="sr-only" for="chat-body">Message</label><textarea id="chat-body" x-model="body" rows="2" maxlength="5000" required class="min-h-11 min-w-0 flex-1 resize-none rounded-[var(--radius)] border border-[var(--rule)] bg-[var(--paper)] px-3 py-2 text-sm text-[var(--ink)] focus:outline-none focus:ring-2 focus:ring-[var(--focus-ring)]" placeholder="Write a message…"></textarea><button class="min-h-11 rounded-[var(--radius)] bg-[var(--brand)] px-4 text-sm font-bold text-[var(--brand-ink)] disabled:opacity-50" :disabled="sending">Send</button></form>
        </div>
    </section>
</x-themes.shared.layout>
