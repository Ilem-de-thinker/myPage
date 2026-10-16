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

/**
 * Where this buyer's cart lives in storage.
 *
 * Keyed by the store, not just the host. Every seller on mypage.ng shares one
 * host and is told apart by the path, so a cart kept per host belongs to
 * whichever shop the buyer happened to visit first. Visiting a second shop then
 * carried the first shop's product ids into its checkout, and pricing rejected
 * them with "That item is no longer available" — a cart of another store's
 * goods, silently carried across.
 *
 * On a seller's own domain the host already identifies the store; the slug is
 * appended anyway so both kinds of shop agree, and so a buyer who moves between
 * the apex and a custom domain keeps one cart rather than two.
 */
const cartKey = () => {
    const store = document.body?.dataset.store || '';

    return 'cart:' + location.host + (store ? '/' + store : '');
};

Alpine.store('cart', {
    items: JSON.parse(localStorage.getItem(cartKey()) || '[]'),

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
        localStorage.setItem(cartKey(), JSON.stringify(this.items));
    },
});

Alpine.start();
