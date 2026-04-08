<div
    x-data="{ show: @entangle('showResultOverlay') }"
    x-show="show"
    x-cloak
    x-transition:enter="ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4 backdrop-blur-[2px] dark:bg-black/50"
    role="dialog"
    aria-modal="true"
    style="display: none;"
>
    <div
        @class([
            'relative w-full max-w-md overflow-hidden rounded-2xl border-2 p-6 text-center shadow-2xl',
            'border-emerald-400/80 bg-gradient-to-br from-emerald-50 via-white to-teal-100 ring-4 ring-emerald-400/40 dark:border-emerald-500/50 dark:from-emerald-950/60 dark:via-gray-800 dark:to-teal-950/50 dark:ring-emerald-500/25' => $resultModalKind === 'bravo',
            'border-amber-400/70 bg-gradient-to-br from-amber-50 via-white to-orange-100 ring-4 ring-amber-400/35 dark:border-amber-500/45 dark:from-amber-950/50 dark:via-gray-800 dark:to-orange-950/45 dark:ring-amber-500/20' => $resultModalKind !== 'bravo',
        ])
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95 translate-y-2"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        @click.stop
    >
        @if ($resultModalKind === 'bravo')
            <div class="pointer-events-none absolute -right-6 -top-6 h-28 w-28 rounded-full bg-emerald-400/25 blur-2xl dark:bg-emerald-500/20"></div>
            <div class="pointer-events-none absolute -bottom-4 -left-4 h-24 w-24 rounded-full bg-teal-400/20 blur-2xl dark:bg-teal-500/15"></div>
            <div class="relative mb-4 flex flex-col items-center gap-2">
                <p class="text-3xl font-black tracking-tight text-emerald-600 drop-shadow-sm dark:text-emerald-300 sm:text-4xl">
                    Bravo !!
                </p>
                <p class="flex items-center gap-1.5 text-sm font-semibold text-emerald-800 dark:text-emerald-200">
                    <span class="text-lg" aria-hidden="true">✨</span>
                    <span class="rounded-full border border-emerald-300/80 bg-white/90 px-4 py-1.5 text-base font-bold text-emerald-900 shadow-sm dark:border-emerald-500/40 dark:bg-emerald-900/40 dark:text-emerald-100">
                        {{ $this->responsibleStaffDisplayName() }}
                    </span>
                    <span class="text-lg" aria-hidden="true">✨</span>
                </p>
                <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">Clôture validée</p>
                <p class="max-w-xs text-xs text-gray-600 dark:text-gray-300">Les montants correspondent à la tolérance.</p>
            </div>
        @else
            <div class="pointer-events-none absolute -right-5 -top-5 h-24 w-24 rounded-full bg-amber-400/20 blur-2xl dark:bg-amber-500/15"></div>
            <div class="relative mb-4">
                <p class="mb-1 text-2xl font-black tracking-tight text-amber-600 dark:text-amber-300 sm:text-3xl">
                    Écart détecté
                </p>
                <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Action requise</p>
                <p class="mx-auto mt-2 max-w-xs text-xs text-gray-600 dark:text-gray-300">Vérifiez la saisie ou les justificatifs avant de poursuivre.</p>
            </div>
        @endif

        <p class="mb-1 text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $resultModalShiftLabel }}</p>
        <p class="mb-4 text-xs leading-relaxed text-gray-600 dark:text-gray-400">{{ $resultModalHint }}</p>

        <dl @class([
            'mb-5 space-y-2 rounded-xl border p-3 text-left text-xs shadow-inner',
            'border-emerald-200/80 bg-white/70 dark:border-emerald-700/40 dark:bg-gray-900/40' => $resultModalKind === 'bravo',
            'border-amber-200/80 bg-white/70 dark:border-amber-700/40 dark:bg-gray-900/40' => $resultModalKind !== 'bravo',
        ])>
            @if ($resultModalKind !== 'bravo')
                <div class="flex justify-between gap-2">
                    <dt class="text-gray-500 dark:text-gray-400">Écart</dt>
                    <dd class="font-mono text-sm font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format((float) ($resultModalCalc['final_difference'] ?? 0), 3, '.', ',') }} DT</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-gray-500 dark:text-gray-400">Mesure caisse</dt>
                    <dd class="font-mono text-sm font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format((float) ($resultModalCalc['measured_without_declared_tip'] ?? 0), 3, '.', ',') }}</dd>
                </div>
            @endif
            <div class="flex justify-between gap-2">
                <dt class="text-gray-500 dark:text-gray-400">Pourboire + POS</dt>
                <dd class="font-mono text-sm font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format((float) ($resultModalCalc['sum_tip_plus_pos_sales'] ?? 0), 3, '.', ',') }}</dd>
            </div>
        </dl>

        <button
            type="button"
            wire:click="dismissResultOverlay"
            @class([
                'w-full rounded-xl px-4 py-3 text-sm font-bold text-white shadow-lg transition hover:brightness-110 active:scale-[0.99]',
                'bg-gradient-to-r from-emerald-600 to-teal-600 ring-2 ring-emerald-400/50 hover:from-emerald-500 hover:to-teal-500 dark:from-emerald-500 dark:to-teal-500' => $resultModalKind === 'bravo',
                'bg-gradient-to-r from-amber-600 to-orange-600 ring-2 ring-amber-400/40 hover:from-amber-500 hover:to-orange-500 dark:from-amber-500 dark:to-orange-500' => $resultModalKind !== 'bravo',
            ])
        >
            Fermer
        </button>
    </div>
</div>
