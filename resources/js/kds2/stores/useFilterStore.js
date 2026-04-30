import { defineStore } from 'pinia'

export const useFilterStore = defineStore('kds2Filter', {
    state: () => ({
        showKitchen: true,
        showHall: true,
    }),

    actions: {
        load(shopId) {
            const raw = localStorage.getItem(`kds2_filter_${shopId}`)
            if (!raw) return
            try {
                const data = JSON.parse(raw)
                this.showKitchen = data.showKitchen ?? true
                this.showHall = data.showHall ?? true
            } catch {}
        },

        persist(shopId) {
            localStorage.setItem(`kds2_filter_${shopId}`, JSON.stringify({
                showKitchen: this.showKitchen,
                showHall: this.showHall,
            }))
        },

        toggle(key, shopId) {
            this[key] = !this[key]
            this.persist(shopId)
        },
    },
})
