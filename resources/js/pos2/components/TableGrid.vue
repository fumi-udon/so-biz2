<script setup>
import { computed } from 'vue';
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

function tableNameNormalized(table) {
    return String(table?.name ?? '').trim();
}

/** ST で始まる卓（大文字小文字無視） */
function isStaffTable(table) {
    return tableNameNormalized(table).toUpperCase().startsWith('ST');
}

/** TK で始まる卓 — ST 判定後に使う（T 単独より先に TK を見る必要は名前上 TK が T で始まるため） */
function isTakeoutTable(table) {
    return tableNameNormalized(table).toUpperCase().startsWith('TK');
}

/** ST / TK 以外はすべて Client（T01 以外・手入力名もここにフォールバック） */
const staffTables = computed(() => props.tables.filter(isStaffTable));

const takeoutTables = computed(() => props.tables.filter(isTakeoutTable));

const clientTables = computed(() =>
    props.tables.filter((t) => !isStaffTable(t) && !isTakeoutTable(t)),
);

/**
 * 描画順: Client → Staff Meal → Takeout。
 * title が無いセクションは区切り線なし（Section 1）。
 */
const gridSections = computed(() => {
    const out = [];
    if (clientTables.value.length > 0) {
        out.push({
            id: 'client',
            title: null,
            tables: clientTables.value,
            gridClass: 'grid min-h-0 auto-rows-fr grid-cols-3 gap-3',
            mini: false,
        });
    }

    if (takeoutTables.value.length > 0) {
        out.push({
            id: 'takeout',
            title: 'Takeout',
            tables: takeoutTables.value,
            gridClass: 'grid min-h-0 auto-rows-fr grid-cols-5 gap-2',
            mini: true,
        });
    }

        if (staffTables.value.length > 0) {
        out.push({
            id: 'staff',
            title: 'Staff Meal',
            tables: staffTables.value,
            gridClass: 'grid min-h-0 auto-rows-fr grid-cols-5 gap-2',
            mini: true,
        });
    }
    return out;
});

function tileFor(tableId) {
    const raw = props.tilesByTableId?.[Number(tableId)];
    return raw && typeof raw === 'object' ? raw : null;
}

/** 選択リング・枠色は共通。サイズ系のみ isMini で切替 */
function tileButtonClass(tableId, isMini) {
    const selected = props.selectedTableId === Number(tableId);
    const minH = isMini ? 'min-h-16' : 'min-h-24';
    const base = `relative ${minH} overflow-hidden rounded-2xl border-2 px-0 py-0 text-left transition active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900`;
    if (selected) {
        return `${base} border-amber-500 ring-4 ring-amber-400/50 focus-visible:ring-amber-400`;
    }
    return `${base} border-slate-600 hover:border-slate-400 focus-visible:ring-cyan-500`;
}

function tileInnerClass(isMini) {
    return isMini
        ? 'flex h-full min-h-16 flex-col justify-between p-1.5'
        : 'flex h-full min-h-24 flex-col justify-between p-3';
}

function tileNameClass(isMini) {
    return isMini
        ? 'text-[11px] font-black leading-none tracking-tight'
        : 'text-sm font-black leading-none tracking-tight';
}

function tileCommandeClass(isMini) {
    return isMini
        ? 'mt-1 text-[10px] font-semibold leading-tight opacity-95 dark:opacity-100'
        : 'mt-1.5 text-[11px] font-semibold leading-tight opacity-95 dark:opacity-100';
}

function tileTotalClass(isMini) {
    return isMini
        ? 'text-right text-[10px] font-bold tabular-nums opacity-95 dark:opacity-100'
        : 'text-right text-xs font-bold tabular-nums opacity-95 dark:opacity-100';
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

        <template v-if="tables.length > 0">
            <template
                v-for="sec in gridSections"
                :key="sec.id"
            >
                <div
                    v-if="sec.title"
                    class="my-4 border-t border-slate-700 pt-2 dark:border-slate-600"
                >
                    <p class="text-xs font-semibold tracking-wide text-slate-300 dark:text-slate-200">
                        {{ sec.title }}
                    </p>
                </div>
                <div
                    :class="sec.gridClass"
                    role="list"
                >
                    <button
                        v-for="table in sec.tables"
                        :key="table.id"
                        type="button"
                        role="listitem"
                        class="touch-manipulation"
                        :class="tileButtonClass(table.id, sec.mini)"
                        :aria-pressed="selectedTableId === Number(table.id)"
                        :aria-label="`Table ${table.name}`"
                        @click="emit('select', table.id)"
                    >
                        <div
                            :class="[
                                tileInnerClass(sec.mini),
                                tileFor(table.id)
                                    ? tileSurfaceInnerClasses(tileFor(table.id).uiStatus)
                                    : 'bg-slate-950/70 text-slate-100 dark:bg-slate-950/80 dark:text-slate-100',
                            ]"
                        >
                            <div>
                                <p :class="tileNameClass(sec.mini)">
                                    {{ table.name }}
                                </p>
                                <p
                                    v-if="tileFor(table.id)"
                                    :class="tileCommandeClass(sec.mini)"
                                >
                                    {{ Number(tileFor(table.id).relevantPosOrderCount ?? 0) }} cmd
                                </p>
                            </div>
                            <p
                                v-if="sessionTotalLabel(tileFor(table.id))"
                                :class="tileTotalClass(sec.mini)"
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
            </template>
        </template>
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
