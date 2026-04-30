import { defineStore } from 'pinia'

export const useMasterStore = defineStore('kds2Master', {
    state: () => ({
        shopId: 0,
        kitchenIds: [],
        hallIds: [],
        filterStrict: false,
        categories: [],
        loadedAt: null,
    }),

    getters: {
        isLoaded: (state) => state.loadedAt !== null,
    },

    actions: {
        loadFromStorage(shopId) {
            const raw = localStorage.getItem(`kds2_master_${shopId}`)
            if (!raw) return false
            try {
                const data = JSON.parse(raw)
                this.shopId = shopId
                this.kitchenIds = data.kitchenIds ?? []
                this.hallIds = data.hallIds ?? []
                this.filterStrict = data.filter_strict ?? false
                this.categories = data.categories ?? []
                this.loadedAt = data.savedAt ?? null
                return true
            } catch {
                return false
            }
        },

        async syncFromApi(shopId) {
            const res = await fetch(`/kds2/api/master`)
            const data = await res.json()
            this.shopId = shopId
            // API は snake_case で返す (kitchen_category_ids / hall_category_ids)
            this.kitchenIds = data.kitchen_category_ids ?? []
            this.hallIds = data.hall_category_ids ?? []
            this.filterStrict = data.filter_strict ?? false
            this.categories = data.categories ?? []
            this.loadedAt = new Date().toISOString()
            localStorage.setItem(`kds2_master_${shopId}`, JSON.stringify({
                kitchenIds: this.kitchenIds,
                hallIds: this.hallIds,
                filter_strict: this.filterStrict,
                categories: this.categories,
                savedAt: this.loadedAt,
            }))
        },
    },
})
