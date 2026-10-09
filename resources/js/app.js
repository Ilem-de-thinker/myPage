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

    add(product, qty = 1) {
        const existing = this.items.find((item) => item.id === product.id);

        if (existing) {
            existing.qty += qty;
        } else {
            this.items.push({ ...product, qty });
        }

        this.save();
    },

    setQty(id, qty) {
        const item = this.items.find((line) => line.id === id);

        if (!item) {
            return;
        }

        if (qty < 1) {
            this.items = this.items.filter((line) => line.id !== id);
        } else {
            item.qty = qty;
        }

        this.save();
    },

    remove(id) {
        this.items = this.items.filter((line) => line.id !== id);
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

    payload() {
        return { items: this.items.map(({ id, qty }) => ({ id, qty })) };
    },

    save() {
        localStorage.setItem('cart:' + location.host, JSON.stringify(this.items));
    },
});

Alpine.start();
