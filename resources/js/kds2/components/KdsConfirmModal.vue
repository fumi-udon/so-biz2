<template>
    <div
        v-if="show"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="kds-confirm-modal-title"
    >
        <div
            class="w-full max-w-md rounded-xl border border-slate-600 bg-slate-900 p-6 shadow-xl"
            @click.stop
        >
            <h2 id="kds-confirm-modal-title" class="text-lg font-semibold text-white">
                Last one.
            </h2>
            <p class="mt-3 text-base text-slate-100">
                {{ ticketName }}
            </p>
            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <button
                    type="button"
                    class="rounded-lg border border-slate-500 bg-slate-800 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900"
                    @click="$emit('cancel')"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-rose-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900"
                    @click="$emit('confirm')"
                >
                    Served
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    show: {
        type: Boolean,
        required: true,
    },
    ticketName: {
        type: String,
        default: '',
    },
});

defineEmits(['confirm', 'cancel']);
</script>
