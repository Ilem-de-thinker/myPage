@php
    $page = auth()->user()?->storePages?->first();
@endphp
@if ($page)
<div x-data="aiTaskIndicator()" x-init="init()" class="fixed right-4 top-16 z-30 max-w-xs" aria-live="polite">
    <template x-if="tasks.length">
        <div>
            <button type="button" @click="open = !open" class="flex items-center gap-2 rounded-full bg-card border border-rule shadow px-4 py-2 text-sm">
                <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg>
                <span x-text="label"></span>
            </button>
            <div x-show="open" x-cloak class="mt-2 rounded-xl border border-rule bg-card shadow p-3 space-y-2">
                <template x-for="task in tasks" :key="task.id">
                    <a :href="'/ai/tasks/' + task.id" class="block text-xs">
                        <span x-text="task.type"></span> · <span x-text="task.completed"></span>/<span x-text="task.total"></span> · <span x-text="task.status"></span>
                    </a>
                </template>
            </div>
        </div>
    </template>
</div>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('aiTaskIndicator', () => ({
            tasks: [],
            open: false,
            get label() {
                const task = this.tasks[0];
                if (! task) {
                    return '';
                }
                if (['completed', 'partially_completed'].includes(task.status)) {
                    return task.completed + ' ' + task.type + ' ready. Review';
                }
                return task.completed + ' of ' + task.total + ' ' + task.type + ' created…';
            },
            init() {
                this.fetchTasks();
                document.addEventListener('visibilitychange', () => { if (! document.hidden) this.fetchTasks(); });
                setInterval(() => { if (! document.hidden) this.fetchTasks(); }, 5000);
            },
            async fetchTasks() {
                try {
                    const response = await fetch('/ai/tasks/active', { headers: { Accept: 'application/json' } });
                    if (! response.ok) {
                        return;
                    }
                    const data = await response.json();
                    this.tasks = (data.tasks || []).filter((task) => ['queued', 'processing', 'completed', 'partially_completed', 'failed'].includes(task.status));
                } catch (error) {
                    // The next poll will retry; a dropped refresh is not worth a banner.
                }
            },
        }));
    });
</script>
@endif
