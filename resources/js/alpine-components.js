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
    fulfilment: config.defaultFulfilment ?? 'delivery',
    zones: config.deliveryZones ?? [],
    zoneId: config.selectedZoneId ?? null,
    freeOverKobo: config.freeOverKobo ?? null,

    /**
     * A preview only. The server quotes the same rules again on submit and the
     * order keeps that figure, never this one.
     */
    get deliveryFeeKobo() {
        if (this.fulfilment !== 'delivery') {
            return 0;
        }

        const zone = this.zones.find((item) => Number(item.id) === Number(this.zoneId));

        if (! zone) {
            return 0;
        }

        if (this.freeOverKobo !== null && this.$store.cart.totalKobo >= this.freeOverKobo) {
            return 0;
        }

        return Number(zone.fee_kobo) || 0;
    },

    get orderTotalKobo() {
        return this.$store.cart.totalKobo + this.deliveryFeeKobo;
    },

    get deliveryFeeLabel() {
        return this.deliveryFeeKobo === 0
            ? 'Free'
            : '₦' + (this.deliveryFeeKobo / 100).toLocaleString();
    },

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

Alpine.data('aiDraftReview', (config = {}) => ({
    id: config.id,
    status: config.status,
    feature: config.feature,
    busy: false,
    error: '',
    message: '',
    payload: {
        name: config.payload?.name ?? '',
        description: config.payload?.description ?? '',
        category: config.payload?.category ?? '',
        price_hint: config.payload?.price_hint ?? '',
        tags: Array.isArray(config.payload?.tags)
            ? config.payload.tags.join(', ')
            : (config.payload?.tags ?? ''),
        seo_title: config.payload?.seo_title ?? '',
        seo_description: config.payload?.seo_description ?? '',
    },

    get isPending() {
        return this.status === 'pending';
    },

    get isListing() {
        return ['products', 'services'].includes(config.feature);
    },

    get isApproved() {
        return this.status === 'approved';
    },

    get presets() {
        return Array.isArray(config.payload?.presets) ? config.payload.presets : [];
    },

    get hasPresets() {
        return this.presets.length > 0;
    },

    get isDirection() {
        return Boolean(config.payload?.theme_key && config.payload?.copy);
    },

    list(value) {
        return String(value ?? '')
            .split(',')
            .map((item) => item.trim())
            .filter(Boolean);
    },

    async send(url, method = 'POST', body = null) {
        const options = {
            method,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': config.csrf,
            },
        };

        if (body !== null) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }

        const response = await fetch(url, options);

        let data = {};
        try {
            data = await response.json();
        } catch (error) {
            data = {};
        }

        if (! response.ok) {
            throw new Error(data.message || 'The request could not be completed.');
        }

        return data;
    },

    async save() {
        await this.send(config.updateUrl, 'PATCH', {
            name: this.payload.name,
            description: this.payload.description,
            category: this.payload.category || null,
            price_hint: this.payload.price_hint || null,
            tags: this.list(this.payload.tags),
            seo_title: this.payload.seo_title || null,
            seo_description: this.payload.seo_description || null,
        });
    },

    async approve() {
        if (this.hasPresets) {
            return;
        }

        this.busy = true;
        this.error = '';

        try {
            if (config.feature === 'products' || config.feature === 'services') {
                await this.save();
            }
            await this.send(config.approveUrl);
            this.status = 'approved';
            this.message = this.isDirection
                ? 'Direction applied. You can change it again from the Brand tab.'
                : 'Approved. The listing is now in your catalogue as a draft.';
        } catch (error) {
            this.error = error.message;
        } finally {
            this.busy = false;
        }
    },

    async applyPreset(index) {
        this.busy = true;
        this.error = '';

        try {
            await this.send(config.approveUrl, 'POST', { preset: index });
            this.status = 'approved';
            this.message = 'Direction applied. You can change it again from the Brand tab.';
        } catch (error) {
            this.error = error.message;
        } finally {
            this.busy = false;
        }
    },

    async discard() {
        this.busy = true;
        this.error = '';

        try {
            await this.send(config.discardUrl);
            this.status = 'discarded';
            this.message = 'Draft discarded.';
        } catch (error) {
            this.error = error.message;
        } finally {
            this.busy = false;
        }
    },

    async undo() {
        this.busy = true;
        this.error = '';

        try {
            await this.send(config.undoUrl);
            this.status = 'undone';
            this.message = 'The approved change was undone.';
        } catch (error) {
            this.error = error.message;
        } finally {
            this.busy = false;
        }
    },
}));

Alpine.data('hoursEditor', (config = {}) => ({
    placeholder: config.placeholder ?? 'Monday to Friday, 9am - 6pm',
    rows: String(config.value ?? '')
        .split('\n')
        .map((row) => row.trim())
        .filter(Boolean),

    get joined() {
        return this.rows.map((row) => row.trim()).filter(Boolean).join('\n');
    },

    add() {
        this.rows.push('');
        this.$nextTick(() => {
            const inputs = this.$el.querySelectorAll('input[type="text"]');
            inputs[inputs.length - 1]?.focus();
        });
    },

    remove(index) {
        this.rows.splice(index, 1);
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

Alpine.data('storeSearch', (config = {}) => ({
    open: false,
    loading: false,
    searched: false,
    message: '',
    results: [],
    applied: {},
    questions: [],
    mode: 'ai',
    error: '',
    controller: null,

    openPanel() {
        this.open = true;
        document.body.classList.add('sheet-open');
        this.$nextTick(() => {
            if (this.$refs.input) {
                this.$refs.input.focus();
            }
        });
    },

    chooseQuestion(question) {
        this.message = question;
        this.$nextTick(() => {
            if (this.$refs.input) {
                this.$refs.input.focus();
            }
        });
    },

    closePanel() {
        this.open = false;
        this.controller?.abort();
        this.loading = false;
        document.body.classList.remove('sheet-open');
    },

    get appliedChips() {
        const chips = [];

        if (this.applied.q) {
            chips.push('“' + this.applied.q + '”');
        }
        if (this.applied.budget_min && this.applied.budget_max) {
            chips.push('₦' + this.applied.budget_min + ' – ₦' + this.applied.budget_max);
        } else if (this.applied.budget_max) {
            chips.push('Under ₦' + this.applied.budget_max);
        } else if (this.applied.budget_min) {
            chips.push('From ₦' + this.applied.budget_min);
        }
        if (this.applied.kind) {
            chips.push(this.applied.kind === 'service' ? 'Services only' : 'Products only');
        }

        return chips;
    },

    async submit() {
        const message = this.message.trim();

        if (!message || this.loading) {
            return;
        }

        this.controller?.abort();
        this.controller = new AbortController();

        this.loading = true;
        this.error = '';
        this.searched = false;
        this.results = [];
        this.questions = [];

        try {
            const response = await fetch(config.endpoint, {
                method: 'POST',
                signal: this.controller.signal,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrf,
                },
                body: JSON.stringify({ message }),
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(data.message || 'Search is unavailable right now. Please try again.');
            }

            this.results = Array.isArray(data.results) ? data.results : [];
            this.applied = data.applied || {};
            this.questions = Array.isArray(data.questions) ? data.questions.slice(0, 2) : [];
            this.mode = data.mode === 'keyword' ? 'keyword' : 'ai';
            this.searched = true;
            this.message = '';
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.error = error.message || 'Something went wrong. Please try again.';
            }
        } finally {
            this.loading = false;
        }
    },
}));

Alpine.data('storefrontEditor', (config = {}) => ({
    aiLoading: false,
    aiDraft: null,
    aiDraftId: null,
    aiError: '',
    aiOpen: false,
    aiBusy: false,
    aiMessage: '',

    get aiPresets() {
        return Array.isArray(this.aiDraft?.presets) ? this.aiDraft.presets : [];
    },

    get aiText() {
        return typeof this.aiDraft?.text === 'string' ? this.aiDraft.text : '';
    },

    get hasPresets() {
        return this.aiPresets.length > 0;
    },

    get hasDirection() {
        return !!(this.aiDraft && (this.aiDraft.theme_key || this.aiDraft.copy));
    },

    get directionThemeLabel() {
        return config.preview?.presetLabels?.[this.aiDraft?.theme_key] ?? this.aiDraft?.theme_key ?? '';
    },

    get directionSurfaceLabel() {
        return config.preview?.surfaceLabels?.[this.aiDraft?.surface] ?? this.aiDraft?.surface ?? '';
    },

    get directionRadiusLabel() {
        return config.preview?.radiusLabels?.[this.aiDraft?.radius] ?? this.aiDraft?.radius ?? '';
    },

    get directionHeroStyleLabel() {
        return config.preview?.heroStyles?.[this.aiDraft?.hero_style] ?? this.aiDraft?.hero_style ?? '';
    },

    get directionHeroAlignmentLabel() {
        return config.preview?.heroAlignments?.[this.aiDraft?.hero_alignment] ?? this.aiDraft?.hero_alignment ?? '';
    },

    get directionHeroOverlayLabel() {
        return config.preview?.heroOverlays?.[this.aiDraft?.hero_overlay] ?? this.aiDraft?.hero_overlay ?? '';
    },

    get directionHiddenLabels() {
        return (this.aiDraft?.hidden_sections ?? [])
            .map((key) => config.preview?.hiddenSections?.[key] ?? key);
    },

    get copySections() {
        const copy = this.aiDraft?.copy ?? {};
        const pages = config.preview?.pages ?? [];

        return pages
            .map((page) => ({
                key: page.key,
                label: page.label,
                blocks: (page.blocks ?? [])
                    .map((block) => ({
                        key: block.key,
                        label: block.label,
                        fields: (block.fields ?? []).filter((field) => copy[page.key]?.[block.key]?.[field.name] !== undefined),
                    }))
                    .filter((block) => block.fields.length > 0),
            }))
            .filter((page) => page.blocks.length > 0);
    },

    repeaterRows(pageKey, blockKey, fieldName) {
        return this.aiDraft?.copy?.[pageKey]?.[blockKey]?.[fieldName] ?? [];
    },

    pageCopySource() {
        // The copy-rewrite task rewrites text; handing it an instruction made it
        // rewrite the instruction. Use the seller's actual page copy instead.
        const tagline = this.$root.querySelector('input[name="tagline"]');
        const copy = Array.from(this.$root.querySelectorAll('textarea[name^="blocks["], input[type="text"][name^="blocks["]'))
            .map((field) => field.value.trim())
            .filter(Boolean);

        if (tagline?.value.trim()) {
            copy.unshift(tagline.value.trim());
        }

        return copy.join('\n\n');
    },

    async draftPageCopy() {
        this.aiLoading = true;
        this.aiDraft = null;
        this.aiDraftId = null;
        this.aiError = '';
        this.aiMessage = '';
        this.aiOpen = false;

        const text = config.feature === 'appearance' ? (config.text ?? '') : this.pageCopySource();

        if (config.feature !== 'appearance' && ! text) {
            this.aiError = 'Add some copy to this page first, then Mercho can suggest an improvement.';
            this.aiLoading = false;

            return;
        }

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
                    input: { text, tone: 'friendly' },
                }),
            });

            const data = await response.json();

            if (response.ok) {
                this.aiDraft = data.result;
                this.aiDraftId = data.draft_id || null;
                this.aiOpen = true;
            } else {
                this.aiError = data.message || 'AI could not prepare page copy.';
            }
        } catch (error) {
            this.aiError = 'AI could not prepare page copy.';
        } finally {
            this.aiLoading = false;
        }
    },

    draftUrl(template, id) {
        return String(template || '').replace('__DRAFT__', String(id));
    },

    async aiRequest(url, body = null) {
        const options = {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': config.csrf,
            },
        };

        if (body !== null) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }

        const response = await fetch(url, options);

        let data = {};
        try {
            data = await response.json();
        } catch (error) {
            data = {};
        }

        if (! response.ok) {
            throw new Error(data.message || 'The draft could not be saved.');
        }

        return data;
    },

    async applyPreset(index) {
        this.aiBusy = true;
        this.aiError = '';

        try {
            await this.aiRequest(this.draftUrl(config.approveUrl, this.aiDraftId), { preset: index });
            window.location.reload();
        } catch (error) {
            this.aiError = error.message;
        } finally {
            this.aiBusy = false;
        }
    },

    async approveDraft() {
        this.aiBusy = true;
        this.aiError = '';

        try {
            await this.aiRequest(
                this.draftUrl(config.approveUrl, this.aiDraftId),
                this.hasDirection ? { copy: this.aiDraft.copy } : null,
            );

            if (this.hasDirection) {
                window.location.reload();

                return;
            }

            this.aiOpen = false;
            this.aiMessage = 'Draft approved.';
        } catch (error) {
            this.aiError = error.message;
        } finally {
            this.aiBusy = false;
        }
    },

    async discardDraft() {
        this.aiBusy = true;
        this.aiError = '';

        try {
            await this.aiRequest(this.draftUrl(config.discardUrl, this.aiDraftId));
            this.aiOpen = false;
            this.aiDraft = null;
            this.aiDraftId = null;
        } catch (error) {
            this.aiError = error.message;
        } finally {
            this.aiBusy = false;
        }
    },

    closeAi() {
        this.aiOpen = false;
        this.aiError = '';
    },

    async copyAiText() {
        if (! this.aiText) {
            return;
        }

        try {
            await navigator.clipboard.writeText(this.aiText);
            this.aiMessage = 'Suggestion copied. Paste it into the fields you want to keep.';
        } catch (error) {
            this.aiError = 'Copying is not available in this browser. Select the text and copy it manually.';
        }
    },

    format(value) {
        return typeof value === 'string' ? value : JSON.stringify(value, null, 2);
    },

    openAiForField(event) {
        const field = event.currentTarget.closest('label');
        const textarea = field ? field.querySelector('textarea') : null;

        this.$dispatch('ai-open', { text: textarea ? textarea.value : '' });
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

Alpine.data('categoryAiPreview', (config = {}) => ({
    loading: ! config.result,
    result: config.result ?? null,
    applyUrl: config.applyUrl ?? '',
    error: '',
    timer: null,

    init() {
        if (! this.result) {
            this.poll();
        }
    },

    async poll() {
        try {
            const response = await fetch(config.statusUrl, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            const task = data.task || {};

            if (['completed', 'partially_completed', 'failed', 'cancelled'].includes(task.status)) {
                const operation = (task.operations || []).find((item) => item.status === 'succeeded');
                this.result = operation ? operation.output : null;
                this.loading = false;

                if (! this.result) {
                    this.error = task.status === 'cancelled'
                        ? 'Category generation was cancelled.'
                        : 'Category generation failed. Try again.';
                }
            } else {
                this.timer = window.setTimeout(() => this.poll(), 2000);
            }
        } catch (error) {
            this.error = 'Could not load category suggestions.';
            this.loading = false;
        }
    },
}));

// Registered components keep the CSP build of Alpine honest: expressions inside
// x-data may not declare functions, so the shell state and the delivery-zone
// editor live here instead of in template strings.
Alpine.data('themeShell', (config = {}) => ({
    mobileNavOpen: false,
    scrolled: config.scrolled ?? true,

    init() {
        window.addEventListener('scroll', () => {
            this.scrolled = window.pageYOffset > 24;
        }, { passive: true });
    },

    closeMobileNav() {
        this.mobileNavOpen = false;
    },

    dispatchAndClose(name) {
        this.mobileNavOpen = false;
        this.$dispatch(name);
    },
}));

Alpine.data('deliveryZoneForm', (config = {}) => ({
    rows: config.rows ?? [],

    addRow() {
        this.rows.push({ name: '', state: '', fee: '' });
    },

    removeRow(index) {
        this.rows.splice(index, 1);
    },
}));
