<script setup>
import { computed, ref, watch } from 'vue';
import { createDefaultTakeoutUi } from './takeoutCustomerLabelModalDefaults.js';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    /** 初期・編集用 */
    initialName: {
        type: String,
        default: '',
    },
    initialTel: {
        type: String,
        default: '',
    },
    /** Inertia `pos_ui` 由来（欠損時は createDefaultTakeoutUi） */
    takeoutUi: {
        type: Object,
        default: createDefaultTakeoutUi,
    },
});

const emit = defineEmits(['close', 'save']);

const ui = computed(() => ({ ...createDefaultTakeoutUi(), ...props.takeoutUi }));

const nameModel = ref('');
const telModel = ref('');
const nameError = ref('');

watch(
    () => props.open,
    (v) => {
        if (v) {
            nameModel.value = String(props.initialName ?? '');
            telModel.value = String(props.initialTel ?? '');
            nameError.value = '';
        }
    },
);

const canSubmit = computed(() => String(nameModel.value ?? '').trim().length > 0);

function onBackdropClick() {
    emit('close');
}

function onSubmit() {
    const n = String(nameModel.value ?? '').trim();
    if (!n) {
        nameError.value = ui.value.nameRequired;
        return;
    }
    nameError.value = '';
    emit('save', {
        name: n,
        tel: String(telModel.value ?? '').trim(),
    });
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm dark:bg-slate-950/80"
            role="dialog"
            aria-modal="true"
            aria-labelledby="takeout-label-modal-title"
            @click.self="onBackdropClick"
        >
            <div
                class="w-full max-w-sm rounded-2xl border border-slate-600 bg-slate-900 p-5 text-slate-100 shadow-2xl dark:border-slate-500 dark:bg-slate-900 dark:text-slate-100"
                @click.stop
            >
                <h2
                    id="takeout-label-modal-title"
                    class="text-base font-bold text-slate-50 dark:text-white"
                >
                    {{ ui.title }}
                </h2>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-400">
                    {{ ui.hint }}
                </p>

                <form
                    class="mt-4 space-y-3"
                    @submit.prevent="onSubmit"
                >
                    <div>
                        <label
                            for="takeout-label-name"
                            class="mb-1 block text-xs font-semibold text-slate-300 dark:text-slate-300"
                        >{{ ui.fieldName }} <span class="text-rose-400">*</span></label>
                        <input
                            id="takeout-label-name"
                            v-model="nameModel"
                            type="text"
                            autocomplete="name"
                            class="w-full rounded-xl border border-slate-600 bg-slate-950 px-3 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/40 dark:border-slate-500 dark:bg-slate-950 dark:text-slate-100"
                            :placeholder="ui.placeholderName"
                        >
                        <p
                            v-if="nameError"
                            class="mt-1 text-xs font-medium text-rose-400 dark:text-rose-400"
                        >
                            {{ nameError }}
                        </p>
                    </div>
                    <div>
                        <label
                            for="takeout-label-tel"
                            class="mb-1 block text-xs font-semibold text-slate-300 dark:text-slate-300"
                        >{{ ui.fieldTel }}</label>
                        <input
                            id="takeout-label-tel"
                            v-model="telModel"
                            type="tel"
                            autocomplete="tel"
                            inputmode="tel"
                            class="w-full rounded-xl border border-slate-600 bg-slate-950 px-3 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/40 dark:border-slate-500 dark:bg-slate-950 dark:text-slate-100"
                            :placeholder="ui.placeholderTel"
                        >
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-600 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800 dark:border-slate-500 dark:text-slate-200 dark:hover:bg-slate-800"
                            @click="emit('close')"
                        >
                            {{ ui.cancel }}
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl border-2 border-cyan-600 bg-cyan-600 px-4 py-2 text-sm font-bold text-cyan-950 shadow-md hover:bg-cyan-500 disabled:cursor-not-allowed disabled:opacity-40 dark:border-cyan-500 dark:bg-cyan-600 dark:text-cyan-950 dark:hover:bg-cyan-500"
                            :disabled="!canSubmit"
                        >
                            {{ ui.save }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
