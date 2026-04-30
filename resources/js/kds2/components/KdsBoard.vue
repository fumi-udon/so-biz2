<template>
    <div class="flex min-h-0 flex-1 flex-col bg-gray-950">
        <KdsFilterBar :shop-id="shopId" :on-sync-master="onSyncMaster" />
        <div
            v-if="queued > 0"
            class="flex items-center gap-2 border-b border-slate-700 bg-slate-900 px-4 py-2"
        >
            <span
                class="inline-flex items-center rounded-full border border-amber-600 bg-amber-900/90 px-3 py-1 text-sm font-medium text-amber-100"
            >
                待機中: {{ queued }}件
            </span>
        </div>
        <div class="grid min-h-0 flex-1 grid-cols-3 gap-4 overflow-auto p-4">
            <KdsBatchColumn
                v-for="batch in visibleBatches"
                :key="batch.key ?? batch.table"
                :batch="batch"
                :shop-id="shopId"
                @serve="forwardServe"
            />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import KdsBatchColumn from './KdsBatchColumn.vue';
import KdsFilterBar from './KdsFilterBar.vue';

const props = defineProps({
    batches: {
        type: Array,
        default: () => [],
    },
    queued: {
        type: Number,
        default: 0,
    },
    shopId: {
        type: Number,
        required: true,
    },
    onSyncMaster: {
        type: Function,
        default: undefined,
    },
});

const emit = defineEmits(['serve']);

const visibleBatches = computed(() => {
    const list = Array.isArray(props.batches) ? props.batches : [];

    return list.slice(0, 3);
});

function forwardServe(id, rev, isLast, name) {
    emit('serve', id, rev, isLast, name);
}
</script>
