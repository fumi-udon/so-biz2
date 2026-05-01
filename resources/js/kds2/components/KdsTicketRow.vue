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
import { useKdsDictStore } from '../stores/useKdsDictStore';

const props = defineProps({
    ticket: {
        type: Object,
        required: true,
    },
    shopId: {
        type: Number,
        default: 0,
    },
});

const kdsDictStore = useKdsDictStore();

/** {@see App\Support\KdsDictionarySetting::normalizeMatchKey} と同等（大文字小文字無視・空白除去） */
function normalizeKdsDictMatchKey(label) {
    return String(label)
        .trim()
        .replace(/\s+/gu, '')
        .toLowerCase()
}

function dictMap() {
    void kdsDictStore.revision;
    try {
        const parsed = JSON.parse(
            localStorage.getItem(`kds2_dict_${props.shopId}`) || '{}',
        );
        if (
            parsed !== null &&
            typeof parsed === 'object' &&
            !Array.isArray(parsed)
        ) {
            return parsed;
        }
    } catch {
        /* ignore */
    }
    return {};
}

function translateOptionsCsv(raw) {
    const dict = dictMap();
    return raw
        .split(',')
        .map((item) => {
            const trimmed = item.trim();
            const key = normalizeKdsDictMatchKey(trimmed);
            return dict[key] !== undefined ? dict[key] : trimmed;
        })
        .join(', ');
}

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
        return translateOptionsCsv(opt);
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
