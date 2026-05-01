<script setup>
/**
 * ProductGrid.vue
 * 商品カードのグリッド表示。
 * - オプションあり商品 → ConfigModal を開くイベントを emit
 * - 単品 → 親で submitLines（即送信）するためのイベントを emit
 */
import { formatDT } from '../utils/currency';

const props = defineProps({
    items: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits([
    /** オプションあり商品タップ時。payload: masterItem */
    'open-modal',
    /** 単品タップ時（親が即送信）。payload: masterItem */
    'add-simple',
]);

/**
 * モーダル必須か（スタイル必須ルールのみ・styles 空、など即時追加で壊れるケースを含む）。
 * @param {object} item
 * @returns {boolean}
 */
function requiresConfigModal(item) {
    const p = item?.options_payload;
    if (!p || typeof p !== 'object') return false;
    if (p.rules?.style_required === true) {
        return true;
    }
    return (Array.isArray(p.styles) && p.styles.length > 0)
        || (Array.isArray(p.toppings) && p.toppings.length > 0);
}

function onTapItem(item) {
    if (requiresConfigModal(item)) {
        emit('open-modal', item);
    } else {
        emit('add-simple', item);
    }
}
</script>

<template>
    <div class="grid grid-cols-3 gap-2">
        <button
            v-for="item in items"
            :key="item.id"
            type="button"
            class="relative flex min-h-20 flex-col justify-between rounded-2xl border border-slate-700 bg-slate-900/70 p-2 text-left transition hover:border-slate-500 hover:bg-slate-800/80 active:scale-95"
            @click="onTapItem(item)"
        >
            <!-- 商品名 -->
            <p class="line-clamp-2 text-[13px] font-semibold leading-snug text-white">
                {{ item.name }}
            </p>

            <!-- 価格 + オプションバッジ -->
            <div class="mt-1.5 flex items-end justify-between">
                <span class="text-[13px] font-bold text-cyan-300">
                    {{ formatDT(item.from_price_minor ?? item.price_minor ?? item.base_price_minor ?? 0) }}
                </span>
                <span
                    v-if="requiresConfigModal(item)"
                    class="rounded bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-medium text-amber-300"
                >
                    OPTIONS
                </span>
            </div>
        </button>

        <div
            v-if="items.length === 0"
            class="col-span-3 rounded-xl border border-dashed border-slate-700 py-8 text-center text-sm text-slate-500"
        >
            商品がありません
        </div>
    </div>
</template>
