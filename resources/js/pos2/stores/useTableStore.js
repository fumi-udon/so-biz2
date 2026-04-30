import { defineStore } from 'pinia';
import { useDebugStore } from './useDebugStore';

export const useTableStore = defineStore('pos2Table', {
    state: () => ({
        selectedTableId: null,
    }),

    getters: {
        hasSelection: (state) => state.selectedTableId !== null,
    },

    actions: {
        /**
         * 0ms で選択テーブルを切り替える。
         * 調査コードは try/catch で完全隔離する（本ロジック非干渉）。
         */
        selectTable(tableId, context = {}) {
            const prev = this.selectedTableId;
            this.selectedTableId = Number(tableId);

            if (context.debugEnabled === true) {
                try {
                    const debugStore = useDebugStore();
                    debugStore.pushTrace('table.select.changed', {
                        traceId: context.traceId ?? null,
                        fromTableId: prev,
                        toTableId: this.selectedTableId,
                        cartCount: Number(context.cartCount ?? 0),
                    });
                } catch {
                    // 調査処理は握りつぶして本ロジックを止めない
                }
            }
        },

        clearSelection(context = {}) {
            const prev = this.selectedTableId;
            this.selectedTableId = null;

            if (context.debugEnabled === true) {
                try {
                    const debugStore = useDebugStore();
                    debugStore.pushTrace('table.select.cleared', {
                        traceId: context.traceId ?? null,
                        fromTableId: prev,
                    });
                } catch {
                    // no-op
                }
            }
        },
    },
});
