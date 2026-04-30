<script setup>
/**
 * CategoryRail.vue
 * カテゴリ横スクロールレール。タップで 0ms 切り替え。
 */
const props = defineProps({
    categories: {
        type: Array,
        required: true,
    },
    activeCategoryId: {
        type: [String, Number, null],
        default: null,
    },
});

const emit = defineEmits(['select']);
</script>

<template>
    <nav class="w-full overflow-x-auto">
        <ul class="flex gap-2 pb-1">
            <li
                v-for="cat in categories"
                :key="cat.id"
            >
                <button
                    type="button"
                    class="whitespace-nowrap rounded-xl border px-4 py-2 text-sm font-semibold transition"
                    :class="String(activeCategoryId) === String(cat.id)
                        ? 'border-cyan-400 bg-cyan-500/25 text-cyan-100'
                        : 'border-slate-600 bg-slate-800/60 text-slate-300 hover:bg-slate-700/70 hover:text-white'"
                    @click="emit('select', cat.id)"
                >
                    {{ cat.name }}
                </button>
            </li>
            <li v-if="categories.length === 0" class="text-xs text-slate-500">
                カテゴリなし（マスタ同期が必要）
            </li>
        </ul>
    </nav>
</template>
