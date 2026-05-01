import { defineStore } from 'pinia';

const SCHEMA_VERSION = 1;

function storageKey(shopId) {
    return `pos2_master_${shopId}_v${SCHEMA_VERSION}`;
}

/** API / localStorage 由来の値を正の整数に正規化（無効時は 100） */
function normalizeTableDisplayLimit(value) {
    const n = Number(value);
    if (Number.isFinite(n) && n > 0 && Number.isInteger(n)) {
        return n;
    }
    return 100;
}

function emptyState() {
    return {
        shopId: 0,
        schemaVersion: 0,
        categories: [],
        menuItems: [],
        tables: [],
        clientTableLimit: 100,
        staffTableLimit: 100,
        takeoutTableLimit: 100,
        generatedAt: null,
        loadedAt: null,
    };
}

export const useMasterStore = defineStore('pos2Master', {
    state: () => emptyState(),

    getters: {
        isLoaded: (state) => state.loadedAt !== null,

        itemById: (state) => (id) => state.menuItems.find((m) => m.id === id) ?? null,

        optionsPayload: () => (item) => {
            const payload = item?.options_payload;
            if (!payload || typeof payload !== 'object') {
                return { rules: { style_required: false }, styles: [], toppings: [] };
            }

            return {
                rules: {
                    style_required: payload.rules?.style_required === true,
                },
                styles: Array.isArray(payload.styles) ? payload.styles : [],
                toppings: Array.isArray(payload.toppings) ? payload.toppings : [],
            };
        },

        // options を持つ商品数（debug 表示用）
        itemsWithOptionsCount: (state) => state.menuItems.filter((m) => {
            const p = m?.options_payload;
            if (!p) return false;
            return (Array.isArray(p.styles) && p.styles.length > 0)
                || (Array.isArray(p.toppings) && p.toppings.length > 0);
        }).length,
    },

    actions: {
        /**
         * LocalStorage から即時復元。
         * schema_version 不一致 or データなし の場合は false を返す（再同期必要）。
         */
        loadFromStorage(shopId) {
            const raw = localStorage.getItem(storageKey(shopId));
            if (!raw) return false;

            try {
                const data = JSON.parse(raw);
                if ((data.schema_version ?? 0) !== SCHEMA_VERSION) {
                    localStorage.removeItem(storageKey(shopId));
                    return false;
                }
                this.shopId = Number(shopId);
                this.schemaVersion = SCHEMA_VERSION;
                this.categories = Array.isArray(data.categories) ? data.categories : [];
                this.menuItems = Array.isArray(data.menuItems) ? data.menuItems : [];
                this.tables = Array.isArray(data.tables) ? data.tables : [];
                this.clientTableLimit = normalizeTableDisplayLimit(
                    data.client_table_limit ?? data.clientTableLimit,
                );
                this.staffTableLimit = normalizeTableDisplayLimit(
                    data.staff_table_limit ?? data.staffTableLimit,
                );
                this.takeoutTableLimit = normalizeTableDisplayLimit(
                    data.takeout_table_limit ?? data.takeoutTableLimit,
                );
                this.generatedAt = data.generated_at ?? null;
                this.loadedAt = data.savedAt ?? new Date().toISOString();
                return true;
            } catch {
                localStorage.removeItem(storageKey(shopId));
                return false;
            }
        },

        /**
         * API レスポンスで Pinia を更新し LocalStorage に保存。
         */
        applyPayload(payload, shopId) {
            this.shopId = Number(shopId);
            this.schemaVersion = Number(payload.schema_version ?? SCHEMA_VERSION);
            this.categories = Array.isArray(payload.categories) ? payload.categories : [];
            this.menuItems = Array.isArray(payload.menuItems) ? payload.menuItems : [];
            this.tables = Array.isArray(payload.tables) ? payload.tables : [];
            this.clientTableLimit = normalizeTableDisplayLimit(payload.client_table_limit);
            this.staffTableLimit = normalizeTableDisplayLimit(payload.staff_table_limit);
            this.takeoutTableLimit = normalizeTableDisplayLimit(payload.takeout_table_limit);
            this.generatedAt = payload.generated_at ?? null;
            this.loadedAt = new Date().toISOString();

            localStorage.setItem(storageKey(shopId), JSON.stringify({
                schema_version: this.schemaVersion,
                categories: this.categories,
                menuItems: this.menuItems,
                tables: this.tables,
                client_table_limit: this.clientTableLimit,
                staff_table_limit: this.staffTableLimit,
                takeout_table_limit: this.takeoutTableLimit,
                generated_at: this.generatedAt,
                savedAt: this.loadedAt,
            }));
        },

        clearStorage(shopId) {
            localStorage.removeItem(storageKey(shopId));
            Object.assign(this, emptyState());
        },
    },
});
