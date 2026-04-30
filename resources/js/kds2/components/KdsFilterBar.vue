<template>
    <div
        class="flex w-full flex-wrap items-center gap-6 border-b border-slate-700 bg-slate-900 px-4 py-3"
        role="group"
        aria-label="Kitchen / Hall filter"
    >
        <div class="flex flex-wrap items-center gap-6">
            <label class="flex cursor-pointer items-center gap-2 select-none">
                <input
                    type="checkbox"
                    class="size-5 shrink-0 cursor-pointer rounded border-slate-500 bg-slate-800 text-rose-600 accent-rose-500 focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 focus:ring-offset-slate-900"
                    :checked="showKitchen"
                    @change="filter.toggle('showKitchen', props.shopId)"
                />
                <span class="text-base font-medium text-white">Kitchen</span>
            </label>
            <label class="flex cursor-pointer items-center gap-2 select-none">
                <input
                    type="checkbox"
                    class="size-5 shrink-0 cursor-pointer rounded border-slate-500 bg-slate-800 text-rose-600 accent-rose-500 focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 focus:ring-offset-slate-900"
                    :checked="showHall"
                    @change="filter.toggle('showHall', props.shopId)"
                />
                <span class="text-base font-medium text-white">Hall</span>
            </label>
        </div>
        <button
            type="button"
            class="ml-auto shrink-0 rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-slate-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="syncing"
            @click="handleSync"
        >
            {{ syncing ? '同期中...' : 'マスタ同期' }}
        </button>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { storeToRefs } from 'pinia';
import { useFilterStore } from '../stores/useFilterStore';

const props = defineProps({
    shopId: {
        type: Number,
        required: true,
    },
    onSyncMaster: {
        type: Function,
        default: undefined,
    },
});

const filter = useFilterStore();
const { showKitchen, showHall } = storeToRefs(filter);

const syncing = ref(false);

async function handleSync() {
    if (!props.onSyncMaster || syncing.value) {
        return;
    }
    syncing.value = true;
    try {
        await props.onSyncMaster();
    } finally {
        syncing.value = false;
    }
}
</script>
