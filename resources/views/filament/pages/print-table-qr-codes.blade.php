<x-filament-panels::page>
    <div class="fi-print-table-qr-hide-on-print mx-auto w-full max-w-[1600px] space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                    {{ $shopName !== '' ? $shopName : '—' }}
                    @if ($shopId > 0)
                        <span class="tabular-nums text-gray-500 dark:text-gray-400">— Shop #{{ $shopId }}</span>
                    @endif
                </p>
                <p class="mt-1 break-all text-xs text-gray-600 dark:text-gray-400">
                    @if ($shopSlug !== '')
                        <code>{{ rtrim(url('/guest/menu/'.$shopSlug), '/') }}/…</code>
                    @else
                        —
                    @endif
                </p>
            </div>
            <button
                type="button"
                class="print:hidden inline-flex items-center justify-center gap-2 rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:bg-white dark:text-gray-950 dark:hover:bg-gray-100 dark:focus-visible:ring-offset-gray-900"
                onclick="window.print()"
            >
                <x-filament::icon icon="heroicon-o-printer" class="h-5 w-5" />
                <span>このページを印刷する / Print</span>
            </button>
        </div>
    </div>

    @if ($tables->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-700 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200">
            @if ($shopId === 0)
                No active shop found. Configure <code class="rounded bg-gray-100 px-1 py-0.5 text-xs text-gray-900 dark:bg-gray-800 dark:text-gray-100">pos.default_shop_id</code> or activate a shop.
            @else
                No active tables with a QR token for this shop.
            @endif
        </div>
    @else
        <div
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 print:grid-cols-2 print:gap-3"
        >
            @foreach ($tables as $table)
                @php
                    $guestUrl = $this->guestUrlForTable($table);
                    $qrSrc = $guestUrl !== ''
                        ? 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data='.rawurlencode($guestUrl)
                        : '';
                @endphp
                <div
                    class="flex flex-col items-center rounded-xl border border-gray-200 bg-white p-4 text-center shadow-sm print:break-inside-avoid print:border-gray-400 print:shadow-none dark:border-gray-700 dark:bg-gray-900"
                >
                    <p class="mb-3 text-lg font-bold text-gray-950 dark:text-white print:text-xl">
                        {{ $table->name }}
                    </p>
                    @if ($qrSrc !== '')
                        <img
                            src="{{ $qrSrc }}"
                            alt="QR {{ $table->name }}"
                            class="h-[200px] w-[200px] max-w-full object-contain print:h-[240px] print:w-[240px]"
                            loading="lazy"
                            referrerpolicy="no-referrer"
                        >
                    @else
                        <span class="text-xs text-gray-500 dark:text-gray-400">Missing URL</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
