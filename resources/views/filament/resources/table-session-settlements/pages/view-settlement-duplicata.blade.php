<x-filament-panels::page>
    @php
        $posDevice = \App\Support\Pos\PosPrinterClientConfig::resolveForShopId((int) $record->shop_id);
    @endphp

    @push('scripts')
        <script src="{{ asset('js/epos-2.27.0.js') }}"></script>
        <script>
            (function () {
                const serverDevice = @json($posDevice);
                window.posPrinterConfig = {
                    driver: 'epson',
                    url: String(serverDevice.url ?? ''),
                    timeoutMs: Number(serverDevice.timeoutMs ?? 10000),
                };
                window.PosConfig = {
                    ip: String(serverDevice.printer_ip ?? ''),
                    port: Number(serverDevice.printer_port ?? 8043),
                    deviceId: String(serverDevice.device_id ?? 'local_printer'),
                    crypto: !!serverDevice.crypto,
                    buffer: !!serverDevice.buffer,
                    connectTimeoutMs: Number(serverDevice.connect_timeout_ms ?? 20000),
                    printTimeoutMs: Number(serverDevice.timeout_ms ?? serverDevice.timeoutMs ?? 10000),
                    idleDisconnectMs: Number(serverDevice.idle_disconnect_ms ?? 60000),
                    deviceInUseRetryMax: Number(serverDevice.device_in_use_retry_max ?? 5),
                    deviceInUseRetryDelayMs: Number(serverDevice.device_in_use_retry_delay_ms ?? 3000),
                };
            })();
        </script>
        @vite(['resources/js/app.js'])
    @endpush

    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <x-filament::button
                color="gray"
                tag="a"
                :href="\App\Filament\Resources\TableSessionSettlements\TableSessionSettlementResource::getUrl()"
            >
                {{ __('pos.settlement_history_back') }}
            </x-filament::button>
            <p class="text-xs text-gray-600 dark:text-gray-400">
                #{{ $record->id }} · {{ $record->shop?->name ?? ('Shop #'.$record->shop_id) }}
            </p>
        </div>

        <div class="relative min-h-[70vh]">
            @livewire(\App\Livewire\Pos\ReceiptPreview::class, [
                'shopId' => (int) $record->shop_id,
                'tableSessionId' => (int) $record->table_session_id,
                'intent' => 'copy',
                'expectedSessionRevision' => $expectedSessionRevision,
            ], key('settlement-duplicata-'.$record->id))
        </div>
    </div>

    <livewire:pos.printer-bridge
        :shop-id="(int) $record->shop_id"
        :key="'settlement-duplicata-printer-bridge-'.$record->id"
    />
</x-filament-panels::page>
