<div
    class="min-h-screen bg-white px-3 py-3 text-gray-950 dark:bg-gray-950 dark:text-gray-100 sm:px-4"
    x-data="{ detailOpen: @entangle('detailOpen') }"
>
    <div class="mx-auto w-full max-w-6xl space-y-2">
        <div class="flex items-center justify-between gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-900">
            <div class="min-w-0">
                <h1 class="truncate text-sm font-black uppercase tracking-wide text-gray-900 dark:text-white">
                    History (Cloture)
                </h1>
                <p class="truncate text-[11px] text-gray-600 dark:text-gray-300">
                    {{ $shopName !== '' ? $shopName : 'Shop #'.$shopId }}
                </p>
            </div>
            <button
                type="button"
                x-on:click="window.close()"
                class="inline-flex items-center rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            >
                Close
            </button>
        </div>

        <div class="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-xs dark:divide-gray-700">
                    <thead class="bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100">
                        <tr>
                            <th class="px-2 py-2 text-left font-bold">Cloture time</th>
                            <th class="px-2 py-2 text-left font-bold">Session start</th>
                            <th class="px-2 py-2 text-left font-bold">Table</th>
                            <th class="px-2 py-2 text-right font-bold">Total</th>
                            <th class="px-2 py-2 text-left font-bold">Payment</th>
                            <th class="px-2 py-2 text-left font-bold">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($this->historyRows as $row)
                            @php
                                $tableName = (string) ($row->tableSession?->restaurantTable?->name ?? '');
                                $openedAt = $row->tableSession?->opened_at;
                            @endphp
                            <tr class="bg-white text-gray-900 dark:bg-gray-900 dark:text-gray-100">
                                <td class="whitespace-nowrap px-2 py-2 tabular-nums">
                                    {{ optional($row->settled_at)->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                                </td>
                                <td class="whitespace-nowrap px-2 py-2 tabular-nums text-gray-700 dark:text-gray-300">
                                    {{ $openedAt ? $openedAt->timezone(config('app.timezone'))->format('Y-m-d H:i') : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-2 py-2">
                                    {{ $tableName !== '' ? $tableName : 'Session #'.$row->table_session_id }}
                                </td>
                                <td class="whitespace-nowrap px-2 py-2 text-right font-bold tabular-nums">
                                    {{ $this->formatMinor((int) $row->final_total_minor) }}
                                </td>
                                <td class="whitespace-nowrap px-2 py-2">
                                    {{ $this->paymentLabel($row->payment_method?->value ?? null) }}
                                </td>
                                <td class="whitespace-nowrap px-2 py-2">
                                    <button
                                        type="button"
                                        wire:click="openDetail({{ (int) $row->id }})"
                                        class="inline-flex items-center rounded border border-blue-300 bg-blue-50 px-2 py-1 text-[11px] font-bold text-blue-800 hover:bg-blue-100 dark:border-blue-700 dark:bg-blue-950/40 dark:text-blue-200 dark:hover:bg-blue-900/50"
                                    >
                                        Details
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr class="bg-white dark:bg-gray-900">
                                <td colspan="6" class="px-2 py-5 text-center text-sm text-gray-600 dark:text-gray-300">
                                    本日の会計履歴はありません。
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div
        x-cloak
        x-show="detailOpen"
        class="fixed inset-0 z-[320] flex items-center justify-center bg-black/60 p-2"
        x-on:keydown.escape.window="$wire.closeDetail()"
    >
        <div class="absolute inset-0" x-on:click="$wire.closeDetail()"></div>
        <div class="relative z-[321] h-[92dvh] w-full max-w-5xl overflow-hidden rounded-lg border-2 border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-200 px-3 py-2 dark:border-gray-700">
                <p class="text-sm font-bold text-gray-900 dark:text-gray-100">Receipt details</p>
                <button
                    type="button"
                    x-on:click="$wire.closeDetail()"
                    class="inline-flex h-8 w-8 items-center justify-center rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    ×
                </button>
            </div>
            <iframe
                title="receipt-preview"
                class="h-[calc(92dvh-44px)] w-full border-0 bg-white dark:bg-gray-950"
                loading="lazy"
                x-bind:src="$wire.selectedReceiptUrl"
            ></iframe>
        </div>
    </div>
</div>
