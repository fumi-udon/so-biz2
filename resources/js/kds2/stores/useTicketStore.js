import { defineStore } from 'pinia'

export const useTicketStore = defineStore('kds2Tickets', {
    state: () => ({
        batches: [], // APIレスポンスのbatches配列
        queued: 0,
        generatedAt: null,
        pendingActions: [], // { id, rev, retriesLeft }
    }),

    actions: {
        hydrate(data) {
            this.batches = data.batches ?? []
            this.queued = data.queued ?? 0
            this.generatedAt = data.generated_at ?? null
        },

        markServedOptimistic(ticketId) {
            for (const batch of this.batches) {
                const ticket = batch.tickets.find((t) => t.id === ticketId)
                if (ticket) {
                    ticket.status = 'served'
                    break
                }
            }
        },

        enqueue(id, rev) {
            this.pendingActions.push({ id, rev, retriesLeft: 5 })
        },
    },
})
