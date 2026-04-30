<template>
    <section
        class="flex flex-col gap-3 rounded-xl border border-slate-700 bg-slate-900/90 p-3 shadow-lg"
        :data-batch-key="batch.key"
        :data-shop-id="shopId"
    >
        <h2 class="border-b border-slate-600 pb-2 text-lg font-semibold text-white">
            {{ batch.table }}
        </h2>
        <div class="flex flex-col gap-2">
            <KdsTicketRow
                v-for="ticket in sortedTickets"
                :key="ticket.id"
                :ticket="ticket"
                @serve="forwardServe"
            />
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import KdsTicketRow from './KdsTicketRow.vue';
import { useMasterStore } from '../stores/useMasterStore';
import { useFilterStore } from '../stores/useFilterStore';

const props = defineProps({
    batch: {
        type: Object,
        required: true,
    },
    shopId: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(['serve']);

const master = useMasterStore();
const filter = useFilterStore();

const sortedTickets = computed(() => {
    const tickets = [...(props.batch?.tickets ?? [])];

    const filtered = tickets.filter((ticket) => {
        if (!master.filterStrict) {
            return true;
        }

        const catId = ticket.cat_id;
        if (catId === null || catId === undefined) {
            return false;
        }

        const inK = master.kitchenIds.includes(Number(catId));
        const inH = master.hallIds.includes(Number(catId));

        if (!filter.showKitchen && !filter.showHall) {
            return false;
        }

        return (filter.showKitchen && inK) || (filter.showHall && inH);
    });

    return filtered.sort((a, b) => {
        if (a.status === 'served' && b.status !== 'served') {
            return 1;
        }
        if (a.status !== 'served' && b.status === 'served') {
            return -1;
        }

        return 0;
    });
});

function forwardServe(id, rev, isLast, name) {
    emit('serve', id, rev, isLast, name);
}
</script>
