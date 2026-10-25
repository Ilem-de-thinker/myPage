import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Alpine from '@alpinejs/csp';
import './animations.js';
import './alpine-components.js';

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-confirm]');

    if (trigger && !window.confirm(trigger.dataset.confirm)) {
        event.preventDefault();
    }
});

const themeStorageKey = 'theme-mode';

function activeTheme() {
    const explicit = document.documentElement.getAttribute('data-theme');

    if (explicit === 'light' || explicit === 'dark') {
        return explicit;
    }

    const defaultTheme = document.documentElement.dataset.themeDefault;

    if (defaultTheme === 'light' || defaultTheme === 'dark') {
        return defaultTheme;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function syncThemeControls() {
    const current = activeTheme();
    const next = current === 'dark' ? 'light' : 'dark';

    document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
        toggle.title = `Switch to ${next}`;
        toggle.setAttribute('aria-label', `Switch to ${next}`);
        toggle.setAttribute('aria-pressed', current === 'dark' ? 'true' : 'false');
        toggle.querySelectorAll('[data-theme-icon]').forEach((icon) => {
            icon.hidden = icon.dataset.themeIcon !== current;
        });
    });
}

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-theme-toggle]');

    if (! toggle) {
        return;
    }

    const next = activeTheme() === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);

    try {
        localStorage.setItem(themeStorageKey, next);
    } catch (error) {
        // The visual switch still works when storage is unavailable.
    }

    syncThemeControls();
});

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', syncThemeControls);
syncThemeControls();

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

Alpine.data('pwaInstallPrompt', () => ({
    deferredPrompt: null,
    showInstall: false,
    showIos: false,
    updateReady: false,

    init() {
        const standalone = window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;
        let dismissed = false;

        try {
            dismissed = localStorage.getItem('mypage-pwa-dismissed') !== null;
        } catch (error) {
            dismissed = false;
        }

        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            this.deferredPrompt = event;

            if (! standalone && ! dismissed) {
                setTimeout(() => {
                    this.showInstall = true;
                }, 2500);
            }
        });

        this.showIos = /iphone|ipad|ipod/i.test(navigator.userAgent) && ! standalone && ! dismissed;

        if (this.showIos) {
            setTimeout(() => {
                this.showInstall = true;
            }, 2500);
        }

        navigator.serviceWorker?.register(this.$el.dataset.worker, { scope: '/' }).then((registration) => {
            registration.addEventListener('updatefound', () => {
                const next = registration.installing;

                next?.addEventListener('statechange', () => {
                    if (next.state === 'installed' && navigator.serviceWorker.controller) {
                        this.updateReady = true;
                    }
                });
            });
        });
    },

    install() {
        this.deferredPrompt?.prompt();
        this.showInstall = false;
    },

    dismiss() {
        this.showInstall = false;

        try {
            localStorage.setItem('mypage-pwa-dismissed', '1');
        } catch (error) {
            // Dismissal still works when storage is unavailable.
        }
    },
}));

Alpine.data('chatInbox', (config = {}) => ({
    unread: Number(config.unread || 0),
    timer: null,

    init() {
        this.timer = window.setInterval(async () => {
            if (document.hidden) {
                return;
            }

            try {
                const response = await fetch(config.refresh, { headers: { Accept: 'application/json' } });

                if (response.ok) {
                    const data = await response.json();
                    this.unread = Number(data.unread_count || 0);
                }
            } catch (error) {
                // The next poll will retry; a dropped refresh is not worth a banner.
            }
        }, 15000);
    },

    destroy() {
        window.clearInterval(this.timer);
    },
}));

Alpine.data('chatThread', (config = {}) => ({
    body: '',
    sending: false,
    aiEnabled: Boolean(config.aiEnabled),
    suggestion: null,
    summary: config.initialSummary,
    translation: null,

    async json(response) {
        return response.json();
    },

    async send() {
        if (! this.body.trim() || this.sending) {
            return;
        }

        this.sending = true;

        try {
            const response = await fetch(config.store, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrf,
                },
                body: JSON.stringify({ body: this.body }),
            });

            if (response.ok) {
                window.location.reload();
            }
        } finally {
            this.sending = false;
        }
    },

    async toggleAi() {
        const response = await fetch(config.toggle, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf },
        });
        const data = await this.json(response);
        this.aiEnabled = Boolean(data.enabled);
    },

    async suggest() {
        const response = await fetch(config.suggest, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf },
        });
        const data = await this.json(response);
        this.suggestion = response.ok ? data.suggestion : { error: data.message || 'Suggestion unavailable.' };
    },

    async summarize() {
        const response = await fetch(config.summaryEndpoint, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf },
        });
        const data = await this.json(response);

        if (response.ok) {
            this.summary = data.summary;
        }
    },

    async translate(id) {
        const response = await fetch(config.translate, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': config.csrf,
            },
            body: JSON.stringify({ message_id: id, language: 'English' }),
        });
        const data = await this.json(response);
        this.translation = response.ok ? data.text : data.message;
    },

    format(value) {
        return typeof value === 'string' ? value : JSON.stringify(value, null, 2);
    },
}));

Alpine.data('analyticsInsights', (config = {}) => ({
    loading: false,
    digest: null,
    question: '',
    answer: null,

    async read(response) {
        const data = await response.json();

        if (! response.ok) {
            throw new Error(data.message || 'The request could not be completed.');
        }

        return data.result;
    },

    async generate() {
        this.loading = true;

        try {
            const response = await fetch(config.digest, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf },
            });

            this.digest = await this.read(response);
        } catch (error) {
            this.digest = { error: error.message };
        } finally {
            this.loading = false;
        }
    },

    async ask() {
        if (! this.question.trim()) {
            return;
        }

        this.loading = true;

        try {
            const response = await fetch(config.ask, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrf,
                },
                body: JSON.stringify({ question: this.question }),
            });

            this.answer = await this.read(response);
        } catch (error) {
            this.answer = { error: error.message };
        } finally {
            this.loading = false;
        }
    },

    format(value) {
        return typeof value === 'string' ? value : JSON.stringify(value, null, 2);
    },
}));

Alpine.data('platformSupport', () => ({
    supportOpen: false,
    supportTab: 'store',

    openSupport() {
        this.supportOpen = true;
        document.body.classList.add('sheet-open');
    },

    closeSupport() {
        this.supportOpen = false;
        document.body.classList.remove('sheet-open');
    },
}));

Alpine.start();
