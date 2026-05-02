<script setup>
import { computed, ref, watch } from 'vue';

/** @typedef {{ id: number, name: string, level?: number }} PinApprover */

const props = defineProps({
    /** 親が v-if で出し分けする想定。true のときのみ Teleport 内を表示 */
    open: {
        type: Boolean,
        default: true,
    },
    /** @type {PinApprover[]} */
    approvers: {
        type: Array,
        default: () => [],
    },
    /** 文言（Inertia pos_ui 拡張や Index の pos2Screen から渡す） */
    labels: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['close', 'submit']);

const defaults = {
    title: 'Validation responsable',
    hint: 'Sélectionnez un approbateur et saisissez le PIN.',
    approverPlaceholder: '— Responsable —',
    approverLabel: 'Responsable',
    pinLabel: 'Code PIN',
    cancel: 'Annuler',
    submit: 'Valider',
    emptyApprovers: 'Aucun responsable (niveau 3+) disponible.',
    validationApprover: 'Choisissez un responsable.',
    validationPin: 'Saisissez le PIN.',
};

const ui = computed(() => ({ ...defaults, ...props.labels }));

const selectedStaffId = ref('');
const pinModel = ref('');
const localError = ref('');

watch(
    () => props.open,
    (v) => {
        if (v) {
            selectedStaffId.value = '';
            pinModel.value = '';
            localError.value = '';
        }
    },
    { immediate: true },
);

const hasApprovers = computed(() => Array.isArray(props.approvers) && props.approvers.length > 0);

const canSubmit = computed(() => hasApprovers.value);

function onBackdropClick() {
    emit('close');
}

function onSubmit() {
    localError.value = '';
    const sid = String(selectedStaffId.value ?? '').trim();
    const pin = String(pinModel.value ?? '').trim();
    if (!sid || Number(sid) < 1) {
        localError.value = ui.value.validationApprover;
        return;
    }
    if (pin === '') {
        localError.value = ui.value.validationPin;
        return;
    }
    emit('submit', {
        approverStaffId: Number(sid),
        approvalPin: pin,
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
            aria-labelledby="pos2-line-delete-pin-title"
            @click.self="onBackdropClick"
        >
            <div
                class="w-full max-w-sm rounded-2xl border border-slate-600 bg-slate-900 p-5 text-slate-100 shadow-2xl dark:border-slate-500 dark:bg-slate-900 dark:text-slate-100"
                @click.stop
            >
                <h2
                    id="pos2-line-delete-pin-title"
                    class="text-base font-semibold text-slate-100 dark:text-slate-100"
                >
                    {{ ui.title }}
                </h2>
                <p class="mt-2 text-xs leading-relaxed text-slate-400 dark:text-slate-400">
                    {{ ui.hint }}
                </p>

                <div class="mt-4 space-y-3">
                    <div>
                        <label
                            for="pos2-line-delete-pin-approver"
                            class="mb-1 block text-xs font-medium text-slate-400 dark:text-slate-400"
                        >
                            {{ ui.approverLabel }}
                        </label>
                        <select
                            id="pos2-line-delete-pin-approver"
                            v-model="selectedStaffId"
                            class="w-full rounded-xl border border-slate-600 bg-slate-950 px-3 py-2.5 text-sm text-slate-100 outline-none ring-offset-2 ring-offset-slate-950 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 dark:border-slate-500 dark:bg-slate-950 dark:text-slate-100"
                            :disabled="!hasApprovers"
                        >
                            <option value="">
                                {{ ui.approverPlaceholder }}
                            </option>
                            <option
                                v-for="a in approvers"
                                :key="a.id"
                                :value="String(a.id)"
                            >
                                {{ a.name }}{{ a.level != null ? ` (L${a.level})` : '' }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            for="pos2-line-delete-pin-input"
                            class="mb-1 block text-xs font-medium text-slate-400 dark:text-slate-400"
                        >
                            {{ ui.pinLabel }}
                        </label>
                        <input
                            id="pos2-line-delete-pin-input"
                            v-model="pinModel"
                            type="password"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            class="w-full rounded-xl border border-slate-600 bg-slate-950 px-3 py-2.5 text-sm tracking-widest text-slate-100 outline-none ring-offset-2 ring-offset-slate-950 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 dark:border-slate-500 dark:bg-slate-950 dark:text-slate-100"
                            @keydown.enter.prevent="onSubmit"
                        >
                    </div>
                </div>

                <p
                    v-if="!hasApprovers"
                    class="mt-3 text-xs text-amber-400 dark:text-amber-300"
                >
                    {{ ui.emptyApprovers }}
                </p>
                <p
                    v-if="localError"
                    class="mt-3 text-xs text-rose-400 dark:text-rose-300"
                >
                    {{ localError }}
                </p>

                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-600 px-4 py-2 text-sm font-medium text-slate-200 transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 dark:border-slate-500 dark:text-slate-100 dark:hover:bg-slate-800"
                        @click="onBackdropClick"
                    >
                        {{ ui.cancel }}
                    </button>
                    <button
                        type="button"
                        class="rounded-xl border border-violet-600 bg-violet-700 px-4 py-2 text-sm font-semibold text-violet-50 transition hover:bg-violet-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 disabled:cursor-not-allowed disabled:opacity-50 dark:border-violet-500 dark:bg-violet-800 dark:text-violet-50 dark:hover:bg-violet-700"
                        :disabled="!canSubmit"
                        @click="onSubmit"
                    >
                        {{ ui.submit }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
