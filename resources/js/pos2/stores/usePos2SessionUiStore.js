/**
 * POS V2 SYNAPSE: モニタリング / アディング、サーバー権威の卓・セッション UI 状態。
 * @see docs/pos_v2_architecture.md §10
 */

import { defineStore } from 'pinia';
import { toRaw } from 'vue';
import { buildPos2JsonHeaders } from '../utils/pos2Http';
import { useTableLabelStore } from './useTableLabelStore';

/** @typedef {'monitoring' | 'adding'} Pos2UiMode */

/** SWR: セッション注文キャッシュの最大件数（超えたら LRU で削除） */
const ORDERS_CACHE_MAX_KEYS = 20;

export const usePos2SessionUiStore = defineStore('pos2SessionUi', {
    state: () => ({
        /** @type {Pos2UiMode} */
        uiMode: 'monitoring',
        /** @type {Array<Record<string, unknown>>} */
        dashboardTiles: [],
        dashboardGeneratedAt: null,
        /** 選択中の restaurant_tables.id */
        selectedRestaurantTableId: null,
        /** アクティブ table_sessions.id（タイルに無い場合は null） */
        activeTableSessionId: null,
        sessionRevision: 0,
        /** GET /pos2/api/sessions/:id/orders の直近成功ペイロード */
        sessionOrdersPayload: null,
        sessionOrdersLoadedAt: null,
        sessionOrdersError: null,
        sessionOrdersLoading: false,
        /**
         * SWR: `table_session_id` → 直近成功した GET …/orders のペイロード（メモリキャッシュ）。
         * @type {Record<number, Record<string, unknown>>}
         */
        ordersCache: {},
        /**
         * `ordersCache` の LRU 順（古い方が先頭）。キーは正の整数 table_session_id。
         * @type {number[]}
         */
        ordersCacheLruOrder: [],
        /** 楽観的行削除の多重実行でバックアップを潰さない */
        optimisticLineDeleteBusy: false,
        /**
         * 行削除で PIN 承認が必要になったとき `{ lineId, sessionId }`。モーダル表示用。
         * @type {{ lineId: number, sessionId: number } | null}
         */
        lineDeletePinChallenge: null,
    }),

    getters: {
        /**
         * 旧POS {@see TableActionHost::getHasUnackedPlacedProperty} と整合: PosOrder status === placed の有無。
         * @param {object} state
         */
        hasUnackedPlacedOrders: (state) => state.sessionOrdersPayload?.has_unacked_placed === true,

        /**
         * GET .../orders の orders[].total_minor 合算（セッション注文ペイロードのみを SSOT とする）。
         */
        sessionTotalMinor() {
            const orders = this.sessionOrdersPayload?.orders;
            if (!Array.isArray(orders)) {
                return 0;
            }
            return orders.reduce((s, o) => s + Number(o?.total_minor ?? 0), 0);
        },

        tileForSelected(state) {
            const tid = state.selectedRestaurantTableId;
            if (tid == null) return null;
            const tiles = state.dashboardTiles ?? [];
            return tiles.find((t) => Number(t.restaurantTableId) === Number(tid)) ?? null;
        },
    },

    actions: {
        setUiMode(mode) {
            this.uiMode = mode === 'adding' ? 'adding' : 'monitoring';
        },

        enterAddingMode() {
            this.uiMode = 'adding';
        },

        exitToMonitoring() {
            this.uiMode = 'monitoring';
        },

        dismissLineDeletePinChallenge() {
            this.lineDeletePinChallenge = null;
        },

        /**
         * 卓選択時: タイルからセッション ID を同期し、モニタリングへリセット。
         * @param {number|null} restaurantTableId
         * @param {number|null} activeSessionId
         */
        syncSelectionFromTile(restaurantTableId, activeSessionId) {
            this.selectedRestaurantTableId = restaurantTableId != null ? Number(restaurantTableId) : null;
            this.activeTableSessionId = activeSessionId != null && Number(activeSessionId) > 0
                ? Number(activeSessionId)
                : null;
            this.uiMode = 'monitoring';
            this.sessionOrdersError = null;

            const sid = this.activeTableSessionId;
            if (sid != null && sid > 0 && this.ordersCache[sid]) {
                const cached = this.ordersCache[sid];
                this.sessionOrdersPayload = cached;
                this.sessionRevision = Number(cached.session_revision ?? 0);
                this._touchOrdersCacheKey(sid);
            } else {
                this.sessionOrdersPayload = null;
                this.sessionRevision = 0;
            }
        },

        applySessionOrdersJson(data) {
            if (!data || typeof data !== 'object') {
                this.sessionOrdersPayload = null;
                return;
            }
            this.sessionOrdersPayload = data;
            this.sessionRevision = Number(data.session_revision ?? 0);
            this.sessionOrdersLoadedAt = new Date().toISOString();

            const tsid = Number(data.table_session_id ?? 0);
            if (Number.isFinite(tsid) && tsid > 0) {
                this._putOrdersCache(tsid, data);
                const labelStore = useTableLabelStore();
                const cname = data.customer_name ?? null;
                const ctel = data.customer_tel ?? data.customer_phone ?? null;
                labelStore.setLabelFromServer(tsid, cname, ctel);
            }
        },

        /**
         * LRU 上で該当セッションを「最近使った」にする。
         * @param {number} sid
         */
        _touchOrdersCacheKey(sid) {
            const k = Number(sid);
            if (!Number.isFinite(k) || k < 1) {
                return;
            }
            const prev = Array.isArray(this.ordersCacheLruOrder) ? this.ordersCacheLruOrder : [];
            const next = prev.filter((x) => Number(x) !== k);
            next.push(k);
            this.ordersCacheLruOrder = next;
        },

        /**
         * `ordersCache` に格納し、20 件超なら LRU で最古キーを削除する。
         * @param {number} tsid
         * @param {Record<string, unknown>} payload
         */
        _putOrdersCache(tsid, payload) {
            const sid = Number(tsid);
            if (!Number.isFinite(sid) || sid < 1) {
                return;
            }
            this.ordersCache = {
                ...this.ordersCache,
                [sid]: payload,
            };
            this._touchOrdersCacheKey(sid);
            while (this.ordersCacheLruOrder.length > ORDERS_CACHE_MAX_KEYS) {
                const oldest = this.ordersCacheLruOrder.shift();
                if (oldest == null) {
                    break;
                }
                const o = Number(oldest);
                if (Number.isFinite(o) && o > 0) {
                    const next = { ...this.ordersCache };
                    delete next[o];
                    this.ordersCache = next;
                }
            }
        },

        /**
         * `ordersCache` / LRU から 1 キーを除去。
         * @param {number} sid
         */
        _removeOrdersCacheKey(sid) {
            const k = Number(sid);
            if (!Number.isFinite(k) || k < 1) {
                return;
            }
            if (!Object.prototype.hasOwnProperty.call(this.ordersCache, k)) {
                this.ordersCacheLruOrder = (this.ordersCacheLruOrder ?? []).filter((x) => Number(x) !== k);
                return;
            }
            const next = { ...this.ordersCache };
            delete next[k];
            this.ordersCache = next;
            this.ordersCacheLruOrder = (this.ordersCacheLruOrder ?? []).filter((x) => Number(x) !== k);
        },

        /**
         * 空卓初送信など: アクティブセッション ID だけ更新する（`syncSelectionFromTile` は payload を null にするため使わない）。
         * @param {number|string|null|undefined} sessionId
         */
        patchActiveSessionId(sessionId) {
            const sid = Number(sessionId);
            if (Number.isFinite(sid) && sid > 0) {
                this.activeTableSessionId = sid;
            }
        },

        /**
         * @param {unknown[]} orders
         */
        _recomputeHasUnackedPlaced(orders) {
            if (!Array.isArray(orders)) return false;
            for (const o of orders) {
                const st = String(o?.status ?? '').toLowerCase();
                if (st === 'placed') {
                    return true;
                }
                const lines = o?.lines;
                if (!Array.isArray(lines)) continue;
                for (const ln of lines) {
                    if (ln?.is_unsent === true) {
                        return true;
                    }
                }
            }
            return false;
        },

        /**
         * Add to Table 直前の楽観マージ: 仮注文を orders に足す（POST 成功後に GET で丸ごと上書き）。
         * @param {{ clientSubmitId: string, lines: unknown[] }} payload
         */
        appendOptimisticStaffSubmit(payload) {
            const clientSubmitId = String(payload?.clientSubmitId ?? '').trim();
            const lineList = Array.isArray(payload?.lines) ? payload.lines : [];
            if (clientSubmitId === '' || lineList.length === 0) return;

            const optOrderId = `opt:${clientSubmitId}`;
            const linesOut = lineList.map((l, i) => {
                const qty = Math.max(1, Number(l?.qty ?? 1));
                const unit = Math.round(Number(l?.total_unit_price_minor ?? 0));
                const lineTotal = unit * qty;
                const productName = String(l?.name ?? '').trim() || '—';
                const styleNameRaw = l?.selected_option_snapshot?.name;
                const styleName = styleNameRaw != null && String(styleNameRaw).trim() !== ''
                    ? String(styleNameRaw).trim()
                    : null;
                const toppingNames = Array.isArray(l?.topping_snapshots)
                    ? l.topping_snapshots
                        .map((t) => (t && t.name != null ? String(t.name).trim() : ''))
                        .filter((x) => x !== '')
                    : [];
                return {
                    id: `${optOrderId}:line:${i}`,
                    order_id: optOrderId,
                    line_status: 'placed',
                    is_unsent: true,
                    qty,
                    display_name: productName,
                    product_name: productName,
                    style_name: styleName,
                    topping_names: toppingNames,
                    line_total_minor: lineTotal,
                    unit_price_minor: unit,
                    ordered_by: 'staff',
                };
            });
            const totalMinor = linesOut.reduce((s, ln) => s + ln.line_total_minor, 0);
            const newOrder = {
                id: optOrderId,
                status: 'placed',
                placed_at: new Date().toISOString(),
                ordered_by: 'staff',
                total_minor: totalMinor,
                lines: linesOut,
            };

            const prev = this.sessionOrdersPayload;
            const baseOrders = [...(prev && Array.isArray(prev.orders) ? prev.orders : [])];
            baseOrders.push(newOrder);

            const tsid = Number(prev?.table_session_id ?? this.activeTableSessionId ?? 0);
            const rtid = Number(prev?.restaurant_table_id ?? this.selectedRestaurantTableId ?? 0);

            this.sessionOrdersPayload = {
                ...(prev && typeof prev === 'object' ? prev : {}),
                table_session_id: tsid,
                restaurant_table_id: rtid,
                session_revision: Number(prev?.session_revision ?? this.sessionRevision) || 0,
                has_unacked_placed: true,
                orders: baseOrders,
                generated_at: new Date().toISOString(),
                schema_version: Number(prev?.schema_version ?? 1) || 1,
                customer_name: prev?.customer_name ?? null,
                customer_tel: prev?.customer_tel ?? prev?.customer_phone ?? null,
            };

            if (Number.isFinite(rtid) && rtid > 0) {
                const tiles = this.dashboardTiles ?? [];
                const idx = tiles.findIndex((t) => Number(t?.restaurantTableId) === rtid);
                if (idx !== -1) {
                    const nextTiles = [...tiles];
                    nextTiles[idx] = {
                        ...tiles[idx],
                        uiStatus: 'pending',
                        unackedPlacedLineExists: true,
                    };
                    this.dashboardTiles = nextTiles;
                }
            }
        },

        /**
         * POST 失敗時: 楽観で足した仮注文だけ除去。
         * @param {string} clientSubmitId
         */
        removeOptimisticStaffSubmit(clientSubmitId) {
            const id = String(clientSubmitId ?? '').trim();
            if (id === '') return;
            const optOrderId = `opt:${id}`;
            const prev = this.sessionOrdersPayload;
            if (!prev || !Array.isArray(prev.orders)) return;
            const orders = prev.orders.filter((o) => String(o?.id) !== optOrderId);
            const hasUnacked = this._recomputeHasUnackedPlaced(orders);
            this.sessionOrdersPayload = {
                ...prev,
                orders,
                has_unacked_placed: hasUnacked,
                generated_at: new Date().toISOString(),
            };
            void this.fetchTableDashboard({ silent: true });
        },

        /**
         * 卓ダッシュボード取得。
         * @param {{ silent?: boolean }} [options] — `silent: true`（定期ポーリング）時は失敗しても `dashboardTiles` を消さない（サイレントフェイル）。
         */
        async fetchTableDashboard(options = {}) {
            const silent = options.silent === true;
            try {
                const res = await fetch('/pos2/api/table-dashboard', {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!res.ok) {
                    if (!silent) {
                        this.dashboardTiles = [];
                    }
                    return { ok: false, status: res.status };
                }
                const data = await res.json();
                this.dashboardTiles = Array.isArray(data.tiles) ? data.tiles : [];
                this.dashboardGeneratedAt = data.generated_at ?? null;
                return { ok: true };
            } catch {
                if (!silent) {
                    this.dashboardTiles = [];
                }
                return { ok: false, status: 0, reason: 'network' };
            }
        },

        /**
         * @param {number|string} sessionId
         * @param {{ silent?: boolean, skipLoadingUi?: boolean }} [options]
         *   - `silent: true` … GET 失敗でも `sessionOrdersError` を更新しない（ポーリング用）。
         *   - `skipLoadingUi: true` … 楽観再検証時に「読み込み中」を出さない（一覧のチラつき防止）。
         */
        async fetchSessionOrders(sessionId, options = {}) {
            const silent = options.silent === true;
            const skipLoadingUi = options.skipLoadingUi === true;
            const sid = Number(sessionId);
            if (!Number.isFinite(sid) || sid < 1) {
                this.sessionOrdersPayload = null;
                if (!silent) {
                    this.sessionOrdersError = 'no_session';
                }
                return { ok: false, reason: 'no_session' };
            }
            if (!skipLoadingUi) {
                this.sessionOrdersLoading = true;
            }
            this.sessionOrdersError = null;
            try {
                const res = await fetch(`/pos2/api/sessions/${sid}/orders`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!res.ok) {
                    // 直前の成功ペイロードを消さない（GET 失敗で一覧が空振りに見えるのを防ぐ）
                    if (!silent) {
                        this.sessionOrdersError = `http_${res.status}`;
                    }
                    return { ok: false, status: res.status };
                }
                const data = await res.json();
                this.applySessionOrdersJson(data);
                return { ok: true };
            } catch (e) {
                if (!silent) {
                    this.sessionOrdersError = e instanceof Error ? e.message : 'fetch_failed';
                }
                return { ok: false };
            } finally {
                if (!skipLoadingUi) {
                    this.sessionOrdersLoading = false;
                }
            }
        },

        /**
         * 確定オーダー行の楽観削除 → POST 削除 API。失敗時はスナップショットへロールバック。
         * @param {number|string} lineId order_lines.id
         * @param {number|string} currentSessionId table_sessions.id（ペイロードと一致必須）
         * @param {number|string|null|undefined} [approverStaffId]
         * @param {string|null|undefined} [approvalPin]
         * @returns {Promise<boolean>} 検証失敗・API失敗・ネットワーク失敗で false（成功時 true）
         */
        async optimisticDeleteOrderLine(lineId, currentSessionId, approverStaffId = null, approvalPin = null) {
            const curSid = Number(currentSessionId);
            const payloadSid = Number(this.sessionOrdersPayload?.table_session_id ?? 0);
            if (!Number.isFinite(curSid) || curSid < 1) {
                return false;
            }
            if (payloadSid !== curSid) {
                return false;
            }
            const payload = this.sessionOrdersPayload;
            if (!payload || !Array.isArray(payload.orders)) {
                return false;
            }

            const lineIdNum = Number(lineId);
            if (!Number.isFinite(lineIdNum) || lineIdNum < 1) {
                return false;
            }

            let lineFound = false;
            for (const o of payload.orders) {
                const lines = Array.isArray(o?.lines) ? o.lines : [];
                for (const ln of lines) {
                    if (Number(ln?.id) === lineIdNum) {
                        lineFound = true;
                        break;
                    }
                }
                if (lineFound) break;
            }
            if (!lineFound) {
                return false;
            }

            const rawPayload = toRaw(this.sessionOrdersPayload);
            let rollbackPayload;
            try {
                rollbackPayload = structuredClone(rawPayload);
            } catch {
                try {
                    rollbackPayload = JSON.parse(JSON.stringify(rawPayload));
                } catch {
                    return false;
                }
            }

            if (this.optimisticLineDeleteBusy) {
                return false;
            }
            this.optimisticLineDeleteBusy = true;

            const applyOptimisticRemoval = () => {
                const ordersIn = Array.isArray(this.sessionOrdersPayload?.orders)
                    ? this.sessionOrdersPayload.orders
                    : [];
                const newOrders = [];
                for (const o of ordersIn) {
                    const lines = Array.isArray(o?.lines) ? o.lines : [];
                    const newLines = lines.filter((ln) => Number(ln?.id) !== lineIdNum);
                    if (newLines.length === 0) {
                        continue;
                    }
                    const totalMinor = newLines.reduce((s, ln) => s + Number(ln?.line_total_minor ?? 0), 0);
                    newOrders.push({
                        ...o,
                        lines: newLines,
                        total_minor: totalMinor,
                    });
                }
                const hasUnacked = this._recomputeHasUnackedPlaced(newOrders);
                this.sessionOrdersPayload = {
                    ...this.sessionOrdersPayload,
                    orders: newOrders,
                    has_unacked_placed: hasUnacked,
                    generated_at: new Date().toISOString(),
                };
                this.sessionRevision = Number(this.sessionOrdersPayload.session_revision ?? 0);
                this._putOrdersCache(curSid, this.sessionOrdersPayload);
            };

            const restoreRollback = () => {
                this.sessionOrdersPayload = rollbackPayload;
                this.sessionRevision = Number(rollbackPayload?.session_revision ?? 0);
                const rbSid = Number(rollbackPayload?.table_session_id ?? curSid);
                if (Number.isFinite(rbSid) && rbSid > 0) {
                    this._putOrdersCache(rbSid, rollbackPayload);
                }
            };

            try {
                applyOptimisticRemoval();

                const bodyObj = {};
                if (approverStaffId != null && Number(approverStaffId) > 0) {
                    bodyObj.approver_staff_id = Number(approverStaffId);
                }
                if (approvalPin != null && String(approvalPin).trim() !== '') {
                    bodyObj.approval_pin = String(approvalPin);
                }

                const res = await fetch(
                    `/pos2/api/sessions/${curSid}/order-lines/${lineIdNum}/delete`,
                    {
                        method: 'POST',
                        headers: buildPos2JsonHeaders(),
                        credentials: 'same-origin',
                        body: JSON.stringify(bodyObj),
                    },
                );

                const data = await res.json().catch(() => ({}));

                if (res.ok && data && data.success === true) {
                    const rev = Number(data.session_revision ?? 0);
                    const tsid = Number(data.table_session_id ?? curSid);
                    this.sessionRevision = rev;
                    this.sessionOrdersPayload = {
                        ...this.sessionOrdersPayload,
                        session_revision: rev,
                    };
                    if (Number.isFinite(tsid) && tsid > 0) {
                        this._putOrdersCache(tsid, this.sessionOrdersPayload);
                    }
                    return true;
                }

                restoreRollback();

                if (res.status === 422 && data && data.pin_approval_required === true) {
                    this.lineDeletePinChallenge = {
                        lineId: lineIdNum,
                        sessionId: curSid,
                    };
                    return false;
                }

                const msg = typeof data.error === 'string' && data.error.trim() !== ''
                    ? data.error
                    : (typeof data.message === 'string' && data.message.trim() !== ''
                        ? data.message
                        : '削除できませんでした');
                window.alert(msg);
                return false;
            } catch (e) {
                restoreRollback();
                window.alert(e instanceof Error ? e.message : '削除できませんでした');
                return false;
            } finally {
                this.optimisticLineDeleteBusy = false;
            }
        },

        async sendRecuStaff() {
            const sid = this.activeTableSessionId;
            if (sid == null || sid < 1) {
                return { ok: false, reason: 'no_session' };
            }
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const res = await fetch(`/pos2/api/sessions/${sid}/recu-staff`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    expected_session_revision: this.sessionRevision,
                }),
            });
            if (res.status === 409) {
                await this.fetchSessionOrders(sid);
                return { ok: false, status: 409, body: await res.json().catch(() => ({})) };
            }
            if (!res.ok) {
                return { ok: false, status: res.status };
            }
            await this.fetchSessionOrders(sid);
            await this.fetchTableDashboard();
            return { ok: true };
        },

        /**
         * 卓移動 POST /pos2/tables/move
         * @param {number} destTableId restaurant_tables.id
         * @returns {Promise<{ ok: true } | { ok: false, reason?: string, status?: number, body?: Record<string, unknown> }>}
         */
        async submitChangeTable(destTableId) {
            const sid = this.activeTableSessionId;
            if (sid == null || sid < 1) {
                return { ok: false, reason: 'no_session' };
            }
            const res = await fetch('/pos2/tables/move', {
                method: 'POST',
                credentials: 'same-origin',
                headers: buildPos2JsonHeaders(),
                body: JSON.stringify({
                    source_table_session_id: sid,
                    dest_table_id: Number(destTableId),
                    expected_session_revision: this.sessionRevision,
                }),
            });
            const body = await res.json().catch(() => ({}));
            if (res.status === 409) {
                await this.fetchSessionOrders(sid, { skipLoadingUi: true, silent: true });
                await this.fetchTableDashboard({ silent: true });
                return { ok: false, status: 409, body };
            }
            if (!res.ok) {
                return { ok: false, status: res.status, body };
            }
            return { ok: true, body };
        },

        /**
         * Bridge / 楽観 UI: セッション ID に紐づくタイルの `uiStatus` だけを即時更新する。
         * @param {number|string|null|undefined} sessionId
         * @param {string} newStatus
         */
        optimisticUpdateTileStatusBySessionId(sessionId, newStatus) {
            const sid = Number(sessionId);
            if (!Number.isFinite(sid) || sid < 1) {
                return;
            }
            const tiles = this.dashboardTiles ?? [];
            const idx = tiles.findIndex((t) => Number(t?.activeTableSessionId) === sid);
            if (idx === -1) {
                return;
            }
            const tile = tiles[idx];
            const nextTiles = [...tiles];
            nextTiles[idx] = {
                ...tile,
                uiStatus: String(newStatus ?? ''),
            };
            this.dashboardTiles = nextTiles;
        },

        /**
         * Bridge / 楽観 UI: 会計完了などで該当セッションの卓タイルを空卓相当にし、
         * 選択中なら注文ペイロードもクリア。SWR キャッシュからも除去する。
         * @param {number|string|null|undefined} sessionId
         */
        optimisticClearSession(sessionId) {
            const sid = Number(sessionId);
            if (!Number.isFinite(sid) || sid < 1) {
                return;
            }
            const tiles = this.dashboardTiles ?? [];
            const idx = tiles.findIndex((t) => Number(t?.activeTableSessionId) === sid);
            if (idx !== -1) {
                const tile = tiles[idx];
                const nextTiles = [...tiles];
                nextTiles[idx] = {
                    ...tile,
                    uiStatus: 'free',
                    activeTableSessionId: null,
                    sessionTotalMinor: 0,
                    relevantPosOrderCount: 0,
                    unackedPlacedPosOrderCount: 0,
                    unackedPlacedLineExists: false,
                };
                this.dashboardTiles = nextTiles;
            }

            if (this.activeTableSessionId === sid) {
                this.activeTableSessionId = null;
                this.sessionOrdersPayload = null;
                this.sessionRevision = 0;
                this.sessionOrdersLoadedAt = null;
                this.sessionOrdersError = null;
            }

            this._removeOrdersCacheKey(sid);
        },
    },
});
