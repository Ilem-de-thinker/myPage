@props([
    'columns' => [],       // ['key' => 'name', 'label' => 'Name', 'type' => 'text|image|badge']
    'rows' => null,        // paginator
    'searchPlaceholder' => 'Search…',
    'filters' => [],       // ['name' => 'status', 'label' => 'All statuses', 'options' => ['active' => 'Active']]
    'bulkDestroyRoute' => null,
    'editRoute' => null,
    'destroyRoute' => null,
    'extraRoute' => null,
    'extraLabel' => null,
    'stockRoute' => null,  // per-row route name, e.g. products.stock
    'emptyText' => 'Nothing found.',
])

@php
    $stockUrlTemplate = $stockRoute ? route($stockRoute, ['product' => '__ID__']) : null;
    $visibleColumns = collect($columns)->reject(fn ($col) => ($col['type'] ?? 'text') === 'image');
@endphp

<div x-data="{
    selected: [],
    hidden: [],
    selectAll: false,
    toggleAll() { this.selectAll = !this.selectAll; this.selected = this.selectAll ? @js($rows->pluck('id')->all()) : []; },
    toggleCol(key) { this.hidden = this.hidden.includes(key) ? this.hidden.filter(k => k !== key) : [...this.hidden, key]; },
    stock: { open: false, busy: false, id: null, name: '', qty: 0, delta: 1, reason: 'quick adjust' },
    openStock(id, name, qty) { this.stock = { open: true, busy: false, id, name, qty, delta: 1, reason: 'quick adjust' }; },
    get stockUrl() { return @js($stockUrlTemplate).replace('__ID__', this.stock.id); },
}">
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ $searchPlaceholder }}" class="px-4 py-2 rounded-xl border border-slate-300 text-sm w-56">
            @foreach ($filters as $filter)
                <select name="{{ $filter['name'] }}" class="px-3 py-2 rounded-xl border border-slate-300 text-sm bg-white" onchange="this.form.submit()">
                    <option value="">{{ $filter['label'] }}</option>
                    @foreach ($filter['options'] as $value => $label)
                        <option value="{{ $value }}" @selected(request($filter['name']) === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            @endforeach
            <button class="px-4 py-2 rounded-xl bg-slate-800 text-white text-xs font-semibold">Filter</button>
            @if (request()->hasAny(['q', 'status']))
                <a href="{{ url()->current() }}" class="text-xs font-semibold text-slate-500">Reset</a>
            @endif
        </form>

        <div class="relative ml-auto" x-data="{ open: false }">
            <button type="button" @click="open = !open" class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-600 bg-white">Columns ▾</button>
            <div x-cloak x-show="open" @click.outside="open = false" class="absolute right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-lg p-3 z-20 w-44 space-y-1.5">
                @foreach ($visibleColumns as $col)
                    <label class="flex items-center gap-2 text-xs text-slate-600">
                        <input type="checkbox" :checked="!hidden.includes('{{ $col['key'] }}')" @change="toggleCol('{{ $col['key'] }}')"> {{ $col['label'] ?: $col['key'] }}
                    </label>
                @endforeach
            </div>
        </div>
    </div>

    @if ($bulkDestroyRoute)
        <div x-cloak x-show="selected.length > 0" class="mb-3 flex items-center gap-3 bg-red-50 border border-red-200 rounded-xl px-4 py-2.5">
            <span class="text-xs font-semibold text-red-700" x-text="selected.length + ' selected'"></span>
            <form method="POST" action="{{ $bulkDestroyRoute }}" onsubmit="return confirm('Delete selected?')">
                @csrf
                <template x-for="id in selected" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                <button class="px-3 py-1.5 rounded-lg bg-red-600 text-white text-xs font-semibold">Delete selected</button>
            </form>
        </div>
    @endif

    @if ($rows->isEmpty())
        <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center"><p class="text-sm text-slate-500">{{ $emptyText }}</p></div>
    @else
        {{-- Desktop table --}}
        <div class="hidden md:block bg-white rounded-2xl border border-slate-200 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wide text-slate-400 border-b border-slate-100">
                        @if ($bulkDestroyRoute)
                            <th class="p-4 w-10"><input type="checkbox" @change="toggleAll()" :checked="selectAll && selected.length > 0"></th>
                        @endif
                        @foreach ($columns as $col)
                            <th class="px-4 py-3 font-semibold" x-show="!hidden.includes('{{ $col['key'] }}')">{{ $col['label'] }}</th>
                        @endforeach
                        <th class="px-4 py-3 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($rows as $row)
                        <tr class="hover:bg-slate-50/60">
                            @if ($bulkDestroyRoute)
                                <td class="p-4"><input type="checkbox" :value="{{ $row->id }}" x-model="selected"></td>
                            @endif
                            @foreach ($columns as $col)
                                @php $value = data_get($row, $col['key']); @endphp
                                <td class="px-4 py-3" x-show="!hidden.includes('{{ $col['key'] }}')">
                                    @include('components.ui.data-table-cell', ['col' => $col, 'value' => $value])
                                </td>
                            @endforeach
                            <td class="px-4 py-3 text-right whitespace-nowrap space-x-3">
                                @if ($editRoute) <a href="{{ route($editRoute, $row) }}" class="text-xs font-semibold text-brand">Edit</a> @endif
                                @if ($extraRoute)
                                    <a href="{{ route($extraRoute, $row) }}" class="text-xs font-semibold text-slate-500">{{ $extraLabel }}</a>
                                @endif
                                @if ($stockRoute)
                                    <button type="button" class="text-xs font-semibold text-slate-500" @click="openStock({{ $row->id }}, @js($row->name ?? 'Product'), {{ (int) data_get($row, 'stock_qty') }})">Stock</button>
                                @endif
                                @if ($destroyRoute)
                                    <form method="POST" action="{{ route($destroyRoute, $row) }}" class="inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')
                                        <button class="text-xs font-semibold text-red-500">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile list: every column is shown, labelled, so nothing is lost --}}
        <div class="md:hidden space-y-3">
            @foreach ($rows as $row)
                <div class="bg-white rounded-2xl border border-slate-200 p-4">
                    <div class="flex items-start gap-3">
                        @if ($bulkDestroyRoute)
                            <input type="checkbox" :value="{{ $row->id }}" x-model="selected" class="mt-1">
                        @endif
                        @php $thumb = data_get($row, 'primaryImageUrl'); @endphp
                        @if ($thumb)
                            <img src="{{ $thumb }}" class="w-14 h-14 rounded-xl object-cover shrink-0">
                        @else
                            <div class="w-14 h-14 rounded-xl bg-slate-100 shrink-0"></div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-ink text-sm truncate">{{ $row->name ?? $row->title ?? '' }}</p>
                            @php $price = data_get($row, 'priceLabel'); @endphp
                            @if ($price)
                                <p class="text-sm font-bold" style="color: var(--brand)">{{ $price }}</p>
                            @endif
                        </div>
                    </div>

                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 mt-3 pt-3 border-t border-slate-100 text-xs">
                        @foreach ($visibleColumns as $col)
                            @continue($col['key'] === 'name')
                            <div>
                                <dt class="text-[10px] uppercase tracking-wide text-slate-400">{{ $col['label'] }}</dt>
                                <dd class="font-medium text-slate-700 mt-0.5">{{ data_get($row, $col['key']) }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <div class="flex items-center gap-4 mt-3 pt-3 border-t border-slate-100">
                        @if ($editRoute) <a href="{{ route($editRoute, $row) }}" class="text-xs font-semibold text-brand">Edit</a> @endif
                        @if ($extraRoute)
                            <a href="{{ route($extraRoute, $row) }}" class="text-xs font-semibold text-slate-500">{{ $extraLabel }}</a>
                        @endif
                        @if ($stockRoute)
                            <button type="button" class="text-xs font-semibold text-slate-500" @click="openStock({{ $row->id }}, @js($row->name ?? 'Product'), {{ (int) data_get($row, 'stock_qty') }})">Stock</button>
                        @endif
                        @if ($destroyRoute)
                            <form method="POST" action="{{ route($destroyRoute, $row) }}" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')
                                <button class="text-xs font-semibold text-red-500">Delete</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $rows->links() }}</div>
    @endif

    {{-- Stock modal --}}
    @if ($stockRoute)
        <div x-cloak x-show="stock.open" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @keydown.escape.window="stock.open = false">
            <div class="bg-white rounded-2xl p-6 w-full max-w-sm" @click.outside="stock.open = false">
                <div class="flex items-center justify-between mb-1">
                    <h3 class="font-display font-bold text-ink">Adjust stock</h3>
                    <button type="button" @click="stock.open = false" class="text-slate-400 text-lg leading-none">&times;</button>
                </div>
                <p class="text-xs text-slate-500 mb-4"><span x-text="stock.name"></span> — currently <span class="font-semibold" x-text="stock.qty"></span></p>

                <form method="POST" :action="stockUrl" @submit="stock.busy = true">
                    @csrf
                    @method('PATCH')

                    <div class="flex items-center gap-2 mb-3">
                        <button type="button" @click="stock.delta = Math.max(-1000, stock.delta - 1)" class="w-9 h-9 rounded-lg border border-slate-300 text-slate-600">−</button>
                        <input type="number" name="delta" x-model.number="stock.delta" class="flex-1 text-center px-3 py-2 rounded-lg border border-slate-300 text-sm" required>
                        <button type="button" @click="stock.delta = stock.delta + 1" class="w-9 h-9 rounded-lg border border-slate-300 text-slate-600">+</button>
                    </div>

                    <input type="text" name="reason" x-model="stock.reason" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm mb-4" required>

                    <p x-show="stock.delta !== 0" class="text-xs text-slate-500 mb-3">
                        New quantity: <span class="font-bold text-ink" x-text="stock.qty + stock.delta"></span>
                    </p>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="stock.open = false" class="px-4 py-2 text-xs font-semibold text-slate-500">Cancel</button>
                        <button type="submit" :disabled="stock.delta === 0" class="px-4 py-2 rounded-xl bg-brand text-white text-xs font-semibold disabled:opacity-40">Save stock</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>