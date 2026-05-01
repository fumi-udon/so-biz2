<script setup>
import { tileSurfaceInnerClasses } from '../utils/tileUiClasses';
import { formatDT } from '../utils/currency';

const props = defineProps({
    tables: {
        type: Array,
        required: true,
    },
    selectedTableId: {
        type: [Number, null],
        default: null,
    },
    debugEnabled: {
        type: Boolean,
        default: false,
    },
    hasDraftForTable: {
        type: Function,
        required: true,
    },
    /** restaurant_table_id → dashboard tile（無ければ空オブジェクト） */
    tilesByTableId: {
        type: Object,
        default: () => ({}),
    },
    /**
     * `split`: 左カラム常駐用。ヘッダーを薄くし、タップ領域を広げる。
     * `standalone`: 卓未選択時の全幅表示。
     */
    layoutVariant: {
        type: String,
        default: 'standalone',
        validator: (v) => v === 'standalone' || v === 'split',
    },
});

const emit = defineEmits(['select']);

function tileFor(tableId) {
    const raw = props.tilesByTableId?.[Number(tableId)];
    return raw && typeof raw === 'object' ? raw : null;
}

function tileButtonClass(tableId) {
    const selected = props.selectedTableId === Number(tableId);
    const base = 'relative min-h-[5.75rem] overflow-hidden rounded-2xl border-2 px-0 py-0 text-left transition active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900';
    if (selected) {
        return `${base} border-amber-500 ring-4 ring-amber-400/50 focus-visible:ring-amber-400`;
    }
    return `${base} border-slate-600 hover:border-slate-400 focus-visible:ring-cyan-500`;
}

function sessionTotalLabel(tile) {
    if (tile == null || tile.sessionTotalMinor == null) return null;
    const n = Number(tile.sessionTotalMinor);
    if (!Number.isFinite(n) || n <= 0) return null;
    return formatDT(n);
}
</script>

<template>
    <section
        class="flex h-full min-h-0 w-full flex-col rounded-2xl border border-slate-700/80 bg-slate-900/80 shadow-xl shadow-black/25 dark:bg-slate-900/90"
        :class="layoutVariant === 'split' ? 'p-3 md:p-4' : 'p-4 sm:p-5'"
    >
        <header
            class="shrink-0"
            :class="layoutVariant === 'split' ? 'mb-3' : 'mb-4'"
        >
            <p class="text-[10px] font-semibold tracking-[0.25em] text-cyan-300 dark:text-cyan-200">
                SÖYA POS2
            </p>
        </header>

        <!-- タブレット現場向け: 常に 3 列（視認性・親指操作） -->
        <div
            v-if="tables.length > 0"
            class="grid min-h-0 auto-rows-fr grid-cols-3 gap-2.5 sm:gap-3"
            role="list"
        >
            <button
                v-for="table in tables"
                :key="table.id"
                type="button"
                role="listitem"
                class="touch-manipulation"
                :class="tileButtonClass(table.id)"
                :aria-pressed="selectedTableId === Number(table.id)"
                :aria-label="`Table ${table.name}`"
                @click="emit('select', table.id)"
            >
                <div
                    class="flex h-full min-h-[5.75rem] flex-col justify-between px-2.5 py-2.5"
                    :class="tileFor(table.id) ? tileSurfaceInnerClasses(tileFor(table.id).uiStatus) : 'bg-slate-950/70 text-slate-100 dark:bg-slate-950/80 dark:text-slate-100'"
                >
                    <div>
                        <p class="text-sm font-black leading-none tracking-tight">
                            {{ table.name }}
                        </p>
                        <p
                            v-if="tileFor(table.id)"
                            class="mt-1.5 text-[11px] font-semibold leading-tight opacity-95 dark:opacity-100"
                        >
                            {{ Number(tileFor(table.id).relevantPosOrderCount ?? 0) }}
                            commande{{ Number(tileFor(table.id).relevantPosOrderCount ?? 0) === 1 ? '' : 's' }}
                        </p>
                    </div>
                    <p
                        v-if="sessionTotalLabel(tileFor(table.id))"
                        class="text-right text-xs font-bold tabular-nums opacity-95 dark:opacity-100"
                    >
                        {{ sessionTotalLabel(tileFor(table.id)) }}
                    </p>
                </div>

                <span
                    v-if="debugEnabled && hasDraftForTable(table.id)"
                    class="absolute bottom-1 right-1 rounded bg-amber-500/30 px-1 py-0.5 text-[9px] font-bold text-amber-950 dark:text-amber-100"
                >
                    draft
                </span>
            </button>
        </div>
        <div
            v-else
            class="flex min-h-[12rem] flex-1 flex-col items-center justify-center rounded-2xl border border-dashed border-rose-500/50 bg-rose-900/20 px-4 py-10 text-center"
        >
            <p class="text-sm font-bold text-rose-400">
                restaurant_tableにレコードが見つかりません
            </p>
        </div>
    </section>
</template>
