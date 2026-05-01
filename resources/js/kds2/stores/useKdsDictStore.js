import { defineStore } from 'pinia'

/** localStorage の辞書更新後に bump し、チケット行の computed を再評価する */
export const useKdsDictStore = defineStore('kds2Dict', {
    state: () => ({
        revision: 0,
    }),

    actions: {
        bump() {
            this.revision += 1
        },
    },
})
