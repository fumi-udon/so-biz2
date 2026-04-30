<script setup>
/**
 * SYNAPSE 右ペイン: 確定注文一覧（テーブル・ゲスト・スタッフ送信の合算表示）。
 */
import { computed } from 'vue';
import { formatDT } from '../utils/currency';

const props = defineProps({
    sessionOrdersPayload: {
        type: Object,
        default: null,
    },
    loadingConfirmed: {
        type: Boolean,
        default: false,
    },
    hasUnackedPlaced: {
        type: Boolean,
        default: false,
    },
    sendKdsBusy: {
        type: Boolean,
        default: false,
    },
    /** true のとき上部の Send To KDS ストリップを出さない（親ツールバーで REÇU する場合） */
    hideKdsBanner: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['send-kds']);

const flatLines = computed(() => {
    const orders = props.sessionOrdersPayload?.orders;
    if (!Array.isArray(orders)) return [];
    const out = [];
    for (const o of orders) {
        const ob = o?.ordered_by ?? 'staff';
        const lines = o?.lines;
        if (!Array.isArray(lines)) continue;
        for (const ln of lines) {
            out.push({
                ...ln,
                order_id: o.id,
                ordered_by: ln.ordered_by ?? ob,
            });
        }
    }
    return out;
});

/** 行背景: KDS 送信前は薄い赤系、送信後はニュートラル（§10 右ペイン） */
function lineRowClass(isUnsent) {
    return isUnsent
        ? 'bg-rose-950/25 dark:bg-rose-950/30'
        : 'bg-slate-950/40 dark:bg-slate-900/50';
}

/** KDS 前: 薄い赤ラベル（Recu 前の placed 行） */
function kdsPhaseBadgeClass(isUnsent) {
    return isUnsent
        ? 'border border-rose-400/35 bg-rose-400/15 text-rose-100 dark:text-rose-50'
        : 'border border-emerald-500/30 bg-emerald-500/12 text-emerald-100 dark:text-emerald-50';
}

function kdsPhaseLabel(isUnsent) {
    return isUnsent ? 'KDS前' : 'KDS送信済';
}

function sourceBadgeClass(orderedBy) {
    return orderedBy === 'guest'
        ? 'bg-amber-500/20 text-amber-100 dark:text-amber-50'
        : 'bg-cyan-500/20 text-cyan-100 dark:text-cyan-50';
}

/** 1 行目: 商品名（API の product_name / 従来 display_name） */
function lineProductLabel(ln) {
    const p = String(ln?.product_name ?? '').trim();
    if (p !== '') {
        return p;
    }
    return String(ln?.display_name ?? '').trim() || '—';
}

/** スタイル名（必須セレクト等）— 商品名の直後に続ける */
function lineStyleLabel(ln) {
    const s = ln?.style_name;
    if (s == null) {
        return '';
    }
    const t = String(s).trim();
    return t === '' ? '' : t;
}

/** 2 行目: トッピング（カンマ区切り） */
function lineToppingsCsv(ln) {
    if (Array.isArray(ln?.topping_names) && ln.topping_names.length > 0) {
        return ln.topping_names.map((x) => String(x).trim()).filter(Boolean).join(', ');
    }
    if (Array.isArray(ln?.topping_snapshots) && ln.topping_snapshots.length > 0) {
        return ln.topping_snapshots
            .map((t) => (t && t.name != null ? String(t.name).trim() : ''))
            .filter(Boolean)
            .join(', ');
    }
    return '';
}
</script>

<template>
    <div
        class="flex h-full min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-slate-700 bg-slate-950/70 text-slate-100"
    >
        <!-- Send to KDS（旧POS Recu と同一条件: PosOrder placed 存在）— 分割レイアウト時は親が REÇU ボタンを表示 -->
        <div
            v-if="hasUnackedPlaced && !hideKdsBanner"
            class="shrink-0 border-b border-rose-900/50 bg-rose-950/40 px-3 py-2"
        >
            <button
                type="button"
                class="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border-2 border-rose-400 bg-rose-600 px-3 py-2.5 text-sm font-black text-white shadow-lg animate-pulse hover:bg-rose-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-rose-300"
                :disabled="sendKdsBusy"
                @click="emit('send-kds')"
            >
                Send To KDS / Recu
            </button>
        </div>

        <!-- セッション注文一覧（ゲスト・Add to Table 同一リスト。行ごとに KDS 前/送信済） -->
        <div class="min-h-0 flex-1 overflow-y-auto">
            <div class="border-b border-slate-700 bg-slate-950/95 px-3 py-2">
                <p class="text-xs font-semibold tracking-widest text-slate-300 dark:text-slate-200">
                    TABLE · 注文一覧
                </p>
                <p class="mt-0.5 text-[10px] leading-snug text-slate-500 dark:text-slate-400">
                    ゲスト受信とスタッフ送信はここに合算表示。Recu 後は各行「KDS送信済」。
                </p>
            </div>
            <div v-if="loadingConfirmed" class="px-3 py-6 text-sm text-slate-500">
                読み込み中…
            </div>
            <ul v-else-if="flatLines.length" class="divide-y divide-slate-800/90">
                <li
                    v-for="ln in flatLines"
                    :key="`ln-${ln.id}`"
                    class="px-2.5 py-1 text-[11px] leading-snug sm:px-3 sm:text-xs"
                    :class="lineRowClass(!!ln.is_unsent)"
                >
                    <div class="flex items-start gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="grid w-full min-w-0 grid-cols-[auto_1fr_auto] items-baseline gap-x-1.5 gap-y-0">
                                <span class="shrink-0 tabular-nums font-semibold text-slate-200 dark:text-slate-200">
                                    {{ Math.max(1, Number(ln.qty ?? 1)) }} x
                                </span>
                                <span class="min-w-0 font-medium leading-snug text-white dark:text-white">
                                    <span class="break-words">{{ lineProductLabel(ln) }}</span>
                                    <template v-if="lineStyleLabel(ln)">
                                        <span class="font-normal text-slate-300 dark:text-slate-300">
                                            {{ lineStyleLabel(ln) }}
                                        </span>
                                    </template>
                                </span>
                                <span
                                    class="shrink-0 whitespace-nowrap text-right text-[10px] font-medium tabular-nums text-slate-500 dark:text-slate-400"
                                >
                                    {{ formatDT(Number(ln.line_total_minor ?? 0)) }}
                                </span>
                                <span
                                    v-if="lineToppingsCsv(ln)"
                                    class="col-start-2 col-span-2 mt-0.5 min-w-0 break-words text-[10px] leading-snug text-slate-500 dark:text-slate-400"
                                >
                                    {{ lineToppingsCsv(ln) }}
                                </span>
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-0.5 self-start pt-0.5">
                            <span
                                class="rounded px-1 py-px text-[9px] font-bold leading-none"
                                :class="kdsPhaseBadgeClass(!!ln.is_unsent)"
                            >
                                {{ kdsPhaseLabel(!!ln.is_unsent) }}
                            </span>
                            <span
                                class="rounded px-1 py-px text-[9px] font-semibold uppercase leading-none"
                                :class="sourceBadgeClass(ln.ordered_by)"
                            >
                                {{ ln.ordered_by === 'guest' ? 'guest' : 'staff' }}
                            </span>
                        </div>
                    </div>
                </li>
            </ul>
            <p v-else class="px-3 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                この卓の注文行はまだありません
            </p>
        </div>
    </div>
</template>
