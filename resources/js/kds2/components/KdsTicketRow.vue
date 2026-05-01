<template>
    <button
        type="button"
        class="flex w-full items-start rounded-lg px-4 py-3 text-left font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-950"
        :class="rowClass"
        @click="$emit('serve', ticket.id, ticket.rev, ticket.is_last, modalLabel)"
    >
        <div class="flex min-w-0 flex-1 flex-col items-start gap-1 text-left">
            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <span class="text-base font-bold leading-tight">{{ mainLine }}</span>
                <span v-if="qtyGt1" class="shrink-0 text-base font-bold">×{{ ticket.qty }}</span>
            </div>
            <span
                v-if="subLine"
                class="w-full break-words text-xs font-normal leading-snug"
                :class="subRowTextClass"
            >
                {{ subLine }}
            </span>
        </div>
    </button>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    ticket: {
        type: Object,
        required: true,
    },
});

defineEmits(['serve']);

const nameRaw = computed(() => String(props.ticket?.name ?? ''));

/** メイン行: name の先頭行（改行より前） */
const mainLine = computed(() => {
    const first = nameRaw.value.split(/\r?\n/)[0] ?? '';
    return first.trim();
});

/** name の改行以降（options が無いときのフォールバック） */
const subFromNameNewlines = computed(() => {
    const parts = nameRaw.value.split(/\r?\n/).map((s) => s.trim()).filter((s) => s !== '');
    if (parts.length < 2) {
        return '';
    }
    return parts.slice(1).join(' ');
});

/** サブ行: options を優先、無ければ name の2行目以降 */
const subLine = computed(() => {
    const opt = String(props.ticket?.options ?? '').trim();
    if (opt !== '') {
        return opt;
    }
    return subFromNameNewlines.value;
});

const subRowTextClass = computed(() => {
    const s = props.ticket?.status;
    if (s === 'confirmed' || s === 'cooking') {
        return 'text-rose-200';
    }
    return 'text-slate-400';
});

/** 確認モーダル用 */
const modalLabel = computed(() => {
    const m = mainLine.value;
    const sub = subLine.value;
    if (sub !== '') {
        return m !== '' ? `${m} / ${sub}` : sub;
    }
    return m;
});

const qtyGt1 = computed(() => Number(props.ticket?.qty) > 1);

const rowClass = computed(() => {
    const s = props.ticket?.status;
    if (s === 'served') {
        return 'bg-slate-700 text-slate-300';
    }
    if (s === 'confirmed' || s === 'cooking') {
        return 'bg-rose-900 text-white';
    }
    return 'bg-slate-800 text-slate-200';
});
</script>
