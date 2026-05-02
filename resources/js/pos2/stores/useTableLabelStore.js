/**
 * テイクアウト卓の客名表示（sessionStorage + Phase B: DB 同期）。
 * キー: `${shopId}_${tableSessionId}` → { name, tel }
 */

import axios from 'axios';
import { defineStore } from 'pinia';
import { buildPos2JsonHeaders } from '../utils/pos2Http';

const STORAGE_KEY = 'pos2_table_labels_v1';

function compositeKey(shopId, sessionId) {
    const sid = Number(sessionId);
    if (!Number.isFinite(sid) || sid < 1) {
        return null;
    }
    const s = Number(shopId);
    if (!Number.isFinite(s) || s < 1) {
        return null;
    }
    return `${s}_${sid}`;
}

function readStorage() {
    if (typeof sessionStorage === 'undefined') {
        return {};
    }
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        if (!raw) {
            return {};
        }
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
    } catch {
        return {};
    }
}

function writeStorage(map) {
    if (typeof sessionStorage === 'undefined') {
        return;
    }
    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(map));
    } catch {
        // 容量超過などは握りつぶし（本ロジックを止めない）
    }
}

export const useTableLabelStore = defineStore('pos2TableLabels', {
    state: () => ({
        /** 現在の店舗 ID（composite キー用） */
        shopId: 0,
        /**
         * @type {Record<string, { name: string, tel: string }>}
         */
        tableLabels: {},
    }),

    actions: {
        /**
         * ページ起動時: sessionStorage から復元。
         */
        hydrateFromSessionStorage() {
            this.tableLabels = readStorage();
        },

        /**
         * @param {number} shopId
         */
        setShopId(shopId) {
            this.shopId = Number(shopId);
        },

        _persist() {
            writeStorage(this.tableLabels);
        },

        /**
         * @param {string|number} sessionId
         * @param {string} name
         * @param {string} [tel]
         */
        setLabel(sessionId, name, tel = '') {
            const key = compositeKey(this.shopId, sessionId);
            if (key == null) {
                return;
            }
            const n = String(name ?? '').trim();
            const t = String(tel ?? '').trim();
            this.tableLabels = {
                ...this.tableLabels,
                [key]: { name: n, tel: t },
            };
            this._persist();
            this._syncCustomerToServer(sessionId, n, t);
        },

        /**
         * GET .../sessions/:id/orders の customer_* を Pinia + sessionStorage にミラー（API は呼ばない）。
         * @param {string|number} sessionId
         * @param {string|null|undefined} name
         * @param {string|null|undefined} tel
         */
        setLabelFromServer(sessionId, name, tel) {
            const key = compositeKey(this.shopId, sessionId);
            if (key == null) {
                return;
            }
            const n = name != null ? String(name).trim() : '';
            const t = tel != null ? String(tel).trim() : '';
            if (n === '' && t === '') {
                if (Object.prototype.hasOwnProperty.call(this.tableLabels, key)) {
                    const next = { ...this.tableLabels };
                    delete next[key];
                    this.tableLabels = next;
                    this._persist();
                }
                return;
            }
            this.tableLabels = {
                ...this.tableLabels,
                [key]: { name: n, tel: t },
            };
            this._persist();
        },

        _syncCustomerToServer(sessionId, name, tel) {
            const sid = Number(sessionId);
            if (!Number.isFinite(sid) || sid < 1) {
                return;
            }
            void axios
                .post(
                    `/pos2/api/sessions/${sid}/customer`,
                    { name, tel },
                    { headers: buildPos2JsonHeaders(), withCredentials: true },
                )
                .catch((err) => console.warn('customer save failed', err));
        },

        /**
         * @param {string|number} sessionId
         * @returns {{ name: string, tel: string } | null}
         */
        getLabel(sessionId) {
            const key = compositeKey(this.shopId, sessionId);
            if (key == null) {
                return null;
            }
            const row = this.tableLabels[key];
            if (!row || typeof row !== 'object') {
                return null;
            }
            return {
                name: String(row.name ?? '').trim(),
                tel: String(row.tel ?? '').trim(),
            };
        },

        /**
         * @param {string|number} sessionId
         */
        clearLabel(sessionId) {
            const key = compositeKey(this.shopId, sessionId);
            if (key == null || !Object.prototype.hasOwnProperty.call(this.tableLabels, key)) {
                return;
            }
            const next = { ...this.tableLabels };
            delete next[key];
            this.tableLabels = next;
            this._persist();
        },
    },
});
