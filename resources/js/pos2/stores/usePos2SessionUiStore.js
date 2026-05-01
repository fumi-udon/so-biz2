/**
 * POS V2 SYNAPSE: モニタリング / アディング、サーバー権威の卓・セッション UI 状態。
 * @see docs/pos_v2_architecture.md §10
 */

import { defineStore } from 'pinia';
import { buildPos2JsonHeaders } from '../utils/pos2Http';

/** @typedef {'monitoring' | 'adding'} Pos2UiMode */

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
            this.sessionOrdersPayload = null;
            this.sessionOrdersError = null;
            this.sessionRevision = 0;
        },

        applySessionOrdersJson(data) {
            if (!data || typeof data !== 'object') {
                this.sessionOrdersPayload = null;
                return;
            }
            this.sessionOrdersPayload = data;
            this.sessionRevision = Number(data.session_revision ?? 0);
            this.sessionOrdersLoadedAt = new Date().toISOString();
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
            const rtid = Number(prev?.restaurant_table_id ?? 0);

            this.sessionOrdersPayload = {
                ...(prev && typeof prev === 'object' ? prev : {}),
                table_session_id: tsid,
                restaurant_table_id: rtid,
                session_revision: Number(prev?.session_revision ?? this.sessionRevision) || 0,
                has_unacked_placed: true,
                orders: baseOrders,
                generated_at: new Date().toISOString(),
                schema_version: Number(prev?.schema_version ?? 1) || 1,
            };
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
    },
});
