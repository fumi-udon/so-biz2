<script setup>
/**
 * SYNAPSE モニタリング: 卓サマリー（タイル集約＋セッション API の要約）。
 */
import { computed } from 'vue';
import { formatDT } from '../utils/currency';

const props = defineProps({
    restaurantTableId: {
        type: Number,
        required: true,
    },
    /** TableTileAggregate の toArray() 互換 1 件（無ければ null） */
    tile: {
        type: Object,
        default: null,
    },
    /** GET /pos2/api/sessions/:id/orders の JSON（無ければ null） */
    sessionOrders: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const totalMinor = computed(() => {
    if (props.tile && props.tile.sessionTotalMinor != null) {
        return Number(props.tile.sessionTotalMinor);
    }
    const orders = props.sessionOrders?.orders;
    if (!Array.isArray(orders)) return 0;
    return orders.reduce((s, o) => s + Number(o?.total_minor ?? 0), 0);
});

const orderCount = computed(() => {
    const n = props.tile?.relevantPosOrderCount;
    if (n != null) return Number(n);
    const orders = props.sessionOrders?.orders;
    return Array.isArray(orders) ? orders.length : 0;
});

const uiStatus = computed(() => String(props.tile?.uiStatus ?? 'free'));
</script>

<template>
    <section
        class="flex min-h-[280px] flex-col justify-between rounded-3xl border border-slate-700/70 bg-slate-900/70 p-5 text-slate-100"
        aria-labelledby="pos2-table-summary-heading"
    >
        <div>
            <p id="pos2-table-summary-heading" class="text-xs font-semibold tracking-[0.2em] text-cyan-300">
                TABLE
            </p>
            <p class="mt-2 text-4xl font-black tabular-nums text-white">
                #{{ restaurantTableId }}
            </p>
            <p class="mt-3 text-sm text-slate-400">
                Status: <span class="font-mono text-cyan-200">{{ uiStatus }}</span>
            </p>
        </div>

        <div v-if="loading" class="mt-6 text-sm text-slate-400">
            読み込み中…
        </div>
        <div v-else class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-600 bg-slate-950/60 p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total (session)</p>
                <p class="mt-1 text-3xl font-bold tabular-nums text-white">
                    {{ formatDT(totalMinor) }}
                </p>
            </div>
            <div class="rounded-2xl border border-slate-600 bg-slate-950/60 p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Orders</p>
                <p class="mt-1 text-3xl font-bold tabular-nums text-white">
                    {{ orderCount }}
                </p>
            </div>
        </div>
        <p class="mt-4 text-xs text-slate-500">
            人数・滞在時間の詳細は次フェーズで API 拡張予定。
        </p>
    </section>
</template>
