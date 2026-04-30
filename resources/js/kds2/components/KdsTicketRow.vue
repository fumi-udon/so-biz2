<template>
    <button
        type="button"
        class="w-full rounded-lg px-4 py-3 text-left font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-950"
        :class="rowClass"
        @click="$emit('serve', ticket.id, ticket.rev, ticket.is_last, ticket.name)"
    >
        <span>{{ ticket.name }}</span>
        <span v-if="Number(ticket.qty) > 1" class="font-semibold">
            ×{{ ticket.qty }}
        </span>
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
