<template>
    <KdsBoard
        :batches="ticketStore.batches"
        :queued="ticketStore.queued"
        :shop-id="shopId"
        :on-sync-master="handleSyncMaster"
        @serve="handleServe"
    />
    <KdsConfirmModal
        :show="confirmModal.show"
        :ticket-name="confirmModal.name"
        @confirm="onModalConfirm"
        @cancel="onModalCancel"
    />
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import KdsBoard from './components/KdsBoard.vue'
import KdsConfirmModal from './components/KdsConfirmModal.vue'
import { useTicketStore } from './stores/useTicketStore'
import { useMasterStore } from './stores/useMasterStore'
import { useFilterStore } from './stores/useFilterStore'

const shopId = Number(
    document.querySelector('meta[name="kds-shop-id"]')?.content ?? 0,
)
const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? ''

const ticketStore = useTicketStore()
const master = useMasterStore()
const filter = useFilterStore()

const confirmModal = ref({ show: false, id: null, rev: null, name: '' })

let pollTimer = null

async function fetchTickets() {
    try {
        const res = await fetch('/kds2/api/tickets')
        const data = await res.json()
        ticketStore.hydrate(data)
    } catch (e) {
        console.error('[KDS] fetchTickets failed', e)
    }
}

async function handleServe(id, rev, isLast, name) {
    if (isLast) {
        confirmModal.value = { show: true, id, rev, name }
        return
    }
    ticketStore.markServedOptimistic(id)
    try {
        await fetch(`/kds2/api/tickets/${id}/served`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ rev }),
        })
    } catch (e) {
        console.error('[KDS] markServed failed', e)
    }
}

async function onModalConfirm() {
    const { id, rev } = confirmModal.value
    confirmModal.value = { show: false, id: null, rev: null, name: '' }
    ticketStore.markServedOptimistic(id)
    try {
        await fetch(`/kds2/api/tickets/${id}/served`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ rev }),
        })
    } catch (e) {
        console.error('[KDS] markServed failed', e)
    }
}

function onModalCancel() {
    confirmModal.value = { show: false, id: null, rev: null, name: '' }
}

async function handleSyncMaster() {
    await master.syncFromApi(shopId)
}

onMounted(async () => {
    filter.load(shopId)
    const cached = master.loadFromStorage(shopId)
    if (!cached) await master.syncFromApi(shopId)
    await fetchTickets()
    pollTimer = setInterval(fetchTickets, 2000)
})

onUnmounted(() => {
    clearInterval(pollTimer)
})
</script>
