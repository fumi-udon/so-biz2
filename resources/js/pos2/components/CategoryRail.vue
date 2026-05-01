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

/**
 * スタッフ向け短縮: 先頭の絵文字（ZWJ・FE0F の簡易対応）＋以降テキストの先頭3グラフェム。
 * 例: 「🥟 Entrées / Tapas」→「🥟Ent」、「Ramen」→「Ram」
 * @param {string|null|undefined} name
 * @returns {string}
 */
function formatCatName(name) {
    if (name == null || typeof name !== 'string') {
        return '';
    }
    const s = name.normalize('NFC').trim();
    if (!s) {
        return '';
    }

    const emojiPrefix =
        /^((?:\p{Extended_Pictographic}\uFE0F?(?:\u200D\p{Extended_Pictographic}\uFE0F?)*))(?:[\s\u200B]+|[/\-_]+)*/u;
    let emoji = '';
    let rest = s;
    const m = s.match(emojiPrefix);
    if (m?.[1]) {
        emoji = m[1];
        rest = s.slice(m[0].length);
    }

    const trimmed = rest.replace(/^[\s\u200B/\-_]+/, '');
    const abbr = Array.from(trimmed).slice(0, 3).join('');
    return emoji + abbr;
}
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
                    class="whitespace-nowrap rounded-xl border px-3 py-2 text-sm font-semibold transition"
                    :class="String(activeCategoryId) === String(cat.id)
                        ? 'border-cyan-400 bg-cyan-500/25 text-cyan-100'
                        : 'border-slate-600 bg-slate-800/60 text-slate-300 hover:bg-slate-700/70 hover:text-white'"
                    @click="emit('select', cat.id)"
                >
                    {{ formatCatName(cat.name) }}
                </button>
            </li>
            <li v-if="categories.length === 0" class="text-xs text-slate-500">
                カテゴリなし（マスタ同期が必要）
            </li>
        </ul>
    </nav>
</template>
