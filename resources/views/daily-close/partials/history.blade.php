<section class="mt-4 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <button
        type="button"
        wire:click="toggleHistoryDetail"
        class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-semibold text-gray-900 hover:bg-gray-50 dark:text-gray-100 dark:hover:bg-gray-700/50"
    >
        <span>Historique clôture (50)</span>
        <span class="text-gray-400 dark:text-gray-500">{{ $historyDetailOpen ? '▼' : '▶' }}</span>
    </button>
    @if ($historyDetailOpen)
        <div class="overflow-x-auto border-t border-gray-200 dark:border-gray-700">
            <table class="w-full min-w-[640px] text-left text-xs text-gray-800 dark:text-gray-200">
                <thead class="bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-900/80 dark:text-gray-400">
                    <tr>
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Shift</th>
                        <th class="px-3 py-2">Verdict</th>
                        <th class="px-3 py-2">Écart</th>
                        <th class="px-3 py-2">Responsable</th>
                        <th class="px-3 py-2">Opérateur</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->closeHistoryRows() as $h)
                        <tr class="border-t border-gray-100 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-900/40">
                            <td class="px-3 py-2 font-mono tabular-nums text-gray-700 dark:text-gray-300">{{ $h->business_date }}</td>
                            <td class="px-3 py-2">{{ $h->shift }}</td>
                            <td class="px-3 py-2 font-medium">{{ $this->historyVerdictLabel($h) }}</td>
                            <td class="px-3 py-2 font-mono tabular-nums">{{ $this->formatMoneyCompact($h->final_difference) }}</td>
                            <td class="px-3 py-2">{{ $this->historyResponsibleDisplay($h) }}</td>
                            <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $this->historyOperatorDisplay($h) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
