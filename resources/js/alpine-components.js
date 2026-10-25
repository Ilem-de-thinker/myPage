import Alpine from '@alpinejs/csp';

Alpine.data('productFilters', (config = {}) => ({
    open: Boolean(config.open),
    q: config.q ?? '',
    timer: null,

    search() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.$el.submit(), 350);
    },

    clearSearch() {
        clearTimeout(this.timer);
        this.q = '';
        this.$el.submit();
    },
}));

Alpine.data('serviceSearch', (config = {}) => ({
    term: config.term ?? '',
    timer: null,

    search() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.$el.submit(), 350);
    },
}));

Alpine.data('discoveryHub', (config = {}) => ({
    loading: false,
    marqueePaused: false,
    searchQuery: config.term ?? '',
    hubPath: config.hubPath ?? '',

    async apply(link) {
        const url = new URL(link.href, window.location.origin);
        this.loading = true;

        try {
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Partial': 'results',
                    Accept: 'text/html',
                },
            });

            if (! response.ok) {
                throw new Error(response.status);
            }

            this.$refs.content.innerHTML = await response.text();
            window.history.pushState({}, '', url);
        } catch (error) {
            window.location.href = url.toString();
        } finally {
            this.loading = false;
        }
    },

    intercept(event) {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
            return;
        }

        const link = event.target.closest('a[href]');

        if (! link || link.target === '_blank' || link.hasAttribute('download')) {
            return;
        }

        const url = new URL(link.href, window.location.origin);

        if (url.origin !== window.location.origin) {
            return;
        }

        if (url.pathname.replace(/\/$/, '') !== this.hubPath) {
            return;
        }

        if (url.href === window.location.href) {
            return;
        }

        event.preventDefault();
        this.apply(link);
    },
}));

Alpine.data('buyerChat', (config = {}) => ({
    body: '',
    sending: false,

    async send() {
        if (! this.body.trim() || this.sending) {
            return;
        }

        this.sending = true;

        try {
            const response = await fetch(config.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrf,
                },
                body: JSON.stringify({ token: config.token, body: this.body }),
            });

            if (response.ok) {
                window.location.reload();
            }
        } finally {
            this.sending = false;
        }
    },
}));

Alpine.data('serviceInquiry', (config = {}) => ({
    name: '',
    phone: '',
    date: '',
    msg: '',
    submitted: false,

    submitInquiry() {
        this.submitted = true;

        const text = encodeURIComponent(
            `Hello ${config.pageName},\nName: ${this.name}\nPhone: ${this.phone}\nEvent Date: ${this.date}\nInquiry: ${this.msg}`,
        );

        window.open(config.desk + text, '_blank');
    },
}));

Alpine.data('storeContactForm', (config = {}) => ({
    formSubmitted: false,
    name: '',
    phone: '',
    date: '',
    service: config.service ?? '',
    notes: '',
    endpoint: config.endpoint ?? '',

    handleSubmit() {
        if (! this.endpoint) {
            return;
        }

        const body = [
            `Hello ${config.shop}, I would like to make an enquiry.`,
            this.name ? `Name: ${this.name}` : '',
            this.phone ? `WhatsApp: ${this.phone}` : '',
            this.date ? `Date needed: ${this.date}` : '',
            this.service ? `Interested in: ${this.service}` : '',
            this.notes ? `Notes: ${this.notes}` : '',
        ].filter(Boolean).join('\n');

        window.open(this.endpoint + encodeURIComponent(body), '_blank', 'noopener');
        this.formSubmitted = true;
    },
}));

Alpine.data('designSystemPreview', () => ({
    themeMode: 'light',
    activeBrand: 'emerald',
    cartCount: 2,
    marqueePaused: false,
    activeTab: 'details',
    activeVariant: 'M',
    activeColor: 'charcoal',
    qty: 1,
    selectedBookingDate: '2026-10-22',
    selectedSlot: '11:30 AM',
    paymentType: 'deposit',
    reviewModalOpen: false,
    searchQuery: '',
    selectedCity: '',

    setTheme(mode) {
        this.themeMode = mode;

        if (mode === 'system') {
            document.documentElement.removeAttribute('data-theme');
        } else {
            document.documentElement.setAttribute('data-theme', mode);
        }
    },

    setBrand(palette) {
        this.activeBrand = palette;

        const colors = {
            emerald: '#15803d',
            purple: '#7e22ce',
            rust: '#c2410c',
            cobalt: '#1d4ed8',
        };

        document.documentElement.style.setProperty('--brand', colors[palette]);
    },
}));

Alpine.data('themePreview', (config = {}) => ({
    preset: 'catalog',
    mode: 'store',
    color: '#16a34a',
    viewport: '390',
    presets: config.presets ?? [],

    selected() {
        return this.presets[this.preset];
    },

    get frameStyle() {
        return `width: ${this.viewport === 'desktop' ? '100%' : `min(100%, ${this.viewport}px)`}`;
    },

    get previewStyle() {
        const ink = this.contrast();
        const font = this.selected().font;

        return [
            `--brand: ${this.color}`,
            `--brand-ink: ${ink}`,
            `--brand-wash: color-mix(in srgb, ${this.color} 10%, var(--paper))`,
            `--brand-wash-2: color-mix(in srgb, ${this.color} 18%, var(--paper))`,
            `--brand-edge: color-mix(in srgb, ${this.color} 35%, var(--rule))`,
            `--brand-text: ${ink}`,
            `--font-heading: ${font.heading}`,
            `--font-body: ${font.body}`,
        ].join('; ');
    },

    selectPreset(key) {
        this.preset = key;

        if (! this.selected().business_types.includes(this.mode)) {
            this.mode = this.selected().business_types[0];
        }
    },

    selectMode(mode) {
        this.mode = mode;

        if (! this.selected().business_types.includes(mode)) {
            this.preset = 'minimal-swiss';
        }
    },

    contrast() {
        const hex = this.color.replace('#', '');
        const values = [0, 2, 4].map((index) => parseInt(hex.slice(index, index + 2), 16) / 255);
        const linear = values.map((value) => (value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4));

        return (0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2]) > 0.179 ? '#15171b' : '#ffffff';
    },
}));

Alpine.data('storeProduct', (config = {}) => ({
    tab: config.tab ?? 'details',
    qty: 1,
    variantId: config.variantId ?? null,
    options: config.options ?? [],
    gallery: config.gallery ?? [],
    activeImage: 0,
    productId: config.productId,
    productName: config.productName ?? '',
    fallbackPriceKobo: config.priceKobo ?? 0,
    fallbackMaxQty: config.maxQty ?? 99,

    get variant() {
        return this.options.find((option) => option.id === this.variantId) ?? null;
    },

    get currentImage() {
        return this.gallery[this.activeImage] ?? null;
    },

    get imageUrl() {
        return this.currentImage ? this.currentImage.url : '';
    },

    get imageAlt() {
        return this.currentImage ? this.currentImage.alt : '';
    },

    get unitKobo() {
        return this.variant ? this.variant.price_kobo : this.fallbackPriceKobo;
    },

    get priceLabel() {
        return '₦' + (this.unitKobo / 100).toLocaleString(undefined, {
            minimumFractionDigits: this.unitKobo % 100 ? 2 : 0,
            maximumFractionDigits: 2,
        });
    },

    get maxQty() {
        return this.variant ? this.variant.stock : this.fallbackMaxQty;
    },

    addToCart() {
        this.qty = 1;
        this.$store.cart.add({
            id: this.productId,
            variant_id: this.variantId,
            variant_name: this.variant ? this.variant.name : null,
            name: this.productName,
            price_kobo: this.unitKobo,
        }, this.qty);
    },

    inCart() {
        return this.$store.cart.items.some((line) => line.id === this.productId && line.variant_id === this.variantId);
    },

    decrementQty() {
        this.qty = Math.max(1, this.qty - 1);
    },

    incrementQty() {
        this.qty = Math.min(this.maxQty, this.qty + 1);
    },
}));

Alpine.data('storeCheckout', (config = {}) => ({
    submitting: false,
    captured: false,

    async capture() {
        if (this.captured) {
            return;
        }

        const email = document.getElementById('email')?.value?.trim();
        const phone = document.getElementById('phone')?.value?.trim();

        if (! email && ! phone) {
            return;
        }

        if (this.$store.cart.items.length === 0) {
            return;
        }

        try {
            await fetch(config.captureUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrf,
                },
                body: JSON.stringify({
                    email: email || null,
                    phone: phone || null,
                    name: document.getElementById('name')?.value?.trim() || null,
                    items: this.$store.cart.items.map((item) => ({
                        id: item.id,
                        variant_id: item.variant_id ?? null,
                        qty: item.qty,
                    })),
                }),
            });

            this.captured = true;
        } catch (error) {
            // Never block checkout on a best-effort capture.
        }
    },
}));

Alpine.data('aiListingForm', (config = {}) => ({
    name: config.name ?? '',
    description: config.description ?? '',
    aiLoading: false,
    aiMessage: '',
    aiError: '',

    async draftCopy() {
        this.aiLoading = true;
        this.aiMessage = '';
        this.aiError = '';

        try {
            const response = await fetch(config.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrf,
                },
                body: JSON.stringify({
                    task: config.task,
                    feature: config.feature,
                    input: { text: [this.name, this.description].filter(Boolean).join('\n') },
                }),
            });

            const data = await response.json();

            if (! response.ok) {
                this.aiError = data.message || 'AI could not prepare this listing.';
                return;
            }

            this.name = data.result.name || this.name;
            this.description = data.result.description || this.description;
            this.aiMessage = 'Draft added to the form. Review it before saving.';
        } catch (error) {
            this.aiError = 'AI could not prepare this listing.';
        } finally {
            this.aiLoading = false;
        }
    },
}));

Alpine.data('newCategoryForm', (config = {}) => ({
    newCat: false,
    name: '',
    error: '',
    busy: false,

    async create() {
        this.busy = true;
        this.error = '';

        try {
            const response = await fetch(config.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrf,
                },
                body: JSON.stringify({ name: this.name }),
            });

            if (! response.ok) {
                const data = await response.json();
                this.error = data.message ?? 'Could not create category';
                return;
            }

            const category = await response.json();
            const option = new Option(category.name, category.id, true, true);
            this.$refs.catSelect.add(option);
            this.name = '';
            this.newCat = false;
        } catch (error) {
            this.error = 'Network error';
        } finally {
            this.busy = false;
        }
    },
}));

Alpine.data('imagePreview', (config = {}) => ({
    previewOpen: false,
    previews: config.previews ?? [],

    addFiles(event) {
        const files = Array.from(event.target.files ?? []);
        this.previews = [...this.previews, ...files.map((file) => URL.createObjectURL(file))];
    },
}));

Alpine.data('dataTable', (config = {}) => ({
    selected: [],
    hidden: [],
    selectAll: false,
    ids: config.ids ?? [],
    stockUrlTemplate: config.stockUrlTemplate ?? '',
    stock: { open: false, busy: false, id: null, name: '', qty: 0, delta: 1, reason: 'quick adjust' },

    toggleAll() {
        this.selectAll = ! this.selectAll;
        this.selected = this.selectAll ? [...this.ids] : [];
    },

    toggleCol(key) {
        this.hidden = this.hidden.includes(key)
            ? this.hidden.filter((current) => current !== key)
            : [...this.hidden, key];
    },

    openStock(id, name, qty) {
        this.stock = { open: true, busy: false, id, name, qty, delta: 1, reason: 'quick adjust' };
    },

    get stockUrl() {
        return this.stockUrlTemplate.replace('__ID__', this.stock.id);
    },

    decrementStock() {
        this.stock.delta = Math.max(-1000, this.stock.delta - 1);
    },

    incrementStock() {
        this.stock.delta = this.stock.delta + 1;
    },
}));

Alpine.data('availabilityEditor', (config = {}) => ({
    days: config.days ?? {},
    copyFrom: null,

    copy() {
        if (! this.copyFrom) {
            return;
        }

        const source = this.days[this.copyFrom];

        for (const weekday of Object.keys(this.days)) {
            if (weekday === String(this.copyFrom)) {
                continue;
            }

            this.days[weekday] = {
                enabled: source.enabled,
                start: source.start,
                end: source.end,
            };
        }
    },
}));

Alpine.data('bookingPreview', (config = {}) => ({
    serviceId: config.serviceId ?? '',
    months: config.months ?? [],
    month: 0,
    date: config.date ?? '',
    slots: [],
    loading: false,
    loadedFor: '',
    availabilityUrl: config.availabilityUrl,

    async loadSlots() {
        if (! this.serviceId || ! this.date) {
            this.slots = [];
            return;
        }

        const key = this.serviceId + '|' + this.date;

        if (this.loadedFor === key) {
            return;
        }

        this.loading = true;

        try {
            const params = new URLSearchParams({ service: this.serviceId, date: this.date });
            const response = await fetch(this.availabilityUrl + '?' + params, {
                headers: { Accept: 'application/json' },
            });
            const data = await response.json();
            this.slots = data.slots || [];
            this.loadedFor = key;
        } catch (error) {
            this.slots = [];
        } finally {
            this.loading = false;
        }
    },

    selectDate(cell) {
        this.date = cell.value;
        this.loadedFor = '';
        this.loadSlots();
    },

    previousMonth() {
        this.month = Math.max(0, this.month - 1);
    },

    nextMonth() {
        this.month = Math.min(this.months.length - 1, this.month + 1);
    },
}));

Alpine.data('bookingWidget', (config = {}) => ({
    services: config.services ?? [],
    months: config.months ?? [],
    staff: config.staff ?? [],
    serviceId: config.serviceId ?? '',
    staffId: '',
    month: 0,
    date: config.date ?? '',
    slots: [],
    startsAt: '',
    loading: false,
    loadedFor: '',
    whatsappUrl: null,
    sending: false,
    availabilityUrl: config.availabilityUrl,
    whatsappEndpoint: config.whatsappEndpoint,
    csrf: config.csrf,

    async sendWhatsApp() {
        if (! this.startsAt || this.sending) {
            return;
        }

        this.sending = true;

        const body = {
            service: this.serviceId,
            staff: this.staffId || null,
            starts_at: this.startsAt,
            name: document.getElementById('name')?.value?.trim(),
            phone: document.getElementById('phone')?.value?.trim(),
            notes: document.getElementById('notes')?.value?.trim() || null,
        };

        try {
            const response = await fetch(this.whatsappEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                },
                body: JSON.stringify(body),
            });

            const data = await response.json();
            this.whatsappUrl = data.url;

            if (data.url) {
                window.open(data.url, '_blank');
            }
        } catch (error) {
            this.whatsappUrl = null;
        } finally {
            this.sending = false;
        }
    },

    get service() {
        return this.services.find((service) => service.id === Number(this.serviceId));
    },

    get slotLabel() {
        const slot = this.slots.find((current) => current.starts_at === this.startsAt);

        return slot ? slot.label : '';
    },

    async loadSlots() {
        if (! this.serviceId || ! this.date) {
            this.slots = [];
            return;
        }

        const key = this.serviceId + '|' + this.date + '|' + this.staffId;

        if (this.loadedFor === key) {
            return;
        }

        this.loading = true;
        this.startsAt = '';

        const params = new URLSearchParams({ service: this.serviceId, date: this.date });

        if (this.staffId) {
            params.set('staff', this.staffId);
        }

        try {
            const response = await fetch(this.availabilityUrl + '?' + params, {
                headers: { Accept: 'application/json' },
            });
            const data = await response.json();
            this.slots = data.slots || [];
            this.loadedFor = key;
        } catch (error) {
            this.slots = [];
        } finally {
            this.loading = false;
        }
    },

    get dueLabel() {
        if (! this.service) {
            return '';
        }

        return this.service.hasDeposit ? this.service.due + ' to book' : this.service.price;
    },

    selectService(id) {
        this.serviceId = String(id);
        this.loadedFor = '';
        this.loadSlots();
    },

    selectDate(cell) {
        this.date = cell.value;
        this.loadedFor = '';
        this.loadSlots();
    },

    selectSlot(slot) {
        this.startsAt = slot.starts_at;
    },

    previousMonth() {
        this.month = Math.max(0, this.month - 1);
    },

    nextMonth() {
        this.month = Math.min(this.months.length - 1, this.month + 1);
    },
}));

Alpine.data('storeAssistant', (config = {}) => ({
    open: false,
    loading: false,
    message: '',
    answer: '',
    history: [],

    async ask() {
        const message = this.message.trim();

        if (! message || this.loading) {
            return;
        }

        this.loading = true;
        this.answer = '';

        try {
            const response = await fetch(config.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrf,
                },
                body: JSON.stringify({ message, history: this.history.slice(-8) }),
            });

            const data = await response.json();

            if (! response.ok) {
                throw new Error(data.message || 'The assistant is unavailable right now.');
            }

            this.answer = data.answer;
            this.history.push({ role: 'user', content: message }, { role: 'assistant', content: data.answer });
            this.message = '';
        } catch (error) {
            this.answer = error.message;
        } finally {
            this.loading = false;
        }
    },
}));

Alpine.data('storefrontEditor', (config = {}) => ({
    aiLoading: false,
    aiDraft: null,
    aiError: '',

    async draftPageCopy() {
        this.aiLoading = true;
        this.aiDraft = null;
        this.aiError = '';

        try {
            const response = await fetch(config.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrf,
                },
                body: JSON.stringify({
                    feature: config.feature,
                    input: { text: config.text, tone: 'friendly' },
                }),
            });

            const data = await response.json();

            if (response.ok) {
                this.aiDraft = data.result;
            } else {
                this.aiError = data.message || 'AI could not prepare page copy.';
            }
        } catch (error) {
            this.aiError = 'AI could not prepare page copy.';
        } finally {
            this.aiLoading = false;
        }
    },

    format(value) {
        return typeof value === 'string' ? value : JSON.stringify(value, null, 2);
    },

    openAiForField(event) {
        const field = event.currentTarget.closest('label');
        const textarea = field ? field.querySelector('textarea') : null;

        this.$dispatch('ai.open', { text: textarea ? textarea.value : '' });
    },
}));

Alpine.data('sharePage', (config = {}) => ({
    shareUrl: config.url ?? '',
    shareTitle: config.title ?? '',

    async share() {
        if (navigator.share) {
            await navigator.share({ title: this.shareTitle, url: this.shareUrl });
            return;
        }

        if (navigator.clipboard) {
            await navigator.clipboard.writeText(this.shareUrl);
        }
    },
}));

Alpine.data('addToCartButton', (config = {}) => ({
    adding: false,

    add() {
        this.adding = true;
        this.$store.cart.add({
            id: config.id,
            name: config.name,
            price_kobo: config.priceKobo,
        }, 1);
        requestAnimationFrame(() => {
            this.adding = false;
        });
    },
}));
