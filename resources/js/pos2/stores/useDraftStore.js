/**
 * @file useDraftStore.js
 * POS V2 ドラフトストア（主に卓紐付け・LS 復元）。送信は即コミット（submitLines）が正で、複数行カートは使わない。
 *
 * スキーマバージョン: 2
 *   v1 からの変更:
 *     - 行構造を CartLineSnapshot（フラット・スナップショット）に全面置換
 *     - 旧フィールド（styleSnapshot, toppingSnapshots, menu_item_id）を廃止
 *     - 金額フィールド名を *_minor に統一
 *
 * ドラフトキー: pos_draft_{shopId}_{tableSessionId}
 *   - tableSessionId 単独キー禁止（前客データ復活リスク）
 */

import { defineStore } from 'pinia';
import { toRaw } from 'vue';
import axios from 'axios';
import { assertCartLine } from '../utils/cartLineBuilder';
import { generateUUID } from '../utils/uuid.js';
import { buildPos2JsonHeaders } from '../utils/pos2Http.js';
import { pushOrderSubmitTrace, recordLastOrderSubmitAudit } from '../utils/orderSubmitInvestigation.js';
import { useDebugStore } from './useDebugStore';
import { useMasterStore } from './useMasterStore';
import { usePos2SessionUiStore } from './usePos2SessionUiStore';

export const DRAFT_SCHEMA_VERSION = 2;

/**
 * Vue Proxy を剥がしてから structuredClone（失敗時は浅いコピーにフォールバック）。
 * @param {unknown} value
 * @returns {unknown[]}
 */
function cloneLinesSnapshot(value) {
    try {
        const raw = toRaw(value);
        if (!Array.isArray(raw)) {
            return [];
        }
        return structuredClone(raw);
    } catch {
        return Array.isArray(value) ? [...value] : [];
    }
}

function draftKey(shopId, tableSessionId) {
    return `pos_draft_${shopId}_${tableSessionId}`;
}

export const useDraftStore = defineStore('pos2Draft', {
    state: () => ({
        shopId: 0,
        tableSessionId: null,
        /**
         * セッションなし空卓用: restaurant_tables.id。
         * 送信時にサーバーが getOrCreateActiveSession して 201 で session_id を返す。
         * tableSessionId が確定したら null に戻す。
         */
        pendingTableId: null,
        /** @type {import('../utils/cartLineBuilder').CartLineSnapshot[]} */
        lines: [],
        loadedAt: null,
        /** 注文送信中（ConfigModal の isSubmitting と衝突させない） */
        isOrderSubmitting: false,
    }),

    getters: {
        key: (state) => (state.tableSessionId ? draftKey(state.shopId, state.tableSessionId) : null),

        /** @deprecated 後方互換エイリアス */
        draftKey() { return this.key; },

        /** カート内の商品点数合計（qty の合算） */
        totalItemsCount: (state) => state.lines.reduce((s, l) => s + Math.max(1, Number(l?.qty ?? 0)), 0),

        /** カート合計 minor（qty 乗算済み） */
        totalMinor: (state) => state.lines.reduce(
            (s, l) => s + (Number(l?.total_unit_price_minor ?? 0) * Math.max(1, Number(l?.qty ?? 1))),
            0,
        ),

        hasLines: (state) => state.lines.length > 0,
    },

    actions: {
        /** LocalStorage に指定テーブルのドラフトが存在するか確認。 */
        hasDraftForTable(shopId, tableSessionId) {
            return localStorage.getItem(draftKey(shopId, tableSessionId)) !== null;
        },

        /**
         * LocalStorage からドラフトを復元。
         * schema_version 不一致は自動破棄（旧 v1 キャッシュも破棄）。
         *
         * @param {number} shopId
         * @param {string|number} tableSessionId
         * @param {object} context - { traceId, debugEnabled }
         * @returns {boolean}
         */
        loadDraftFromStorage(shopId, tableSessionId, context = {}) {
            this.shopId = Number(shopId);
            this.tableSessionId = String(tableSessionId);

            const key = draftKey(shopId, tableSessionId);
            const raw = localStorage.getItem(key);

            if (!raw) {
                this.lines = [];
                this.loadedAt = new Date().toISOString();
                this.safeTrace(context, 'draft.restore.empty', {
                    traceId: context.traceId ?? null,
                    tableSessionId: this.tableSessionId,
                    key,
                    reason: 'no_data',
                });
                return false;
            }

            try {
                const parsed = JSON.parse(raw);

                if ((parsed?.schema_version ?? 0) !== DRAFT_SCHEMA_VERSION) {
                    localStorage.removeItem(key);
                    this.lines = [];
                    this.loadedAt = new Date().toISOString();
                    this.safeTrace(context, 'draft.restore.schema_mismatch', {
                        traceId: context.traceId ?? null,
                        tableSessionId: this.tableSessionId,
                        key,
                        storedVersion: parsed?.schema_version ?? null,
                        expectedVersion: DRAFT_SCHEMA_VERSION,
                    });
                    return false;
                }

                this.lines = Array.isArray(parsed?.lines) ? parsed.lines : [];
                this.loadedAt = new Date().toISOString();

                this.safeTrace(context, 'draft.restore.succeeded', {
                    traceId: context.traceId ?? null,
                    tableSessionId: this.tableSessionId,
                    key,
                    lineCount: this.lines.length,
                    totalItemsCount: this.totalItemsCount,
                    totalMinor: this.totalMinor,
                    savedAt: parsed?.saved_at ?? null,
                });
                return true;
            } catch (error) {
                localStorage.removeItem(key);
                this.lines = [];
                this.loadedAt = new Date().toISOString();
                this.safeTrace(context, 'draft.restore.failed', {
                    traceId: context.traceId ?? null,
                    tableSessionId: this.tableSessionId,
                    key,
                    reason: 'parse_error',
                    message: error instanceof Error ? error.message : 'unknown',
                });
                return false;
            }
        },

        /**
         * CartLineSnapshot をカートに追加し、LocalStorage に即時同期。
         *
         * @param {import('../utils/cartLineBuilder').CartLineSnapshot} cartLine
         * @param {object} context - { traceId, debugEnabled }
         * @returns {{ ok: boolean, reason: string|null }}
         */
        addLine(cartLine, context = {}) {
            // ランタイムアサート（デバッグ時のみ）
            const { ok: assertOk, errors } = assertCartLine(cartLine, context.debugEnabled === true);
            if (context.debugEnabled && errors.length > 0) {
                this.safeTrace(context, 'draft.addLine.assert_failed', {
                    traceId: context.traceId ?? null,
                    errors,
                    cartLine,
                });
                // アサート失敗でも投入は続行（本番相当でも壊れない）
            }

            // 必須項目の最低限チェック（本番でも効くガード）
            if (!cartLine || typeof cartLine !== 'object') {
                const reason = 'cartLine is not an object';
                this.safeTrace(context, 'draft.addLine.rejected', { reason });
                return { ok: false, reason };
            }
            if (!cartLine.product_id) {
                const reason = 'missing product_id';
                this.safeTrace(context, 'draft.addLine.rejected', { reason });
                return { ok: false, reason };
            }
            if (!Number.isInteger(cartLine.total_unit_price_minor)) {
                const reason = 'total_unit_price_minor is not integer';
                this.safeTrace(context, 'draft.addLine.rejected', { reason });
                return { ok: false, reason };
            }

            const mergeKey = typeof cartLine.merge_key === 'string' ? cartLine.merge_key : '';
            const addQty = Math.max(1, Math.floor(Number(cartLine.qty) || 1));

            if (mergeKey.length > 0) {
                const idx = this.lines.findIndex((l) => l.merge_key === mergeKey);
                if (idx >= 0) {
                    const existing = this.lines[idx];
                    const prevQty = Math.max(1, Math.floor(Number(existing.qty) || 1));
                    existing.qty = prevQty + addQty;
                    this._persist();

                    this.safeTrace(context, 'draft.addLine.merged', {
                        traceId: context.traceId ?? null,
                        merge_key: mergeKey,
                        cart_item_id: existing.cart_item_id,
                        display_full_name: existing.display_full_name,
                        qty_before: prevQty,
                        qty_after: existing.qty,
                        total_unit_price_minor: existing.total_unit_price_minor,
                        lineCount: this.lines.length,
                        totalMinor: this.totalMinor,
                    });

                    return { ok: true, reason: null, merged: true };
                }
            }

            this.lines.push(cartLine);
            this._persist();

            this.safeTrace(context, 'draft.addLine.succeeded', {
                traceId: context.traceId ?? null,
                cart_item_id: cartLine.cart_item_id,
                merge_key: mergeKey || null,
                display_full_name: cartLine.display_full_name,
                total_unit_price_minor: cartLine.total_unit_price_minor,
                qty: cartLine.qty,
                lineCount: this.lines.length,
                totalMinor: this.totalMinor,
            });

            return { ok: true, reason: null, merged: false };
        },

        /**
         * 指定 cart_item_id の行を削除し、LocalStorage に即時同期。
         * @param {string} cartItemId
         * @param {object} context
         */
        removeLine(cartItemId, context = {}) {
            const before = this.lines.length;
            this.lines = this.lines.filter((l) => l.cart_item_id !== cartItemId);
            this._persist();
            this.safeTrace(context, 'draft.removeLine', {
                traceId: context.traceId ?? null,
                cartItemId,
                removedCount: before - this.lines.length,
                lineCount: this.lines.length,
            });
        },

        /**
         * ドラフト全クリア（送信成功後）。
         * @param {object} context
         */
        clearDraft(context = {}) {
            const key = this.key;
            if (key) localStorage.removeItem(key);
            this.lines = [];
            this.loadedAt = new Date().toISOString();
            this.safeTrace(context, 'draft.cleared', { traceId: context.traceId ?? null });
        },

        /**
         * Dev clean up 等: localStorage を外部で全削除した後のメモリ整合。
         * @param {number} shopId
         */
        resetAfterLocalStoragePurge(shopId) {
            this.shopId = Number(shopId) || 0;
            this.lines = [];
            this.tableSessionId = null;
            this.pendingTableId = null;
            this.loadedAt = new Date().toISOString();
            this.isOrderSubmitting = false;
        },

        /**
         * 楽観送信の裏処理: POST → 成功時 GET で権威上書き／失敗時ロールバック。
         * UI スレッドはブロックしない（呼び出し元は void）。
         *
         * @param {object} ctx
         */
        async _runOptimisticSubmitBackground(ctx) {
            const {
                clientSubmitId,
                linesPlain,
                payload,
                submitUrl,
                traceId,
                dbg,
                debugStore,
                correlation,
                started,
            } = ctx;

            const sessionUi = usePos2SessionUiStore();
            let auditOrderSubmit = true;
            let httpStatus = null;
            let durationMs = 0;
            let outcome = 'unknown';
            let orderId = null;
            let errMessage = null;

            const rollbackLines = Array.isArray(ctx.rollbackLines)
                ? ctx.rollbackLines
                : linesPlain;

            const rollback = () => {
                sessionUi.removeOptimisticStaffSubmit(clientSubmitId);
                try {
                    this.lines = JSON.parse(JSON.stringify(rollbackLines));
                } catch {
                    this.lines = Array.isArray(rollbackLines) ? [...rollbackLines] : [];
                }
                this._persist();
            };

            const alertFailure = (msg) => {
                const m = typeof msg === 'string' && msg.trim() !== '' ? msg.trim() : '';
                window.alert(
                    m !== ''
                        ? `${m}\n\n---\n送信に失敗しました。もう一度お試しください。\nÉchec d'envoi. Veuillez réessayer.`
                        : '送信に失敗しました。もう一度お試しください。\nÉchec d\'envoi. Veuillez réessayer.',
                );
            };

            try {
                pushOrderSubmitTrace(debugStore, dbg, 'order.submit.request_sent', {
                    trace_id: traceId,
                    client_submit_id: clientSubmitId,
                    url: submitUrl,
                });

                const response = await axios.post(submitUrl, payload, {
                    withCredentials: true,
                    headers: buildPos2JsonHeaders(),
                    validateStatus: () => true,
                });
                durationMs = typeof performance !== 'undefined' ? performance.now() - started : 0;
                httpStatus = response.status;

                pushOrderSubmitTrace(debugStore, dbg, 'order.submit.http_received', {
                    ...correlation,
                    http_status: httpStatus,
                    duration_ms: Math.round(durationMs),
                });

                if (response.status === 201) {
                    outcome = 'success';
                    const ids = response.data?.order_ids;
                    orderId = Array.isArray(ids) && ids.length > 0 ? ids[ids.length - 1] : (response.data?.order_id ?? null);

                    const confirmedSessionId = response.data?.table_session_id ?? null;
                    if (confirmedSessionId != null && Number(confirmedSessionId) > 0) {
                        this.tableSessionId = String(confirmedSessionId);
                        this.pendingTableId = null;
                        sessionUi.patchActiveSessionId(Number(confirmedSessionId));
                    }

                    pushOrderSubmitTrace(debugStore, dbg, 'order.submit.succeeded', {
                        ...correlation,
                        http_status: 201,
                        order_id: orderId,
                        confirmed_session_id: confirmedSessionId,
                        duration_ms: Math.round(durationMs),
                    });
                    try {
                        if (dbg && debugStore) {
                            debugStore.pushApiLog('pos2.sessions.orders', response.status, durationMs);
                        }
                    } catch {
                        // non-fatal
                    }

                    const sid = Number(this.tableSessionId);
                    if (Number.isFinite(sid) && sid > 0) {
                        let getRes = await sessionUi.fetchSessionOrders(sid, { skipLoadingUi: true });
                        if (!getRes.ok) {
                            await new Promise((r) => setTimeout(r, 650));
                            getRes = await sessionUi.fetchSessionOrders(sid, { skipLoadingUi: true });
                        }
                        if (!getRes.ok) {
                            pushOrderSubmitTrace(debugStore, dbg, 'order.submit.revalidate_get_failed', {
                                client_submit_id: clientSubmitId,
                                status: getRes.status ?? null,
                            });
                        }
                    }

                    void sessionUi.fetchTableDashboard();
                    return;
                }

                outcome = 'http_error';
                errMessage = response.data?.message ?? `HTTP ${httpStatus}`;
                const body = response.data;
                const bodyPreview = typeof body === 'object' && body !== null
                    ? { message: body.message ?? null, order_id: body.order_id ?? null }
                    : null;
                pushOrderSubmitTrace(debugStore, dbg, 'order.submit.failed', {
                    ...correlation,
                    http_status: httpStatus,
                    duration_ms: Math.round(durationMs),
                    failure_phase: 'non_201_response',
                    message: errMessage,
                    body_preview: bodyPreview,
                });
                try {
                    if (dbg && debugStore) {
                        debugStore.pushApiLog('pos2.sessions.orders', httpStatus || 0, durationMs);
                    }
                } catch {
                    // non-fatal
                }
                rollback();
                if (httpStatus === 419) {
                    window.alert(
                        'セッションまたは CSRF が無効です。ページを再読み込みしてください。\n'
                        + 'Session ou jeton CSRF invalide. Veuillez recharger la page.',
                    );
                    return;
                }
                alertFailure(errMessage);
            } catch (error) {
                durationMs = typeof performance !== 'undefined' ? performance.now() - started : 0;
                const status = axios.isAxiosError(error) ? (error.response?.status ?? 0) : 0;
                httpStatus = status || null;
                outcome = 'network_error';
                const axBody = axios.isAxiosError(error) && error.response?.data && typeof error.response.data === 'object'
                    ? error.response.data
                    : null;
                const serverFromAxios = typeof axBody?.message === 'string' ? axBody.message.trim() : '';
                errMessage = serverFromAxios !== ''
                    ? serverFromAxios
                    : (error instanceof Error ? error.message : 'network_error');
                pushOrderSubmitTrace(debugStore, dbg, 'order.submit.failed', {
                    ...correlation,
                    http_status: status || null,
                    duration_ms: Math.round(durationMs),
                    failure_phase: 'axios_throw',
                    message: errMessage,
                });
                try {
                    if (dbg && debugStore) {
                        debugStore.pushApiLog('pos2.sessions.orders', status || 0, durationMs);
                    }
                } catch {
                    // non-fatal
                }
                rollback();
                alertFailure(errMessage);
            } finally {
                this.isOrderSubmitting = false;
                if (auditOrderSubmit) {
                    recordLastOrderSubmitAudit(debugStore, dbg, {
                        ...correlation,
                        outcome,
                        http_status: httpStatus,
                        duration_ms: Math.round(durationMs),
                        order_id: orderId,
                        error_message: errMessage,
                    });
                }
            }
        },

        /**
         * 指定行を Laravel へ即送信（1 操作＝1 コミット。楽観 UI・裏 POST/GET・失敗時ロールバックは従来 submitOrder と同一パイプライン）。
         * 成功時は常に clearDraft 済み（this.lines は空）。失敗時は送信直前の this.lines を復元。
         *
         * @param {unknown[]} linesInput - CartLineSnapshot の配列（通常 1 行）
         * @param {boolean} debugEnabled - POS2_DEBUG（Inertia auth.debug）
         * @returns {Promise<{ ok: true, revalidateScheduled?: true } | { ok: false, reason?: string, status?: number, message?: string }>}
         */
        async submitLines(linesInput, debugEnabled = false) {
            const dbg = debugEnabled === true;
            let debugStore = null;
            if (dbg) {
                try {
                    debugStore = useDebugStore();
                } catch {
                    // 調査コードは本ロジックを止めない
                }
            }

            const linesPlain = cloneLinesSnapshot(linesInput);
            if (!Array.isArray(linesPlain) || linesPlain.length === 0) {
                pushOrderSubmitTrace(debugStore, dbg, 'order.submit.skipped', { reason: 'empty' });
                return { ok: false, reason: 'empty' };
            }

            const rollbackLines = cloneLinesSnapshot(this.lines || []);

            const hasSession = this.tableSessionId != null && String(this.tableSessionId).trim() !== '';
            const hasTable = this.pendingTableId != null && Number(this.pendingTableId) > 0;
            if (!hasSession && !hasTable) {
                pushOrderSubmitTrace(debugStore, dbg, 'order.submit.skipped', { reason: 'no_session_and_no_table' });
                return { ok: false, reason: 'no_session' };
            }

            if (this.isOrderSubmitting) {
                pushOrderSubmitTrace(debugStore, dbg, 'order.submit.skipped', { reason: 'busy' });
                return { ok: false, reason: 'busy' };
            }
            this.isOrderSubmitting = true;

            let traceId = null;
            if (dbg && debugStore) {
                try {
                    traceId = debugStore.nextTraceId('order-submit');
                } catch {
                    // non-fatal
                }
            }

            const clientSubmitId = generateUUID();
            const lineCountBefore = linesPlain.length;
            const totalMinorBefore = linesPlain.reduce(
                (s, l) => s + (Number(l?.total_unit_price_minor ?? 0) * Math.max(1, Number(l?.qty ?? 1))),
                0,
            );
            const draftKeySnapshot = this.key;

            const masterStore = useMasterStore();
            const styleViolations = [];
            for (const line of linesPlain) {
                const pid = String(line?.product_id ?? '');
                const item = masterStore.menuItems.find((m) => String(m.id) === pid);
                const styleRequired = item?.options_payload?.rules?.style_required === true;
                if (!styleRequired) {
                    continue;
                }
                const sid = line?.selected_option_snapshot?.id;
                if (sid == null || String(sid).trim() === '') {
                    styleViolations.push(String(line?.display_full_name ?? line?.name ?? pid));
                }
            }
            if (styleViolations.length > 0) {
                this.isOrderSubmitting = false;
                const msg = `スタイル必須の行にスタイル ID がありません。モーダルから追加し直してください: ${styleViolations.join(' / ')}`;
                pushOrderSubmitTrace(debugStore, dbg, 'order.submit.skipped', {
                    reason: 'style_required_snapshot_missing',
                    violations: styleViolations,
                });
                return { ok: false, status: 422, message: msg };
            }

            const submitUrl = hasSession
                ? `/pos2/api/sessions/${encodeURIComponent(String(this.tableSessionId))}/orders`
                : `/pos2/api/tables/${encodeURIComponent(String(this.pendingTableId))}/orders`;

            const payload = {
                lines: linesPlain,
                client_submit_id: clientSubmitId,
            };

            const correlation = {
                trace_id: traceId,
                client_submit_id: clientSubmitId,
                line_count: lineCountBefore,
                total_minor: totalMinorBefore,
                table_session_id: this.tableSessionId,
                shop_id: this.shopId,
                draft_key: draftKeySnapshot,
            };

            pushOrderSubmitTrace(debugStore, dbg, 'order.submit.started', correlation);

            const sessionUi = usePos2SessionUiStore();
            sessionUi.appendOptimisticStaffSubmit({ clientSubmitId, lines: linesPlain });
            this.clearDraft({ debugEnabled: dbg, traceId });

            const started = typeof performance !== 'undefined' ? performance.now() : 0;

            const bgCtx = {
                clientSubmitId,
                linesPlain,
                rollbackLines,
                payload,
                submitUrl,
                traceId,
                dbg,
                debugStore,
                correlation,
                started,
            };
            setTimeout(() => {
                void this._runOptimisticSubmitBackground(bgCtx).catch(() => {
                    this.isOrderSubmitting = false;
                });
            }, 0);

            return { ok: true, revalidateScheduled: true };
        },

        /** LocalStorage に現在のドラフトを書き込む（内部用）。 */
        _persist() {
            const key = this.key;
            if (!key) return;
            try {
                localStorage.setItem(key, JSON.stringify({
                    schema_version: DRAFT_SCHEMA_VERSION,
                    shop_id: this.shopId,
                    table_session_id: this.tableSessionId,
                    lines: this.lines,
                    saved_at: new Date().toISOString(),
                }));
            } catch (e) {
                // LocalStorage フル等でも本処理を止めない
                console.warn('[pos2] draft persist failed:', e);
            }
        },

        safeTrace(context, event, payload) {
            if (context?.debugEnabled !== true) return;
            try {
                const debugStore = useDebugStore();
                debugStore.pushTrace(event, payload);
            } catch {
                // 調査コードは本ロジックを止めない
            }
        },
    },
});
