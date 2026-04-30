import { defineStore } from 'pinia';

export const useDebugStore = defineStore('pos2Debug', {
    state: () => ({
        lastBootstrapTime: null,
        localStorageSize: 0,
        apiLogs: [],
        traceLogs: [],
        generatedAt: null,
        selectedTableId: null,
        cartItemsCount: 0,
        traceSequence: 0,
        /** 直近の注文送信試行（要約。DebugPanel の Cart タブ用） */
        lastOrderSubmit: null,
    }),

    getters: {
        apiStatus(state) {
            if (state.apiLogs.length === 0) {
                return 'idle';
            }

            const latest = state.apiLogs[0];
            if (latest.status >= 200 && latest.status < 400) {
                return 'ok';
            }

            return 'error';
        },
    },

    actions: {
        recordBootstrap(durationMs, generatedAt) {
            this.lastBootstrapTime = {
                at: new Date().toISOString(),
                durationMs: Math.max(0, Math.round(durationMs)),
            };
            this.generatedAt = generatedAt ?? null;
        },

        pushApiLog(name, status, durationMs) {
            this.apiLogs.unshift({
                name,
                status,
                durationMs: Math.max(0, Math.round(durationMs)),
                at: new Date().toISOString(),
            });
            this.apiLogs = this.apiLogs.slice(0, 5);
        },

        nextTraceId(prefix = 'op') {
            this.traceSequence += 1;
            return `${prefix}-${Date.now()}-${this.traceSequence}`;
        },

        pushTrace(event, payload = {}) {
            this.traceLogs.unshift({
                at: new Date().toISOString(),
                event,
                payload,
            });
            this.traceLogs = this.traceLogs.slice(0, 100);
        },

        refreshLocalStorageSize() {
            let bytes = 0;
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (!key) continue;
                const value = localStorage.getItem(key) ?? '';
                bytes += key.length + value.length;
            }
            this.localStorageSize = bytes;
        },

        setUiSnapshot(selectedTableId, cartItemsCount) {
            this.selectedTableId = selectedTableId ?? null;
            this.cartItemsCount = Math.max(0, Number(cartItemsCount ?? 0));
        },

        clearTraceLogs() {
            this.traceLogs = [];
        },

        /** localStorage 掃除後: メモリ上の調査用状態のみ初期化（本番ログとは無関係）。 */
        resetDiagnosticsAfterStoragePurge() {
            this.clearTraceLogs();
            this.lastOrderSubmit = null;
            this.lastBootstrapTime = null;
            this.apiLogs = [];
        },

        /**
         * 注文送信の調査用スナップショット（PII 最小・行の全文は載せない）。
         * @param {object} snapshot
         */
        recordLastOrderSubmit(snapshot) {
            this.lastOrderSubmit = {
                at: new Date().toISOString(),
                ...snapshot,
            };
        },
    },
});
