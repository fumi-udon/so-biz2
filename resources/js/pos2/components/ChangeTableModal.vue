<script setup>
/**
 * POS V2: 卓移動 — 空き客席のみコンパクトグリッド（ミニ卓 5 列基調）。
 */
import { computed } from 'vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    /** @type {Array<{ id: number, name: string }>} */
    candidates: {
        type: Array,
        default: () => [],
    },
    busy: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
    hint: {
        type: String,
        default: '',
    },
    emptyText: {
        type: String,
        default: '',
    },
    cancelLabel: {
        type: String,
        default: '閉じる',
    },
});

const emit = defineEmits(['close', 'confirm']);

const hasCandidates = computed(() => Array.isArray(props.candidates) && props.candidates.length > 0);

function onBackdropClick() {
    if (!props.busy) {
        emit('close');
    }
}

function onConfirm(id) {
    if (props.busy) {
        return;
    }
    emit('confirm', Number(id));
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="pointer-events-auto fixed inset-0 z-[50] flex items-end justify-center bg-black/55 p-3 backdrop-blur-[1px] sm:items-center dark:bg-black/65"
            role="dialog"
            aria-modal="true"
            :aria-busy="busy"
            @click.self="onBackdropClick"
        >
            <div
                class="flex max-h-[min(85dvh,32rem)] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-600 bg-slate-950 text-slate-100 shadow-2xl dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                @click.stop
            >
                <div class="shrink-0 border-b border-slate-700 px-4 py-3 dark:border-slate-700">
                    <h2 class="text-base font-bold text-cyan-200 dark:text-cyan-200">
                        {{ title }}
                    </h2>
                    <p
                        v-if="hint"
                        class="mt-1 text-xs leading-relaxed text-slate-400 dark:text-slate-400"
                    >
                        {{ hint }}
                    </p>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-3 py-3">
                    <p
                        v-if="!hasCandidates"
                        class="rounded-xl border border-slate-700 bg-slate-900/80 px-3 py-4 text-center text-sm text-slate-300 dark:border-slate-600 dark:text-slate-300"
                    >
                        {{ emptyText }}
                    </p>
                    <div
                        v-else
                        class="grid grid-cols-3 gap-2 sm:grid-cols-5 sm:gap-2"
                    >
                        <button
                            v-for="c in candidates"
                            :key="c.id"
                            type="button"
                            :disabled="busy"
                            class="flex min-h-16 flex-col items-center justify-center rounded-xl border-2 border-slate-600 bg-slate-900/90 px-1 py-2 text-center text-xs font-semibold text-slate-100 transition hover:border-cyan-500 hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-500 dark:bg-slate-900 dark:text-slate-50 dark:hover:border-cyan-400 dark:hover:bg-slate-800"
                            @click="onConfirm(c.id)"
                        >
                            <span class="line-clamp-2 break-words">{{ c.name }}</span>
                        </button>
                    </div>
                </div>

                <div class="shrink-0 border-t border-slate-700 px-4 py-3 dark:border-slate-700">
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-600 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800 disabled:opacity-50 dark:border-slate-500 dark:text-slate-100 dark:hover:bg-slate-800"
                            :disabled="busy"
                            @click="emit('close')"
                        >
                            {{ cancelLabel }}
                        </button>
                    </div>
                    <div
                        v-if="busy"
                        class="mt-2 flex items-center gap-2 text-xs text-cyan-200 dark:text-cyan-200"
                    >
                        <span
                            class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-cyan-400 border-t-transparent"
                            aria-hidden="true"
                        />
                        <span>処理中…</span>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
