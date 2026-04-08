<section class="mb-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-base font-semibold text-gray-900 dark:text-gray-100">Clôture caisse</h1>
            <p class="text-xs text-gray-500 dark:text-gray-400">Saisie et validation du service</p>
        </div>
        <div class="text-right text-sm text-gray-700 dark:text-gray-200">
            <p><span class="text-gray-500 dark:text-gray-400">Responsable</span> <span class="font-medium text-gray-900 dark:text-gray-100">{{ $this->responsibleStaffDisplayName() }}</span></p>
            <p><span class="text-gray-500 dark:text-gray-400">Service</span> <span class="font-mono font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ $this->currentShiftLabel() }}</span></p>
        </div>
    </div>
</section>
