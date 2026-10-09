import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Alpine from 'alpinejs';

function initRichText() {
    document.querySelectorAll('textarea[data-richtext]').forEach((textarea) => {
        const holder = document.createElement('div');
        holder.className = 'rich-editor rounded-xl border border-slate-300 bg-white p-3 text-sm min-h-32';
        textarea.parentNode.insertBefore(holder, textarea);
        textarea.classList.add('hidden');

        const editor = new Editor({
            element: holder,
            extensions: [StarterKit],
            content: textarea.value,
            onUpdate: () => {
                textarea.value = editor.getHTML();
            },
        });

        textarea.form?.addEventListener('submit', () => {
            textarea.value = editor.getHTML();
        });
    });
}

window.Alpine = Alpine;
Alpine.store('cart', {
    items: JSON.parse(localStorage.getItem('cart:' + location.host) || '[]'),

    // A line is identified by the product plus the chosen variant, so "Large"
    // and "Small" stay separate rows.
    keyOf(item) {
        return `${item.id}:${item.variant_id ?? ''}`;
    },

    find(id, variantId) {
        const key = `${id}:${variantId ?? ''}`;

        return this.items.find((line) => this.keyOf(line) === key);
    },

    add(product, qty = 1) {
        const existing = this.find(product.id, product.variant_id);

        if (existing) {
            existing.qty += qty;
        } else {
            this.items.push({ ...product, qty });
        }

        this.save();
    },

    setQty(id, variantId, qty) {
        const item = this.find(id, variantId);

        if (!item) {
            return;
        }

        if (qty < 1) {
            this.remove(id, variantId);
        } else {
            item.qty = qty;
        }

        this.save();
    },

    remove(id, variantId) {
        const key = `${id}:${variantId ?? ''}`;

        this.items = this.items.filter((line) => this.keyOf(line) !== key);

        this.save();
    },

    clear() {
        this.items = [];
        this.save();
    },

    get count() {
        return this.items.reduce((total, line) => total + line.qty, 0);
    },

    get totalKobo() {
        return this.items.reduce((total, line) => total + line.price_kobo * line.qty, 0);
    },

    /** Payload for the WhatsApp order endpoint. */
    payload() {
        return {
            items: this.items.map(({ id, variant_id, qty }) => ({
                id,
                variant_id: variant_id ?? null,
                qty,
            })),
        };
    },

    /**
     * Payload for the checkout form. Prices are deliberately left out: the server
     * re-prices every line from the database.
     */
    checkoutPayload() {
        return {
            items: this.items.map(({ id, variant_id, qty }) => ({
                id,
                variant_id: variant_id ?? null,
                qty,
            })),
        };
    },

    save() {
        localStorage.setItem('cart:' + location.host, JSON.stringify(this.items));
    },
});

Alpine.start();
