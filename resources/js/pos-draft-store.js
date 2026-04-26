/**
 * posDraft Alpine Store
 *
 * 役割：テーブル切替時のコンテキスト管理のみ
 * - switchContext: テーブル切替時にセッションIDを同期（0ms）
 * - clearSession: 送信完了後のsessionStorageクリア
 * - clearAllForShop: Cloture時の一括クリア
 * - BroadcastChannel: マルチタブ同期
 *
 * 担わないこと：
 * - 商品の追加・削除・表示（posOrders / Livewireの責務）
 * - addItem / removeItem / _persist は意図的に存在しない
 */

/**
 * Alpine.store('posDraft') — POS 卓ドロワーのコンテキスト同期（テーブル切替・マルチタブクリア）
 *
 * - Livewire が $this->js() で switchContext / onHostClosed を同期する（x-init だけに頼らない）。
 * - sessionStorage の tmp→formal 移行はテーブル初回バインド時の孤立キー整理用（カート SSOT ではない）。
 *
 * @see .cursorrules High Latency (L52–57), TALL/Alpine (L59–66)
 */

/**
 * @param {unknown} raw
 * @returns {unknown[]}
 */
function safeParseDraftArray(raw) {
    if (raw == null || raw === '') {
        return [];
    }
    try {
        const v = JSON.parse(String(raw));
        return Array.isArray(v) ? v : [];
    } catch {
        return [];
    }
}

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;
    if (!Alpine || typeof Alpine.store !== 'function') {
        return;
    }

    if (!window.__posDraftBroadcastBound) {
        window.__posDraftBroadcastBound = true;
        try {
            const bc = new BroadcastChannel('pos_draft');
            bc.onmessage = (e) => {
                try {
                    if (e?.data?.type !== 'clear' || !e.data.shopId || !e.data.sessionId) {
                        return;
                    }
                    const s = window.Alpine?.store?.('posDraft');
                    if (s && typeof s.clearSession === 'function') {
                        s.clearSession(Number(e.data.shopId), Number(e.data.sessionId), false);
                    }
                } catch {
                    /* ignore */
                }
            };
        } catch {
            /* BroadcastChannel unsupported */
        }
    }

    Alpine.store('posDraft', {
        shopId: 0,
        sessionId: null,
        tableId: null,

        /**
         * @param {number} shopId
         * @param {number|null|undefined} sessionId
         * @param {number|null|undefined} tableId
         */
        switchContext(shopId, sessionId, tableId) {
            const nextShop = Number(shopId) > 0 ? Number(shopId) : 0;
            const nextSession =
                sessionId != null && sessionId !== '' && Number(sessionId) > 0 ? Number(sessionId) : null;
            const nextTable =
                tableId != null && tableId !== '' && Number(tableId) > 0 ? Number(tableId) : null;

            const prevSession = this.sessionId;
            const hadSession = prevSession != null && Number(prevSession) > 0;

            // tmp → formal: session became available (first bind after tmp drafts)
            if (nextShop && nextSession && nextTable && !hadSession) {
                const tmpKey = `pos_draft_${nextShop}_table_${nextTable}_tmp`;
                const formalKey = `pos_draft_${nextShop}_${nextSession}`;
                const rawTmp = sessionStorage.getItem(tmpKey);
                if (rawTmp) {
                    const tmpItems = safeParseDraftArray(rawTmp);
                    const formalItems = safeParseDraftArray(sessionStorage.getItem(formalKey));
                    sessionStorage.setItem(formalKey, JSON.stringify([...formalItems, ...tmpItems]));
                    sessionStorage.removeItem(tmpKey);
                }
            }

            this.shopId = nextShop;
            this.sessionId = nextSession;
            this.tableId = nextTable;
        },

        /**
         * @param {number} shopId
         * @param {number} sessionId
         * @param {boolean} [broadcast]
         */
        clearSession(shopId, sessionId, broadcast = true) {
            if (!shopId || !sessionId) {
                return;
            }
            const key = `pos_draft_${shopId}_${sessionId}`;
            sessionStorage.removeItem(key);
            if (broadcast) {
                try {
                    const bc = new BroadcastChannel('pos_draft');
                    bc.postMessage({ type: 'clear', shopId, sessionId });
                } catch {
                    /* ignore */
                }
            }
        },

        /**
         * @param {number} shopId
         */
        clearAllForShop(shopId) {
            if (!shopId || shopId < 1) {
                return;
            }
            const prefix = `pos_draft_${shopId}_`;
            try {
                Object.keys(sessionStorage)
                    .filter((k) => k.startsWith(prefix))
                    .forEach((k) => sessionStorage.removeItem(k));
            } catch {
                /* ignore */
            }
        },

        /**
         * Host drawer closed: remove persisted keys for this context (safety).
         *
         * @param {number} shopId
         * @param {number|null|undefined} tableId
         * @param {number|null|undefined} sessionId
         */
        onHostClosed(shopId, tableId, sessionId) {
            const sid = sessionId != null && Number(sessionId) > 0 ? Number(sessionId) : null;
            const tid = tableId != null && Number(tableId) > 0 ? Number(tableId) : null;
            const sh = Number(shopId) > 0 ? Number(shopId) : 0;
            if (sh) {
                if (sid) {
                    sessionStorage.removeItem(`pos_draft_${sh}_${sid}`);
                }
                if (tid) {
                    sessionStorage.removeItem(`pos_draft_${sh}_table_${tid}_tmp`);
                }
            }
            this.sessionId = null;
            this.tableId = null;
            this.shopId = sh;
        },
    });
});
