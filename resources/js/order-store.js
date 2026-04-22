/**
 * Alpine.store('cart') — Guest Order UI
 *
 * Responsibilities:
 *   - Catalog data (read-only, injected once via #guest-order-data JSON)
 *   - Translations (i18n dictionary, same payload)
 *   - Cart state (lines[], qty, totals) — Alpine SSOT, zero server round-trips
 *   - Modal (Bottom-sheet open/close, selected style/toppings)
 *   - Cart panel (full-screen drawer: review, qty, remove, duplicate, clear)
 *   - Scroll-spy (IntersectionObserver + near-bottom fallback)
 *   - Transmission draft builder (Ver2: POST to table POS; never push directly to KDS)
 *
 * Hydration Standard:
 *   - Do NOT import alpinejs from npm — Livewire bundles Alpine on window.Alpine.
 *   - Register this store inside document.addEventListener('alpine:init', ...).
 *
 * Kitchen / KDS pipeline (operational contract — see docs/order-system-ver1.md):
 *   Guest device -> (HTTP, non-RT) -> Table POS -> staff "Recu staff" -> confirmed ->
 *   (RT) -> Kitchen KDS. buildTransmissionDraft() shapes the guest-side payload only;
 *   server must attach immutability snapshots and route to KDS only after confirmation.
 */

const GUEST_ORDER_CLIENT_SESSION_KEY = 'guest_order_client_session_v1';

/**
 * When the page is restored from bfcache, or a stale in-memory cart lingers,
 * `lines[].itemId` can reference another shop's `menu_items.id` (ids are
 * unique rows; server enforces by `shop_id` → "Unknown menu item.").
 * We drop any line not in the current #guest-order-data catalog.
 */

/**
 * Read and parse the JSON payload embedded in #guest-order-data.
 *
 * @returns {{ catalog: object, translations: Record<string, string>, categoryIds: string[], context: object }}
 */
function readGuestOrderPayload() {
    const el = document.getElementById('guest-order-data');
    if (!el || el.textContent === null || el.textContent.trim() === '') {
        return {
            catalog:       { meta: {}, categories: [] },
            translations: {},
            categoryIds:   [],
            context:       { tenantSlug: '', tableToken: '' },
        };
    }
    try {
        return JSON.parse(el.textContent);
    } catch {
        return {
            catalog:        { meta: {}, categories: [] },
            translations:   {},
            categoryIds:    [],
            context:        { tenantSlug: '', tableToken: '' },
        };
    }
}

/**
 * @param {unknown} line
 * @returns {boolean}
 */
function isCartLine(line) {
    return (
        line !== null
        && typeof line === 'object'
        && typeof line.lineId === 'string'
        && typeof line.mergeKey === 'string'
    );
}

/**
 * Toast banner without Blade changes (JS-only DOM).
 *
 * @param {string} message
 * @param {boolean} [isError]
 */
function showGuestOrderFlash(message, isError = false) {
    const wrap = document.createElement('div');
    wrap.className = 'pointer-events-none fixed inset-x-0 top-20 z-[90] flex justify-center px-4';
    const span = document.createElement('span');
    span.className = isError
        ? 'rounded-full bg-red-700 px-3 py-1.5 text-[11px] font-bold text-white shadow'
        : 'rounded-full bg-slate-900 px-3 py-1.5 text-[11px] font-bold text-white shadow';
    span.textContent = message;
    wrap.appendChild(span);
    document.body.appendChild(wrap);
    window.setTimeout(() => wrap.remove(), 2800);
}

/**
 * @returns {object | null}
 */
function findGuestMenuLivewireComponent() {
    const root = document.getElementById('guest-order-root');
    if (!root || typeof window.Livewire === 'undefined' || typeof window.Livewire.find !== 'function') {
        return null;
    }
    const wireId = root.getAttribute('wire:id');
    if (!wireId) {
        return null;
    }
    return window.Livewire.find(wireId);
}

let guestOrderLivewireListenersBound = false;

/**
 * Livewire dispatches server params as CustomEvent.detail (object or single-element array).
 *
 * @param {unknown} detail
 * @returns {Record<string, unknown>}
 */
function normalizeLivewireEventDetail(detail) {
    if (detail === null || detail === undefined) {
        return {};
    }
    if (typeof detail === 'object' && !Array.isArray(detail)) {
        return /** @type {Record<string, unknown>} */ (detail);
    }
    if (Array.isArray(detail) && detail.length === 1 && typeof detail[0] === 'object' && detail[0] !== null && !Array.isArray(detail[0])) {
        return /** @type {Record<string, unknown>} */ (detail[0]);
    }
    return {};
}

function registerGuestOrderLivewireListeners() {
    const Lw = window.Livewire;
    if (!Lw || typeof Lw.on !== 'function' || guestOrderLivewireListenersBound) {
        return;
    }

    guestOrderLivewireListenersBound = true;

    Lw.on('guest-order-saved', () => {
        const Alpine = window.Alpine;
        if (!Alpine || typeof Alpine.store !== 'function') {
            return;
        }
        try {
            const cart = Alpine.store('cart');
            cart.lines = [];
            // Must use closeCartPanel (not only cartPanelOpen=false): openCartPanel()
            // acquires a body scroll lock; releasing it restores scrolling after success.
            if (typeof cart.closeCartPanel === 'function') {
                cart.closeCartPanel({ restoreScroll: true });
            } else {
                cart.cartPanelOpen = false;
                cart.dismissToast();
            }
            if (typeof cart.clearSubmitIdempotencyKey === 'function') {
                cart.clearSubmitIdempotencyKey();
            }
            const msg = typeof cart.t === 'function' ? cart.t('order_sent') : 'OK';
            showGuestOrderFlash(msg, false);
        } catch {
            showGuestOrderFlash('OK', false);
        }
    });

    Lw.on('guest-order-error', (detail) => {
        const bag = normalizeLivewireEventDetail(detail);
        const raw = typeof detail === 'string'
            ? detail
            : (bag.message ?? 'Error');
        showGuestOrderFlash(String(raw), true);
    });
}

document.addEventListener('livewire:init', registerGuestOrderLivewireListeners);

document.addEventListener('alpine:init', () => {
    registerGuestOrderLivewireListeners();

    const Alpine = window.Alpine;

    Alpine.store('cart', {

        // ─── Data from #guest-order-data (bootstrapFromDom) ───────────────────
        catalog: { meta: {}, categories: [] },
        translations: {},
        categoryIds: [],
        contextTenantSlug: '',
        contextTableToken: '',

        // ─── Ver2: stable per-tab id for correlating multi-device / retries ─────
        clientSessionId: '',

        /** Per-submit intent: reused on retry until success or cart mutation (Phase 2 idempotency). */
        _submitIdempotencyKey: null,

        // ─── Scroll-spy ───────────────────────────────────────────────────────────
        activeCategoryId: null,
        _nearBottomLocked: false,
        _cartGlowTimeoutId: null,

        // ─── Body scroll lock (sheet + cart panel, refcount) ───────────────────────
        _bodyScrollLockCount: 0,

        // ─── Cart ─────────────────────────────────────────────────────────────────
        lines: [],
        flyEffects: [],
        cartGlow: false,

        // ─── Cart panel (Uber/Glovo-style drawer) ─────────────────────────────────
        cartPanelOpen: false,
        _menuScrollY: 0,
        panelPulseTotal: false,
        _clearCartStep: 0,
        _clearCartResetTimerId: null,

        // ─── Toast / undo ──────────────────────────────────────────────────────────
        toast: null, // { type: 'removed', line: CartLine } | null
        _toastTimerId: null,
        clipboardBanner: false,

        // ─── Bottom-sheet modal ───────────────────────────────────────────────────
        sheetOpen: false,
        editingItem: null,
        /** Sheet line qty (reserved; add-to-cart currently uses 1 per tap). */
        quantity: 1,
        selectedStyleId: null,
        selectedToppingIds: [],
        _scrollPos: 0,

        // ─── Hydration bootstrap ─────────────────────────────────────────────────

        itemIdExistsInCatalog(itemId) {
            const k = String(itemId);
            for (const cat of this.catalog?.categories ?? []) {
                for (const it of cat.items ?? []) {
                    if (String(it.id) === k) {
                        return true;
                    }
                }
            }
            return false;
        },

        /**
         * Remove cart lines that are not in the current embedded catalog
         * (e.g. tenant was switched, or bfcache restored a stale in-memory cart).
         */
        pruneCartToCatalog() {
            if (!Array.isArray(this.lines) || this.lines.length === 0) {
                return;
            }
            const before = this.lines.length;
            this.lines = this.lines.filter((l) => this.itemIdExistsInCatalog(l?.itemId));
            if (this.lines.length === before) {
                return;
            }
            this.clearSubmitIdempotencyKey();
            if (typeof this.dismissToast === 'function') {
                this.dismissToast();
            }
        },

        bootstrapFromDom() {
            const payload = readGuestOrderPayload();
            this.catalog         = payload.catalog ?? { meta: {}, categories: [] };
            this.translations     = payload.translations ?? {};
            this.categoryIds      = Array.isArray(payload.categoryIds) ? payload.categoryIds : [];
            const ctx             = payload.context ?? {};
            this.contextTenantSlug = String(ctx.tenantSlug ?? '');
            this.contextTableToken = String(ctx.tableToken ?? '');
            this.clientSessionId   = this.ensureClientSessionId();
            this.clearSubmitIdempotencyKey();

            this.pruneCartToCatalog();

            queueMicrotask(() => {
                this.initScrollSpyWithEndSnap(this.categoryIds);
            });
        },

        clearSubmitIdempotencyKey() {
            this._submitIdempotencyKey = null;
        },

        // ─── Body scroll lock ─────────────────────────────────────────────────────

        acquireBodyScrollLock() {
            this._bodyScrollLockCount += 1;
            if (this._bodyScrollLockCount === 1) {
                document.documentElement.classList.add('overflow-hidden');
            }
        },

        releaseBodyScrollLock() {
            this._bodyScrollLockCount = Math.max(0, this._bodyScrollLockCount - 1);
            if (this._bodyScrollLockCount === 0) {
                document.documentElement.classList.remove('overflow-hidden');
            }
        },

        // ─── Client session (sessionStorage) ─────────────────────────────────────

        ensureClientSessionId() {
            try {
                const existing = window.sessionStorage?.getItem(GUEST_ORDER_CLIENT_SESSION_KEY);
                if (existing && existing.length > 8) {
                    return existing;
                }
                const id = crypto.randomUUID();
                window.sessionStorage?.setItem(GUEST_ORDER_CLIENT_SESSION_KEY, id);
                return id;
            } catch {
                return crypto.randomUUID();
            }
        },

        // ─── i18n ─────────────────────────────────────────────────────────────────

        t(key, params = {}) {
            let text = Object.prototype.hasOwnProperty.call(this.translations, key)
                ? String(this.translations[key])
                : key;
            for (const [k, v] of Object.entries(params)) {
                text = text.replaceAll(':' + k, String(v));
            }
            return text;
        },

        formatMinorToDisplay(minor) {
            const n = Number(minor);
            const safe = Number.isFinite(n) ? Math.max(0, n) : 0;
            const divisor = Number(this.catalog?.meta?.price_divisor) > 0
                ? Number(this.catalog.meta.price_divisor)
                : 1000;
            const snapped = Math.round(safe / 500) * 500;
            if (snapped % 1000 === 0) {
                return String(snapped / divisor) + ' DT';
            }
            const whole = Math.floor(snapped / 1000);
            return `${whole}.5 DT`;
        },

        // ─── Cart getters ─────────────────────────────────────────────────────────

        lineCount() {
            return this.lines.reduce((sum, l) => sum + l.qty, 0);
        },

        cartTotalMinor() {
            return this.lines.reduce((sum, l) => sum + l.lineTotalMinor, 0);
        },

        sheetUnitTotalMinor() {
            const item = this.editingItem;
            if (!item) return 0;

            const style = Array.isArray(item.styles)
                ? item.styles.find(s => s.id === this.selectedStyleId) ?? null
                : null;

            const base = style
                ? Number(style.price_minor)
                : Number(item.from_price_minor ?? 0);

            const toppingDelta = this.selectedToppingIds.reduce((sum, id) => {
                const t = Array.isArray(item.toppings)
                    ? item.toppings.find(tp => tp.id === id)
                    : null;
                return sum + (t ? Number(t.price_delta_minor) : 0);
            }, 0);

            return base + toppingDelta;
        },

        canAddToCart() {
            if (!this.editingItem) return false;
            if (this.editingItem.rules?.style_required && !this.selectedStyleId) return false;
            return true;
        },

        // ─── Cart panel ───────────────────────────────────────────────────────────

        openCartPanel() {
            if (this.sheetOpen) {
                this.closeSheet();
            }
            this._clearCartStep = 0;
            this._menuScrollY = window.scrollY;
            this.acquireBodyScrollLock();
            this.cartPanelOpen = true;
        },

        /**
         * @param {{ restoreScroll?: boolean }} [opts]
         */
        closeCartPanel(opts = {}) {
            const restoreScroll = opts.restoreScroll !== false;
            this.cartPanelOpen = false;
            this._clearCartStep = 0;
            this.dismissToast();
            this.releaseBodyScrollLock();
            if (restoreScroll) {
                window.scrollTo(0, this._menuScrollY);
            }
        },

        continueShopping() {
            this.closeCartPanel({ restoreScroll: true });
        },

        pulseTotalRow() {
            this.panelPulseTotal = true;
            window.setTimeout(() => {
                this.panelPulseTotal = false;
            }, 450);
        },

        // ─── Bottom-sheet ─────────────────────────────────────────────────────────

        openSheet(itemId) {
            if (this.cartPanelOpen) {
                this.closeCartPanel({ restoreScroll: false });
            }

            const flat = Array.isArray(window.sharedMenuData) ? window.sharedMenuData : [];
            let item = flat.find((i) => i != null && String(i.id) === String(itemId)) ?? null;

            if (!item) {
                for (const cat of (this.catalog.categories ?? [])) {
                    item = (cat.items ?? []).find((i) => String(i.id) === String(itemId)) ?? null;
                    if (item) break;
                }
            }
            if (!item) return;

            this.editingItem = JSON.parse(JSON.stringify(item));
            this.quantity = 1;
            this.selectedStyleId = null;
            this.selectedToppingIds = [];
            this._scrollPos = window.scrollY;
            this.acquireBodyScrollLock();
            this.sheetOpen = true;
        },

        closeSheet() {
            this.sheetOpen = false;
            this.editingItem = null;
            this.quantity = 1;
            this.selectedStyleId = null;
            this.selectedToppingIds = [];
            this.releaseBodyScrollLock();
            window.scrollTo(0, this._scrollPos);
        },

        toggleTopping(id) {
            if (this.selectedToppingIds.includes(id)) {
                this.selectedToppingIds = this.selectedToppingIds.filter(t => t !== id);
            } else {
                this.selectedToppingIds = [...this.selectedToppingIds, id];
            }
        },

        // ─── Cart mutation ────────────────────────────────────────────────────────

        buildCartLineFromModal() {
            const item   = this.editingItem;
            const style  = Array.isArray(item.styles)
                ? item.styles.find(s => s.id === this.selectedStyleId) ?? null
                : null;

            const toppingSnapshots = this.selectedToppingIds.map(id => {
                const src = Array.isArray(item.toppings)
                    ? item.toppings.find(t => t.id === id)
                    : null;
                return {
                    id:              String(src?.id ?? id),
                    name:            String(src?.name ?? ''),
                    priceDeltaMinor: Number(src?.price_delta_minor ?? 0),
                };
            });

            const sortedToppingIds  = [...this.selectedToppingIds].sort();
            const mergeKey          = `${String(item.id)}|${this.selectedStyleId ?? '__none__'}|${sortedToppingIds.join(',')}`;
            const stylePriceMinor   = style ? Number(style.price_minor) : Number(item.from_price_minor ?? 0);
            const toppingDeltaMinor = toppingSnapshots.reduce((s, t) => s + t.priceDeltaMinor, 0);
            const unitLineTotalMinor = stylePriceMinor + toppingDeltaMinor;

            return {
                lineId:              crypto.randomUUID(),
                mergeKey,
                itemId:              String(item.id),
                titleSnapshot:       String(item.name),
                kitchenNameSnapshot: String(item.kitchen_name || item.name),
                styleId:             this.selectedStyleId ? String(this.selectedStyleId) : null,
                styleNameSnapshot:   style ? String(style.name) : null,
                stylePriceMinor,
                toppingSnapshots,
                unitLineTotalMinor,
                lineTotalMinor:      unitLineTotalMinor,
                qty:                 1,
                note:                '',
            };
        },

        addToCart(clickEvent = null) {
            if (!this.canAddToCart()) return;

            const newLine = this.buildCartLineFromModal();
            let added = false;

            const mergingEnabled =
                this.catalog.meta?.merge_identical_lines !== false
                && this.editingItem?.rules?.merge_identical_lines !== false;

            if (mergingEnabled) {
                const existing = this.lines.find(l => l.mergeKey === newLine.mergeKey);
                if (existing) {
                    existing.qty += 1;
                    existing.lineTotalMinor = existing.unitLineTotalMinor * existing.qty;
                    this.lines = [...this.lines];
                    added = true;
                }
            }

            if (!added) {
                this.lines = [...this.lines, newLine];
            }

            this.clearSubmitIdempotencyKey();

            const origin = this.resolveFxOrigin(clickEvent);
            this.playCartFx(origin);
            if (this.cartPanelOpen) {
                this.pulseTotalRow();
            }
            this.closeSheet();
        },

        playCartFx(origin = null) {
            this.triggerCartGlow();
            this.spawnFlyEffect(origin);
        },

        triggerCartGlow() {
            this.cartGlow = true;
            if (this._cartGlowTimeoutId !== null) {
                clearTimeout(this._cartGlowTimeoutId);
            }
            this._cartGlowTimeoutId = window.setTimeout(() => {
                this.cartGlow = false;
                this._cartGlowTimeoutId = null;
            }, 750);
        },

        resolveFxOrigin(clickEvent) {
            const target = clickEvent?.currentTarget;
            if (target instanceof Element) {
                const rect = target.getBoundingClientRect();
                return {
                    x: rect.left + rect.width / 2,
                    y: rect.top + rect.height / 2,
                };
            }

            return {
                x: window.innerWidth / 2,
                y: window.innerHeight * 0.8,
            };
        },

        spawnFlyEffect(origin = null) {
            const startX = origin?.x ?? window.innerWidth / 2;
            const startY = origin?.y ?? window.innerHeight * 0.8;

            requestAnimationFrame(() => {
                const cart = document.getElementById('guest-cart-cta')
                    ?? document.getElementById('guest-cart-fx-target');
                if (!cart) return;

                const cartRect = cart.getBoundingClientRect();
                if (cartRect.width < 2 || cartRect.height < 2) return;

                const targetX = cartRect.left + Math.min(36, cartRect.width * 0.12);
                const targetY = cartRect.top + cartRect.height / 2;

                const particles = 3;
                for (let i = 0; i < particles; i += 1) {
                    const fxId = crypto.randomUUID();
                    const spread = (i - 1) * 14;
                    const duration = 840 + i * 80;
                    const fx = {
                        id: fxId,
                        startX: startX + spread,
                        startY: startY - Math.abs(spread) * 0.35,
                        dx: targetX - (startX + spread),
                        dy: targetY - (startY - Math.abs(spread) * 0.35),
                        rotate: Math.floor(Math.random() * 120) - 60,
                        size: 10 + i * 2,
                        glow: 10 + i * 3,
                        duration,
                        active: false,
                    };

                    this.flyEffects = [...this.flyEffects, fx];

                    requestAnimationFrame(() => {
                        this.flyEffects = this.flyEffects.map(item =>
                            item.id === fxId ? { ...item, active: true } : item
                        );
                    });

                    window.setTimeout(() => {
                        this.flyEffects = this.flyEffects.filter(item => item.id !== fxId);
                    }, duration + 120);
                }
            });
        },

        _recalcLineTotal(line) {
            if (!isCartLine(line)) return;
            line.lineTotalMinor = Number(line.unitLineTotalMinor) * Number(line.qty);
        },

        incrementLineQty(lineId) {
            const line = this.lines.find(l => l.lineId === lineId);
            if (!line) return;
            line.qty += 1;
            this._recalcLineTotal(line);
            this.lines = [...this.lines];
            this.clearSubmitIdempotencyKey();
            this.pulseTotalRow();
        },

        decrementLineQty(lineId) {
            const line = this.lines.find(l => l.lineId === lineId);
            if (!line) return;
            line.qty -= 1;
            if (line.qty <= 0) {
                this.removeLine(lineId, { silent: true });
                return;
            }
            this._recalcLineTotal(line);
            this.lines = [...this.lines];
            this.clearSubmitIdempotencyKey();
            this.pulseTotalRow();
        },

        /**
         * @param {string} lineId
         * @param {{ silent?: boolean }} [opts]
         */
        removeLine(lineId, opts = {}) {
            const line = this.lines.find(l => l.lineId === lineId);
            if (!line) return;
            this.lines = this.lines.filter(l => l.lineId !== lineId);
            if (!opts.silent) {
                this.showRemovedToast(line);
            }
            if (this.lines.length === 0) {
                this._clearCartStep = 0;
                this.dismissToast();
            }
            this.clearSubmitIdempotencyKey();
            this.pulseTotalRow();
        },

        /**
         * One-line summary for compact cart rows (style + toppings).
         *
         * @param {object} line
         * @returns {string}
         */
        lineModifiersSummary(line) {
            if (!line || typeof line !== 'object') return '';
            const parts = [];
            if (line.styleNameSnapshot) {
                parts.push(String(line.styleNameSnapshot));
            }
            if (Array.isArray(line.toppingSnapshots)) {
                for (const t of line.toppingSnapshots) {
                    if (t?.name) parts.push(String(t.name));
                }
            }
            return parts.join(' · ');
        },

        clearCartTap() {
            if (this.lines.length === 0) return;

            if (this._clearCartResetTimerId !== null) {
                clearTimeout(this._clearCartResetTimerId);
                this._clearCartResetTimerId = null;
            }

            if (this._clearCartStep === 0) {
                this._clearCartStep = 1;
                this._clearCartResetTimerId = window.setTimeout(() => {
                    this._clearCartStep = 0;
                    this._clearCartResetTimerId = null;
                }, 2200);
                return;
            }

            this.lines = [];
            this._clearCartStep = 0;
            this.dismissToast();
            this.clearSubmitIdempotencyKey();
            this.closeCartPanel({ restoreScroll: true });
        },

        showRemovedToast(line) {
            if (this._toastTimerId !== null) {
                clearTimeout(this._toastTimerId);
                this._toastTimerId = null;
            }
            let snapshot;
            try {
                snapshot = structuredClone(line);
            } catch {
                snapshot = JSON.parse(JSON.stringify(line));
            }
            this.toast = { type: 'removed', line: snapshot };
            this._toastTimerId = window.setTimeout(() => {
                this.dismissToast();
            }, 5200);
        },

        dismissToast() {
            if (this._toastTimerId !== null) {
                clearTimeout(this._toastTimerId);
                this._toastTimerId = null;
            }
            this.toast = null;
        },

        undoRemoveLine() {
            if (!this.toast || this.toast.type !== 'removed' || !isCartLine(this.toast.line)) {
                this.dismissToast();
                return;
            }
            const line = this.toast.line;
            const existing = this.lines.find(l => l.mergeKey === line.mergeKey);
            if (existing) {
                existing.qty += line.qty;
                this._recalcLineTotal(existing);
                this.lines = [...this.lines];
            } else {
                this.lines = [...this.lines, line];
            }
            this.dismissToast();
            this.clearSubmitIdempotencyKey();
            this.pulseTotalRow();
        },

        setLineNote(lineId, note) {
            const line = this.lines.find(l => l.lineId === lineId);
            if (!line) return;
            line.note = String(note).slice(0, 280);
            this.lines = [...this.lines];
            this.clearSubmitIdempotencyKey();
        },

        /**
         * Serializable draft for Ver2 HTTP submission to **table POS** (never straight to KDS).
         * Server must enforce: persist as `placed`, staff confirmation -> `confirmed`, then KDS RT.
         *
         * @returns {object}
         */
        buildTransmissionDraft() {
            const currency = String(this.catalog?.meta?.currency ?? 'TND');
            const priceDivisor = Number(this.catalog?.meta?.price_divisor) > 0
                ? Number(this.catalog.meta.price_divisor)
                : 1000;

            const lines = this.lines.map(l => ({
                lineId: l.lineId,
                mergeKey: l.mergeKey,
                itemId: l.itemId,
                titleSnapshot: l.titleSnapshot,
                kitchenNameSnapshot: String(l.kitchenNameSnapshot ?? l.titleSnapshot ?? ''),
                styleId: l.styleId,
                styleNameSnapshot: l.styleNameSnapshot,
                stylePriceMinor: Number(l.stylePriceMinor),
                toppingSnapshots: Array.isArray(l.toppingSnapshots)
                    ? l.toppingSnapshots.map(t => ({
                        id: t.id,
                        name: t.name,
                        priceDeltaMinor: Number(t.priceDeltaMinor),
                    }))
                    : [],
                unitLineTotalMinor: Number(l.unitLineTotalMinor),
                qty: Number(l.qty),
                lineTotalMinor: Number(l.lineTotalMinor),
                note: String(l.note ?? ''),
            }));

            return {
                schemaVersion: 1,
                intent: 'submit_to_table_pos',
                clientSessionId: this.clientSessionId,
                context: {
                    tenantSlug: this.contextTenantSlug,
                    tableToken: this.contextTableToken,
                    locale: document.documentElement.lang || '',
                },
                catalogFingerprint: {
                    currency,
                    priceDivisor,
                },
                lines,
                totals: {
                    currency,
                    priceDivisor,
                    subtotalMinor: this.cartTotalMinor(),
                },
                generatedAt: new Date().toISOString(),
            };
        },

        /** Submit cart to Livewire (Zero Trust pricing on server). */
        async submitOrderDraft() {
            const draft = this.buildTransmissionDraft();
            if (draft.lines.length === 0) return;

            if (!this._submitIdempotencyKey) {
                this._submitIdempotencyKey = crypto.randomUUID();
            }
            draft.idempotencyKey = this._submitIdempotencyKey;

            if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
                navigator.vibrate(14);
            }

            const component = findGuestMenuLivewireComponent();
            if (!component) {
                showGuestOrderFlash('Service unavailable', true);
                return;
            }

            try {
                await component.call('submitOrder', draft);
            } catch (err) {
                console.error(err);
                showGuestOrderFlash('Network error', true);
            }

            if (import.meta.env?.DEV) {
                window.dispatchEvent(new CustomEvent('guest-order:draft-ready', {
                    detail: draft,
                    bubbles: true,
                }));
                // eslint-disable-next-line no-console
                console.info('[guest-order] transmission draft', draft);
            }
        },

        // ─── Scroll-spy ───────────────────────────────────────────────────────────

        initScrollSpyWithEndSnap(categoryIds) {
            if (!categoryIds.length) return;

            const SNAP_THRESHOLD = 24;
            const store          = this;

            store.activeCategoryId = categoryIds[0];

            const observer = new IntersectionObserver(
                (entries) => {
                    if (store._nearBottomLocked) return;

                    const candidates = entries
                        .filter(e => e.isIntersecting && e.boundingClientRect.top >= 0)
                        .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);

                    if (candidates.length > 0) {
                        const rawId = candidates[0].target.id.replace(/^cat-/, '');
                        store.activeCategoryId = rawId;
                    }
                },
                {
                    rootMargin: '-56px 0px -80% 0px',
                    threshold:  0,
                }
            );

            categoryIds.forEach(id => {
                const el = document.getElementById('cat-' + id);
                if (el) observer.observe(el);
            });

            const onScroll = () => {
                const scrolled = document.documentElement.scrollTop + window.innerHeight;
                const total    = document.documentElement.scrollHeight;

                if (scrolled >= total - SNAP_THRESHOLD) {
                    store._nearBottomLocked = true;
                    store.activeCategoryId  = categoryIds[categoryIds.length - 1];
                } else {
                    store._nearBottomLocked = false;
                }
            };

            window.addEventListener('scroll', onScroll, { passive: true });
        },

        scrollToCategory(id) {
            const el = document.getElementById('cat-' + id);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        },

        /** @param {string} itemId @returns {string|null} */
        resolveItemImage(itemId) {
            for (const cat of (this.catalog.categories ?? [])) {
                const it = (cat.items ?? []).find(i => String(i.id) === String(itemId));
                if (it?.image) {
                    return String(it.image);
                }
            }
            return null;
        },

        async copyTransmissionDraftToClipboard() {
            const text = JSON.stringify(this.buildTransmissionDraft(), null, 2);
            try {
                await navigator.clipboard.writeText(text);
                this.clipboardBanner = true;
                window.setTimeout(() => { this.clipboardBanner = false; }, 2000);
            } catch {
                // ignore — insecure context or denied
            }
        },
    });
});

// bfcache: re-read #guest-order-data and prune lines that are not in this page's menu
window.addEventListener('pageshow', (ev) => {
    if (!ev.persisted) {
        return;
    }
    if (typeof window.Alpine === 'undefined' || typeof window.Alpine.store !== 'function') {
        return;
    }
    try {
        window.Alpine.store('cart').bootstrapFromDom();
    } catch {
        // ignore
    }
});
