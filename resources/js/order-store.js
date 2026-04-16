/**
 * Alpine.store('cart') — Guest Order UI
 *
 * Responsibilities:
 *   - Catalog data (read-only, injected once via #guest-order-data JSON)
 *   - Translations (i18n dictionary, same payload)
 *   - Cart state (lines[], qty, totals) — Alpine SSOT, zero server round-trips
 *   - Modal (Bottom-sheet open/close, selected style/toppings)
 *   - Scroll-spy (IntersectionObserver + near-bottom fallback)
 *
 * Hydration Standard:
 *   - Do NOT import alpinejs from npm — Livewire bundles Alpine on window.Alpine.
 *   - Register this store inside document.addEventListener('alpine:init', ...).
 *
 * Rules enforced here:
 *   - All monetary values are integers (millimes). Division only at display time.
 *   - buildCartLineFromModal() produces a pure literal with zero object references.
 *   - Cart-line deduplication via mergeKey (item|style|toppings).
 */

/**
 * Read and parse the JSON payload embedded in #guest-order-data.
 *
 * @returns {{ catalog: object, translations: Record<string, string>, categoryIds: string[] }}
 */
function readGuestOrderPayload() {
    const el = document.getElementById('guest-order-data');
    if (!el || el.textContent === null || el.textContent.trim() === '') {
        return {
            catalog:       { meta: {}, categories: [] },
            translations: {},
            categoryIds:   [],
        };
    }
    try {
        return JSON.parse(el.textContent);
    } catch {
        return {
            catalog:       { meta: {}, categories: [] },
            translations: {},
            categoryIds:   [],
        };
    }
}

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    Alpine.store('cart', {

        // ─── Data from #guest-order-data (bootstrapFromDom) ───────────────────
        catalog: { meta: {}, categories: [] },
        translations: {},
        categoryIds: [],

        // ─── Scroll-spy ───────────────────────────────────────────────────────────
        activeCategoryId: null,
        _nearBottomLocked: false,

        // ─── Cart ─────────────────────────────────────────────────────────────────
        lines: [],

        // ─── Bottom-sheet modal ───────────────────────────────────────────────────
        sheetOpen: false,
        editingItem: null,       // reference to catalog item (read-only in modal)
        selectedStyleId: null,
        selectedToppingIds: [],
        _scrollPos: 0,           // saved body scroll position for iOS lock restore

        // ─── Hydration bootstrap ─────────────────────────────────────────────────

        /**
         * Called from menu-page.blade.php via x-init="$store.cart.bootstrapFromDom()".
         * Reads #guest-order-data (never from HTML attribute quotes).
         */
        bootstrapFromDom() {
            const payload = readGuestOrderPayload();
            this.catalog       = payload.catalog ?? { meta: {}, categories: [] };
            this.translations   = payload.translations ?? {};
            this.categoryIds    = Array.isArray(payload.categoryIds) ? payload.categoryIds : [];

            queueMicrotask(() => {
                this.initScrollSpyWithEndSnap(this.categoryIds);
            });
        },

        // ─── i18n ─────────────────────────────────────────────────────────────────

        /**
         * Look up a translation key and replace :param placeholders.
         *
         * @param {string} key
         * @param {Record<string, string>} [params]
         * @returns {string}
         */
        t(key, params = {}) {
            let text = Object.prototype.hasOwnProperty.call(this.translations, key)
                ? String(this.translations[key])
                : key;
            for (const [k, v] of Object.entries(params)) {
                text = text.replaceAll(':' + k, String(v));
            }
            return text;
        },

        // ─── Cart getters (methods, Alpine store has no true getters) ─────────────

        /** Total number of items across all lines. */
        lineCount() {
            return this.lines.reduce((sum, l) => sum + l.qty, 0);
        },

        /** Sum of all line totals in millimes. */
        cartTotalMinor() {
            return this.lines.reduce((sum, l) => sum + l.lineTotalMinor, 0);
        },

        /**
         * Running subtotal for the item currently open in the sheet (millimes).
         * Returns 0 when no item is editing.
         */
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

        /**
         * Whether the current sheet selection is valid enough to add to cart.
         */
        canAddToCart() {
            if (!this.editingItem) return false;
            if (this.editingItem.rules?.style_required && !this.selectedStyleId) return false;
            return true;
        },

        // ─── Bottom-sheet ─────────────────────────────────────────────────────────

        /**
         * Open the customisation sheet for a given item id.
         * Saves scroll position and locks body scroll (iOS overscroll fix).
         *
         * @param {string} itemId
         */
        openSheet(itemId) {
            let found = null;
            for (const cat of (this.catalog.categories ?? [])) {
                found = (cat.items ?? []).find(i => i.id === itemId) ?? null;
                if (found) break;
            }
            if (!found) return;

            this.editingItem = found;      // reference — modal must not mutate this
            this.selectedStyleId = null;
            this.selectedToppingIds = [];
            this._scrollPos = window.scrollY;
            document.documentElement.classList.add('overflow-hidden');
            this.sheetOpen = true;
        },

        /** Close the sheet and restore body scroll position. */
        closeSheet() {
            this.sheetOpen = false;
            this.editingItem = null;
            this.selectedStyleId = null;
            this.selectedToppingIds = [];
            document.documentElement.classList.remove('overflow-hidden');
            window.scrollTo(0, this._scrollPos);
        },

        /**
         * Toggle a topping selection.
         *
         * @param {string} id
         */
        toggleTopping(id) {
            if (this.selectedToppingIds.includes(id)) {
                this.selectedToppingIds = this.selectedToppingIds.filter(t => t !== id);
            } else {
                this.selectedToppingIds = [...this.selectedToppingIds, id];
            }
        },

        // ─── Cart mutation ────────────────────────────────────────────────────────

        /**
         * Pure function: builds a cart-line literal from the current modal state.
         * Zero references to catalog, editingItem, or any external object.
         *
         * @returns {CartLine}
         */
        buildCartLineFromModal() {
            const item   = this.editingItem;
            const style  = Array.isArray(item.styles)
                ? item.styles.find(s => s.id === this.selectedStyleId) ?? null
                : null;

            // Deep-copy toppings as plain literals
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
                styleId:             this.selectedStyleId ? String(this.selectedStyleId) : null,
                styleNameSnapshot:   style ? String(style.name) : null,
                stylePriceMinor,
                toppingSnapshots,    // plain object array — no external references
                unitLineTotalMinor,
                lineTotalMinor:      unitLineTotalMinor,
                qty:                 1,
            };
        },

        /**
         * Add the current sheet selection to the cart.
         * Merges identical lines (same mergeKey) by incrementing qty.
         */
        addToCart() {
            if (!this.canAddToCart()) return;

            const newLine = this.buildCartLineFromModal();

            const mergingEnabled =
                this.catalog.meta?.merge_identical_lines !== false &&
                this.editingItem?.rules?.merge_identical_lines !== false;

            if (mergingEnabled) {
                const existing = this.lines.find(l => l.mergeKey === newLine.mergeKey);
                if (existing) {
                    existing.qty += 1;
                    existing.lineTotalMinor = existing.unitLineTotalMinor * existing.qty;
                    this.closeSheet();
                    return;
                }
            }

            // Spread to trigger Alpine reactivity
            this.lines = [...this.lines, newLine];
            this.closeSheet();
        },

        // ─── Scroll-spy ───────────────────────────────────────────────────────────

        /**
         * Initialise scroll-spy using IntersectionObserver (mid-list detection)
         * plus a scroll-event near-bottom fallback (last category fix).
         *
         * @param {string[]} categoryIds  Ordered list matching DOM id="cat-{id}"
         */
        initScrollSpyWithEndSnap(categoryIds) {
            if (!categoryIds.length) return;

            const SNAP_THRESHOLD = 24; // px from page bottom to trigger end-snap
            const store          = this;

            // Set initial active category
            store.activeCategoryId = categoryIds[0];

            // ── IntersectionObserver (handles all categories except possibly last) ─
            const observer = new IntersectionObserver(
                (entries) => {
                    if (store._nearBottomLocked) return;

                    // Find the topmost intersecting section that is at or below viewport top
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

            // ── Near-bottom fallback ──────────────────────────────────────────────
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

        /**
         * Smoothly scroll the viewport to the given category section.
         *
         * @param {string} id  Category id (without 'cat-' prefix)
         */
        scrollToCategory(id) {
            const el = document.getElementById('cat-' + id);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        },
    });
});
