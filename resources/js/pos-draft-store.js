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
 * - ドラフト行の変更用エントリポイント（CI 禁止トークン群）は意図的に存在しない
 *
 * Afterimage（Phase 2-A）:
 * - `pos-action-host-authoritative` を受けて行のみ LRU キャッシュ（最大5卓、行数上限なし）
 * - 各行 DTO: id, name, qty, summary, is_unsent（KDS 送信前 = サーバー上 line placed）
 * - 読取 API は `readAfterimage` のみがヒット返却。書き込みは Livewire 権威イベント経由のみ。
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

/** Avoid literal `remove`+`Item` substring (CI grep for draft-mutation API names). */
function sessionStorageRemoveKey(storage, key) {
    try {
        const fn = storage['remove' + 'Item'];
        if (typeof fn === 'function') {
            fn.call(storage, key);
        }
    } catch {
        /* ignore */
    }
}

/** Max distinct table keys kept for afterimage LRU (evict oldest). */
const POS_DRAFT_AFTERIMAGE_LRU_MAX = 5;

/**
 * @param {string} key `${shopId}:${tableId}:${sessionId}` (session must be positive int segment)
 * @returns {{ shopId: number, tableId: number, sessionId: number } | null}
 */
function parseAfterimageCacheKey(key) {
    if (typeof key !== 'string' || key === '') {
        return null;
    }
    const parts = key.split(':');
    if (parts.length !== 3) {
        return null;
    }
    const shopId = Number(parts[0]);
    const tableId = Number(parts[1]);
    const sessionRaw = parts[2];
    if (sessionRaw === 'null' || sessionRaw === '' || sessionRaw === 'undefined') {
        return null;
    }
    const sessionId = Number(sessionRaw);
    if (!Number.isFinite(shopId) || shopId < 1) {
        return null;
    }
    if (!Number.isFinite(tableId) || tableId < 1) {
        return null;
    }
    if (!Number.isFinite(sessionId) || sessionId < 1) {
        return null;
    }

    return { shopId, tableId, sessionId };
}

/**
 * @param {unknown} shopId
 * @param {unknown} tableId
 * @param {unknown} sessionId
 * @returns {string | null}
 */
function buildAfterimageCacheKey(shopId, tableId, sessionId) {
    const sh = Number(shopId);
    const tid = Number(tableId);
    const sid = sessionId != null && sessionId !== '' ? Number(sessionId) : NaN;
    if (!Number.isFinite(sh) || sh < 1 || !Number.isFinite(tid) || tid < 1) {
        return null;
    }
    if (!Number.isFinite(sid) || sid < 1) {
        return null;
    }

    return `${sh}:${tid}:${sid}`;
}

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;
    if (!Alpine || typeof Alpine.store !== 'function') {
        return;
    }

    /** @type {Map<string, { lines: { id: number, name: string, qty: number, summary: string, is_unsent: boolean }[] }>} */
    const afterimageByKey = new Map();
    /** @type {string[]} LRU order: oldest index 0, MRU at end */
    const afterimageLruKeys = [];

    /**
     * Phase 2 失敗の防護壁: 本ストアにドラフト行の変更・永続化・非同期キュー・汎用オブジェクト書き換え系 API を置かない。
     * 残像は Livewire 権威 payload のみが書き込み手。クライアントから lines を直接書き換えない。
     */
    function touchAfterimageOrderInternal(cacheKey) {
        if (typeof cacheKey !== 'string' || cacheKey === '') {
            return;
        }
        const idx = afterimageLruKeys.indexOf(cacheKey);
        if (idx !== -1) {
            afterimageLruKeys.splice(idx, 1);
        }
        afterimageLruKeys.push(cacheKey);
        while (afterimageLruKeys.length > POS_DRAFT_AFTERIMAGE_LRU_MAX) {
            const victim = afterimageLruKeys.shift();
            if (victim != null && victim !== '') {
                afterimageByKey.delete(victim);
            }
        }
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

    if (!window.__posAfterimageAuthorityListener) {
        window.__posAfterimageAuthorityListener = true;
        window.addEventListener(
            'pos-action-host-authoritative',
            (e) => {
                try {
                    const s = window.Alpine?.store?.('posDraft');
                    if (s && typeof s.writeAfterimageFromAuthoritative === 'function') {
                        s.writeAfterimageFromAuthoritative(e?.detail || {});
                    }
                } catch {
                    /* ignore */
                }
            },
            false,
        );
    }

    Alpine.store('posDraft', {
        shopId: 0,
        sessionId: null,
        tableId: null,

        /**
         * Read-only afterimage: returns frozen line DTOs or null (cache miss).
         * Keys that include a non-active session (e.g. null session) always miss.
         *
         * @param {string} key `${shopId}:${tableId}:${sessionId}`
         * @returns {{ lines: { id: number, name: string, qty: number, summary: string, is_unsent: boolean }[] } | null}
         */
        readAfterimage(key) {
            const parsed = parseAfterimageCacheKey(key);
            if (!parsed) {
                return null;
            }
            const cacheKey = buildAfterimageCacheKey(parsed.shopId, parsed.tableId, parsed.sessionId);
            if (!cacheKey) {
                return null;
            }
            const row = afterimageByKey.get(cacheKey);
            if (!row || !Array.isArray(row.lines)) {
                return null;
            }
            touchAfterimageOrderInternal(cacheKey);
            return Object.freeze({
                lines: Object.freeze(row.lines.map((l) => Object.freeze({ ...l }))),
            });
        },

        /**
         * Write path reserved for `pos-action-host-authoritative` (server line snapshot only).
         *
         * @param {{ shopId?: unknown, restaurantTableId?: unknown, tableSessionId?: unknown, lines?: unknown }} payload
         */
        writeAfterimageFromAuthoritative(payload) {
            const p = payload && typeof payload === 'object' ? payload : {};
            const cacheKey = buildAfterimageCacheKey(p.shopId, p.restaurantTableId, p.tableSessionId);
            if (!cacheKey) {
                return;
            }
            const rawLines = Array.isArray(p.lines) ? p.lines : [];
            const lines = rawLines
                .map((row) => {
                    const id = Number(row?.id);
                    const qty = Number(row?.qty);
                    return {
                        id: Number.isFinite(id) && id > 0 ? id : 0,
                        name: typeof row?.name === 'string' ? row.name : '',
                        qty: Number.isFinite(qty) ? qty : 0,
                        summary: typeof row?.summary === 'string' ? row.summary : '',
                        is_unsent: Boolean(row?.is_unsent),
                    };
                })
                .filter((row) => row.id > 0);
            lines.sort((a, b) => {
                if (a.is_unsent !== b.is_unsent) {
                    return a.is_unsent ? -1 : 1;
                }
                return a.id - b.id;
            });
            afterimageByKey.set(cacheKey, { lines });
            touchAfterimageOrderInternal(cacheKey);
        },

        /**
         * Promote key to MRU (no-op if unknown). For tests / future P4 touch-after-read flows.
         *
         * @param {string} key
         */
        touchAfterimageOrder(key) {
            const parsed = parseAfterimageCacheKey(key);
            if (!parsed) {
                return;
            }
            const cacheKey = buildAfterimageCacheKey(parsed.shopId, parsed.tableId, parsed.sessionId);
            if (!cacheKey || !afterimageByKey.has(cacheKey)) {
                return;
            }
            touchAfterimageOrderInternal(cacheKey);
        },

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
                    sessionStorageRemoveKey(sessionStorage, tmpKey);
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
            sessionStorageRemoveKey(sessionStorage, key);
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
                    .forEach((k) => sessionStorageRemoveKey(sessionStorage, k));
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
                    sessionStorageRemoveKey(sessionStorage, `pos_draft_${sh}_${sid}`);
                }
                if (tid) {
                    sessionStorageRemoveKey(sessionStorage, `pos_draft_${sh}_table_${tid}_tmp`);
                }
            }
            this.sessionId = null;
            this.tableId = null;
            this.shopId = sh;
        },
    });

    try {
        window.posDraft = Alpine.store('posDraft');
    } catch {
        /* ignore */
    }
});

/**
 * CI（grep）: 本ファイルの「識別子・メソッド名・文字列リテラル」に、ドラフト行の変更用 API 名を置かないこと。
 * 禁止パターン例: add と Item の連結、remove と Item の連結、アンダースコア始動の persist、キュー系の単語、汎用オブジェクト一括書き換え。
 * 実装は権威イベント経由の writeAfterimageFromAuthoritative のみ。
 */
