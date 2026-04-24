@php
    $shopId = (int) $this->shopId;
@endphp

{{-- Filament の <x-filament-panels::page> は py-8 を付けるためキオスクでは使わない --}}
<div
    @class([
        'fi-page fi-pos-dashboard-page flex h-full min-h-0 w-full max-w-full min-w-0 flex-col overflow-x-hidden p-0 text-gray-950 dark:text-gray-100 max-md:overflow-y-visible md:overflow-y-hidden',
    ])
>
    <div
        @class([
            'fi-pos-viewport',
            'flex w-full max-w-full min-h-0 flex-1 flex-col overflow-x-hidden overscroll-none bg-gray-100 text-gray-950 dark:bg-gray-950 dark:text-gray-100 max-md:overflow-y-visible md:overflow-y-hidden',
            'h-[100dvh] max-h-[100dvh]' => $shopId > 0,
            'min-h-[50vh]' => $shopId <= 0,
        ])
    >
        <div
            @class([
                'flex min-h-0 w-full max-w-full min-w-0 flex-1 flex-col overflow-x-hidden max-md:overflow-y-visible md:flex-row md:overflow-y-hidden',
            ])
        >
            <div
                @class([
                    'flex min-h-0 min-w-0 max-w-full flex-1 flex-col overflow-x-hidden border-b border-gray-200 dark:border-gray-600 max-md:overflow-y-visible md:border-e md:border-b-0 md:overflow-y-hidden',
                    'w-full',
                    'max-md:min-h-0 max-md:flex-1',
                    'md:h-full md:w-1/2 md:flex-none md:shrink-0' => $shopId > 0,
                ])
            >
                <div
                    class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-0.5 sm:p-1"
                >
                    @if ($shopId > 0)
                        <livewire:pos.table-status-grid
                            :shop-id="$shopId"
                            :key="'pos-table-status-grid-'.$shopId"
                        />
                    @else
                        <p class="text-xs text-gray-700 dark:text-gray-200 sm:text-sm">
                            {{ __('pos.table_dashboard_no_shop') }}
                        </p>
                    @endif
                </div>

                @if ($shopId > 0)
                    <livewire:pos.takeaway-bar
                        :shop-id="$shopId"
                        :key="'pos-takeaway-bar-'.$shopId"
                    />
                @endif

                <div
                    class="fi-pos-footer relative z-[1] flex flex-none min-h-0 flex-wrap items-center justify-between gap-1 border-t border-gray-200 bg-white p-1 dark:border-gray-600 dark:bg-gray-900 sm:gap-1.5 sm:p-1.5"
                >
                    <div
                        class="flex min-h-0 min-w-0 max-w-full flex-1 flex-col gap-1 max-md:items-stretch md:flex-row md:flex-wrap md:items-center sm:gap-1.5"
                    >
                        <div
                            class="flex shrink-0 flex-wrap items-center gap-1 max-md:w-full sm:gap-1.5"
                        >
                            @include('components.pos.footer-utility-menu')
                        </div>
                        @if ($shopId > 0)
                            <div
                                class="min-h-0 min-w-0 max-w-full max-md:basis-full max-md:w-full max-md:flex-none md:flex-1 sm:max-w-[20rem] sm:flex-initial"
                            >
                                <livewire:pos.staff-meal-bar
                                    :shop-id="$shopId"
                                    :staff-door-open="$staffDoorOpen"
                                    :inline-in-footer="true"
                                    :key="'pos-staff-meal-bar-'.$shopId"
                                />
                            </div>
                        @endif
                    </div>
                    <div
                        class="flex shrink-0 items-center gap-0.5 sm:gap-1"
                    >
                        <button
                            type="button"
                            wire:click="$toggle('staffDoorOpen')"
                            wire:loading.attr="disabled"
                            wire:target="staffDoorOpen"
                            @class([
                                'inline-flex h-11 w-11 min-h-11 min-w-11 touch-manipulation items-center justify-center rounded-full border border-pink-200/90 bg-pink-50 text-pink-400 shadow-sm transition hover:border-pink-300 hover:bg-pink-100 hover:text-pink-500 focus:outline-none focus:ring-2 focus:ring-pink-200/80 focus:ring-offset-1 focus:ring-offset-white active:scale-95 disabled:pointer-events-none disabled:opacity-50 dark:border-pink-800/60 dark:bg-pink-950/40 dark:text-pink-300 dark:hover:bg-pink-900/50 dark:hover:text-pink-200 dark:focus:ring-pink-500/40 dark:focus:ring-offset-gray-900',
                                'border-pink-300 bg-pink-100 text-pink-500 shadow dark:border-pink-600 dark:bg-pink-900/60' => $staffDoorOpen,
                            ])
                            aria-pressed="{{ $staffDoorOpen ? 'true' : 'false' }}"
                            aria-label="{{ __('pos.staff_door_toggle') }}"
                            title="{{ __('pos.staff_door_toggle') }}"
                        >
                            <svg
                                class="pointer-events-none h-4 w-4 shrink-0"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    fill="currentColor"
                                    d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            @if ($shopId > 0)
                <div
                    @class([
                        'flex h-auto max-h-[min(52dvh,30rem)] min-h-0 w-full max-w-full flex-none flex-col overflow-x-hidden border-gray-200 bg-white text-gray-950 dark:border-gray-600 dark:bg-slate-950 dark:text-gray-100 max-md:overflow-y-visible md:h-full md:max-h-none md:overflow-y-auto',
                        'md:w-1/2 md:shrink-0',
                    ])
                >
                    <livewire:pos.table-action-host
                        :shop-id="$shopId"
                        :key="'pos-table-action-host-'.$shopId"
                    />
                </div>
            @endif
        </div>

        @include('livewire.pos.table-dashboard')
    </div>

    @if ($shopId > 0)
        <livewire:pos.cloture-modal
            :shop-id="$shopId"
            :key="'pos-cloture-modal-'.$shopId"
        />
        <livewire:pos.discount-modal
            :shop-id="$shopId"
            :key="'pos-discount-modal-'.$shopId"
        />
        <livewire:pos.printer-bridge
            :shop-id="$shopId"
            :key="'pos-printer-bridge-'.$shopId"
        />

        @push('scripts')
            <script src="{{ asset('js/epos-2.27.0.js') }}"></script>
            <script>
                (function () {
                    const serverDevice = @json($this->posPrinterDeviceDefaults);
                    const shopId = {{ (int) $shopId }};

                    function pickOverride() {
                        try {
                            const raw = localStorage.getItem('pos_printer_override');
                            if (!raw) {
                                return null;
                            }
                            const o = JSON.parse(raw);
                            if (!o || typeof o !== 'object') {
                                return null;
                            }
                            return o;
                        } catch {
                            return null;
                        }
                    }

                    function mergeDevice(base, o) {
                        const out = { ...base };
                        if (o.printer_ip != null && String(o.printer_ip).trim() !== '') {
                            out.printer_ip = String(o.printer_ip).trim();
                        }
                        if (o.printer_port != null && String(o.printer_port).trim() !== '') {
                            out.printer_port = String(o.printer_port).trim();
                        }
                        if (o.device_id != null && String(o.device_id).trim() !== '') {
                            out.device_id = String(o.device_id).trim();
                        }
                        if (o.crypto != null) {
                            out.crypto = !!o.crypto;
                        }
                        if (o.timeout_ms != null && !Number.isNaN(Number(o.timeout_ms))) {
                            out.timeout_ms = Math.max(1000, Math.min(300000, Number(o.timeout_ms)));
                            out.timeoutMs = out.timeout_ms;
                        }
                        if (o.connect_timeout_ms != null && !Number.isNaN(Number(o.connect_timeout_ms))) {
                            out.connect_timeout_ms = Math.max(3000, Math.min(120000, Number(o.connect_timeout_ms)));
                        }
                        return out;
                    }

                    function buildServiceUrl(d) {
                        const scheme = d.crypto ? 'https' : 'http';
                        const timeout = Math.max(1000, Math.min(300000, Number(d.timeout_ms ?? d.timeoutMs ?? 10000)));
                        const dev = encodeURIComponent(String(d.device_id ?? 'local_printer'));
                        return (
                            scheme +
                            '://' +
                            String(d.printer_ip) +
                            ':' +
                            String(d.printer_port) +
                            '/cgi-bin/epos/service.cgi?devid=' +
                            dev +
                            '&timeout=' +
                            timeout
                        );
                    }

                    if (shopId < 1) {
                        window.posPrinterConfig = {
                            driver: 'mock',
                            url: '',
                            timeoutMs: 10000,
                        };
                        window.PosConfig = null;
                        return;
                    }

                    const o = pickOverride();
                    const device = o ? mergeDevice(serverDevice, o) : serverDevice;
                    window.posPrinterConfig = {
                        driver: 'epson',
                        url: buildServiceUrl(device),
                        timeoutMs: Number(device.timeout_ms ?? device.timeoutMs ?? 10000),
                    };
                    window.PosConfig = {
                        ip: String(device.printer_ip ?? ''),
                        port: Number(device.printer_port ?? 8043),
                        deviceId: String(device.device_id ?? 'local_printer'),
                        crypto: !!device.crypto,
                        buffer: !!device.buffer,
                        connectTimeoutMs: Number(device.connect_timeout_ms ?? 20000),
                        printTimeoutMs: Number(device.timeout_ms ?? device.timeoutMs ?? 10000),
                        idleDisconnectMs: Number(device.idle_disconnect_ms ?? 60000),
                        deviceInUseRetryMax: Number(device.device_in_use_retry_max ?? 5),
                        deviceInUseRetryDelayMs: Number(device.device_in_use_retry_delay_ms ?? 3000),
                    };
                })();
            </script>
            @vite(['resources/js/app.js'])
        @endpush
    @endif

    <x-filament-actions::modals />
    <x-filament-panels::unsaved-action-changes-alert />
</div>
