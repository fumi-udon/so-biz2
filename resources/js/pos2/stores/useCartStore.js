/**
 * POS2 カートストア（ドラフト契約）
 *
 * ドラフトキー: pos_draft_{shopId}_{tableSessionId}
 * ─ restaurant_table_id 単独キー禁止（前客データ復活リスク）
 *
 * 各ドラフト行の必須フィールド:
 *   menu_item_id      : number
 *   qty               : number (>= 1)
 *   styleSnapshot     : { id, name, price_minor } | null
 *   toppingSnapshots  : [{ id, name, price_delta_minor }]
 *   note              : string
 *
 * Phase1 では UI は最小限。構造の定義と検証ロジックが主目的。
 */

import { defineStore } from 'pinia';

export const DRAFT_SCHEMA_VERSION = 1;

function draftKey(shopId, tableSessionId) {
    return `pos_draft_${shopId}_${tableSessionId}`;
}

function emptyDraftLine(menuItemId) {
    return {
        menu_item_id: Number(menuItemId),
        qty: 1,
        styleSnapshot: null,
        toppingSnapshots: [],
        note: '',
    };
}

/**
 * @param {object} line - ドラフト行
 * @param {boolean} styleRequired - options_payload.rules.style_required
 * @returns {{ valid: boolean, reason: string | null }}
 */
export function validateDraftLine(line, styleRequired = false) {
    if (!line || typeof line !== 'object') {
        return { valid: false, reason: 'line is not an object' };
    }
    if (!Number.isInteger(line.menu_item_id) || line.menu_item_id < 1) {
        return { valid: false, reason: 'menu_item_id is invalid' };
    }
    if (!Number.isInteger(line.qty) || line.qty < 1) {
        return { valid: false, reason: 'qty must be >= 1' };
    }
    if (styleRequired && line.styleSnapshot === null) {
        return { valid: false, reason: 'style is required but not selected' };
    }
    if (line.styleSnapshot !== null) {
        const s = line.styleSnapshot;
        if (typeof s !== 'object' || !s.id || typeof s.name !== 'string' || typeof s.price_minor !== 'number') {
            return { valid: false, reason: 'styleSnapshot has invalid shape' };
        }
    }
    if (!Array.isArray(line.toppingSnapshots)) {
        return { valid: false, reason: 'toppingSnapshots must be an array' };
    }
    for (const t of line.toppingSnapshots) {
        if (!t.id || typeof t.name !== 'string' || typeof t.price_delta_minor !== 'number') {
            return { valid: false, reason: `topping snapshot invalid: ${JSON.stringify(t)}` };
        }
    }
    return { valid: true, reason: null };
}

export const useCartStore = defineStore('pos2Cart', {
    state: () => ({
        shopId: 0,
        tableSessionId: null,
        lines: [],
        schemaVersion: DRAFT_SCHEMA_VERSION,
    }),

    getters: {
        draftKey: (state) =>
            state.tableSessionId
                ? draftKey(state.shopId, state.tableSessionId)
                : null,

        totalItemsCount: (state) =>
            state.lines.reduce((sum, l) => sum + (l.qty ?? 0), 0),

        totalMinor: (state) =>
            state.lines.reduce((sum, l) => {
                const stylePrice = l.styleSnapshot?.price_minor ?? 0;
                const toppingDelta = (l.toppingSnapshots ?? []).reduce(
                    (s, t) => s + (t.price_delta_minor ?? 0), 0,
                );
                return sum + (stylePrice + toppingDelta) * (l.qty ?? 0);
            }, 0),
    },

    actions: {
        /**
         * LocalStorage からドラフトを復元。
         * schema_version 不一致なら破棄して空に初期化する。
         */
        loadFromStorage(shopId, tableSessionId) {
            this.shopId = Number(shopId);
            this.tableSessionId = String(tableSessionId);
            const key = draftKey(shopId, tableSessionId);
            const raw = localStorage.getItem(key);
            if (!raw) {
                this.lines = [];
                return false;
            }
            try {
                const data = JSON.parse(raw);
                if ((data.schema_version ?? 0) !== DRAFT_SCHEMA_VERSION) {
                    localStorage.removeItem(key);
                    this.lines = [];
                    return false;
                }
                this.lines = Array.isArray(data.lines) ? data.lines : [];
                this.schemaVersion = DRAFT_SCHEMA_VERSION;
                return true;
            } catch {
                localStorage.removeItem(key);
                this.lines = [];
                return false;
            }
        },

        /**
         * 現在のドラフトを LocalStorage に保存。
         */
        persist() {
            const key = this.draftKey;
            if (!key) return;
            localStorage.setItem(key, JSON.stringify({
                schema_version: DRAFT_SCHEMA_VERSION,
                shop_id: this.shopId,
                table_session_id: this.tableSessionId,
                lines: this.lines,
                saved_at: new Date().toISOString(),
            }));
        },

        /**
         * 1 行追加（style 必須チェック込み）。
         * @returns {{ ok: boolean, reason: string | null }}
         */
        addLine(menuItemId, options = {}) {
            const line = {
                ...emptyDraftLine(menuItemId),
                styleSnapshot: options.styleSnapshot ?? null,
                toppingSnapshots: Array.isArray(options.toppingSnapshots) ? options.toppingSnapshots : [],
                note: String(options.note ?? ''),
            };

            const styleRequired = options.styleRequired === true;
            const { valid, reason } = validateDraftLine(line, styleRequired);
            if (!valid) {
                return { ok: false, reason };
            }

            this.lines.push(line);
            this.persist();
            return { ok: true, reason: null };
        },

        /**
         * 指定インデックスの行を削除。
         */
        removeLine(index) {
            this.lines.splice(index, 1);
            this.persist();
        },

        /**
         * テーブルセッションを切り替え（前セッションのドラフトを保持したまま別キーに移動）。
         */
        switchSession(shopId, tableSessionId) {
            this.shopId = Number(shopId);
            this.tableSessionId = String(tableSessionId);
            this.lines = [];
            this.loadFromStorage(shopId, tableSessionId);
        },

        /**
         * ドラフトを全クリア（送信成功後に呼ぶ）。
         */
        clearDraft() {
            const key = this.draftKey;
            if (key) localStorage.removeItem(key);
            this.lines = [];
        },
    },
});
