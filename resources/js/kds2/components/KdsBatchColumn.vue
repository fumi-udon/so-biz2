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
                :shop-id="shopId"
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

    const MISSING = 999_999_999;

    return filtered.sort((a, b) => {
        const ca = Number(a.category_sort ?? MISSING);
        const cb = Number(b.category_sort ?? MISSING);
        if (ca !== cb) return ca - cb;

        const ia = Number(a.item_sort ?? MISSING);
        const ib = Number(b.item_sort ?? MISSING);
        if (ia !== ib) return ia - ib;

        const na = String(a.sort_name ?? '');
        const nb = String(b.sort_name ?? '');
        const nameCmp = na.localeCompare(nb, 'ja');
        if (nameCmp !== 0) return nameCmp;

        return Number(a.id) - Number(b.id);
    });
});

function forwardServe(id, rev, isLast, name) {
    emit('serve', id, rev, isLast, name);
}
</script>
