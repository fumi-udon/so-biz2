<section class="mb-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
        <div class="min-w-0 flex-1">
            <label for="dc-business-date" class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300">Date</label>
            <input
                id="dc-business-date"
                type="date"
                wire:model.live="data.business_date"
                min="{{ now()->subDays(3)->toDateString() }}"
                max="{{ now()->toDateString() }}"
                class="w-full max-w-[11rem] rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-sm tabular-nums text-gray-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
            >
        </div>
        <button
            type="button"
            wire:click="fetchRecettesFromApi"
            wire:loading.attr="disabled"
            wire:target="fetchRecettesFromApi"
            class="inline-flex min-h-[2.5rem] min-w-[10rem] items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 shadow-sm transition-all duration-200 hover:bg-gray-50 disabled:opacity-95 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700"
            wire:loading.class="animate-pulse border-transparent bg-gradient-to-r from-sky-500 via-indigo-500 to-fuchsia-600 text-white shadow-lg ring-2 ring-amber-300 ring-offset-2 ring-offset-white dark:from-sky-600 dark:via-indigo-600 dark:to-fuchsia-700 dark:ring-amber-400/90 dark:ring-offset-gray-800"
        >
            <span wire:loading.remove wire:target="fetchRecettesFromApi">Récupérer les ventes</span>
            <span wire:loading wire:target="fetchRecettesFromApi" class="inline-flex items-center gap-2 font-bold tracking-wide">
                <svg class="size-5 shrink-0 animate-spin text-amber-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-30" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Synchronisation…
            </span>
        </button>
    </div>
    @if ($recettesApiErrorMessage)
        <p class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-800 dark:border-rose-800/50 dark:bg-rose-950/40 dark:text-rose-200">{{ $recettesApiErrorMessage }}</p>
    @endif
    @if ($fetchedRecettesPanel)
        <div class="mt-3 grid grid-cols-1 gap-2 rounded-lg border border-gray-100 bg-gray-50 p-3 text-sm sm:grid-cols-3 dark:border-gray-600 dark:bg-gray-900/40">
            <p class="text-gray-600 dark:text-gray-400">Midi <span class="font-mono font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format($fetchedRecettesPanel['lunch'], 2, '.', ',') }}</span></p>
            <p class="text-gray-600 dark:text-gray-400">Soir <span class="font-mono font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format($fetchedRecettesPanel['dinner'], 2, '.', ',') }}</span></p>
            <p class="text-gray-600 dark:text-gray-400">Jour <span class="font-mono font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format($fetchedRecettesPanel['journal'], 2, '.', ',') }}</span></p>
        </div>
        @if ($this->fetchedRecettesAmountForCurrentShift() !== null)
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Service courant (API) : <span class="font-mono font-medium tabular-nums text-gray-800 dark:text-gray-200">{{ number_format($this->fetchedRecettesAmountForCurrentShift(), 2, '.', ',') }}</span></p>
        @endif
    @endif
</section>
