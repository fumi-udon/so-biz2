<x-filament-panels::page>
    <div
        wire:ignore
        class="mx-auto w-full max-w-3xl space-y-4"
        x-data="printerDiagnosticPanel(@js($dbPrinterDeviceDefaults))"
        x-init="init()"
    >
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-gray-950 dark:text-white">Printer Diagnostics Panel</h2>
                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                        Shop #<span x-text="shopId"></span>
                        <span x-show="shopName && shopName.length"> — <span x-text="shopName"></span></span>
                    </p>
                </div>

                <span
                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                    :class="{
                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200': healthBadge === 'online',
                        'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200': healthBadge === 'warning',
                        'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200': healthBadge === 'offline',
                    }"
                >
                    <template x-if="healthBadge === 'online'"><span>Online</span></template>
                    <template x-if="healthBadge === 'warning'"><span>Warning</span></template>
                    <template x-if="healthBadge === 'offline'"><span>Offline</span></template>
                </span>
            </div>

            <p class="mt-2 text-xs text-gray-600 dark:text-gray-300" x-text="printerStatus"></p>
        </div>

        <div class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:grid-cols-2">
            <label class="block sm:col-span-1">
                <span class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300">Target IP</span>
                <input
                    type="text"
                    x-model.trim="ip"
                    class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
            </label>
            <label class="block sm:col-span-1">
                <span class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300">Port</span>
                <input
                    type="text"
                    x-model.trim="port"
                    class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
            </label>
            <label class="block sm:col-span-2">
                <span class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300">Device ID</span>
                <input
                    type="text"
                    x-model.trim="deviceId"
                    class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
            </label>
            <label class="flex items-center gap-2 sm:col-span-2">
                <input type="checkbox" x-model="crypto" class="rounded border-gray-300 dark:border-gray-600">
                <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">TLS (crypto)</span>
            </label>
            <label class="block sm:col-span-2">
                <span class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300">Timeout (ms)</span>
                <input
                    type="number"
                    min="1000"
                    max="300000"
                    x-model.number="timeoutMs"
                    class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
            </label>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                class="inline-flex items-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-500 disabled:cursor-not-allowed disabled:opacity-50"
                @click="connect()"
                x-bind:disabled="isBusy || isConnected"
            >
                Connect
            </button>
            <button
                type="button"
                class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                @click="testPrint()"
                x-bind:disabled="isBusy || !isConnected || !printer"
            >
                Test print
            </button>
            <button
                type="button"
                class="inline-flex items-center rounded-lg bg-gray-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-500 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-gray-700 dark:hover:bg-gray-600"
                @click="disconnect()"
                x-bind:disabled="isBusy || !isConnected"
            >
                Disconnect
            </button>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-800 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700"
                @click="saveLocalOverride()"
            >
                Save to this device (local override)
            </button>
            <button
                type="button"
                class="inline-flex items-center rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-rose-500"
                @click="clearLocalOverride()"
            >
                Clear override
            </button>
        </div>

        <div class="rounded-xl border border-gray-200 bg-black p-3 shadow-sm dark:border-gray-700">
            <div class="mb-2 flex items-center justify-between">
                <h3 class="text-xs font-bold uppercase tracking-wide text-emerald-300">Live log monitor</h3>
                <button
                    type="button"
                    class="rounded-md border border-gray-600 bg-gray-800 px-2 py-1 text-[11px] font-semibold text-gray-200 hover:bg-gray-700"
                    @click="clearLogs()"
                >
                    Clear
                </button>
            </div>
            <pre
                x-ref="logBox"
                class="h-72 max-h-[50dvh] overflow-auto whitespace-pre-wrap break-words rounded border border-emerald-900/40 bg-black p-2 font-mono text-[11px] leading-relaxed text-emerald-300 sm:h-96"
                x-text="logs.join('\n')"
            ></pre>
        </div>
    </div>

    @push('scripts')
        <script src="{{ asset('js/epos-2.27.0.js') }}"></script>
        <script>
            function printerDiagnosticPanel(dbDefaults) {
                return {
                    shopId: dbDefaults.shop_id ?? 0,
                    shopName: @js($shopName),
                    dbDefaults,
                    ip: '',
                    port: '',
                    deviceId: '',
                    crypto: true,
                    timeoutMs: 10000,
                    isBusy: false,
                    isConnected: false,
                    healthBadge: 'offline',
                    printerStatus: 'Not connected',
                    logs: [],
                    ePosDev: null,
                    printer: null,

                    ASB_NO_RESPONSE: 0x00000001,
                    ASB_OFF_LINE: 0x00000008,
                    ASB_COVER_OPEN: 0x00000020,
                    ASB_MECHANICAL_ERR: 0x00000400,
                    ASB_AUTOCUTTER_ERR: 0x00000800,
                    ASB_UNRECOVER_ERR: 0x00002000,
                    ASB_AUTORECOVER_ERR: 0x00004000,
                    ASB_RECEIPT_NEAR_END: 0x00020000,
                    ASB_RECEIPT_END: 0x00080000,

                    init() {
                        this.reloadEffectiveFields();
                        this.log('Panel ready. Priority: localStorage `pos_printer_override` > server defaults.');
                        this.syncWindowPosPrinterConfig();
                    },

                    readOverride() {
                        try {
                            const raw = localStorage.getItem('pos_printer_override');
                            if (!raw) return null;
                            const o = JSON.parse(raw);
                            if (!o || typeof o !== 'object') return null;
                            return o;
                        } catch {
                            return null;
                        }
                    },

                    reloadEffectiveFields() {
                        const o = this.readOverride();
                        const base = { ...this.dbDefaults };
                        if (o) {
                            if (o.printer_ip != null && String(o.printer_ip).trim() !== '') base.printer_ip = String(o.printer_ip).trim();
                            if (o.printer_port != null && String(o.printer_port).trim() !== '') base.printer_port = String(o.printer_port).trim();
                            if (o.device_id != null && String(o.device_id).trim() !== '') base.device_id = String(o.device_id).trim();
                            if (o.crypto != null) base.crypto = !!o.crypto;
                            if (o.timeout_ms != null && !Number.isNaN(Number(o.timeout_ms))) {
                                base.timeout_ms = Math.max(1000, Math.min(300000, Number(o.timeout_ms)));
                            }
                        }
                        this.ip = String(base.printer_ip ?? '192.168.1.200');
                        this.port = String(base.printer_port ?? '8043');
                        this.deviceId = String(base.device_id ?? 'local_printer');
                        this.crypto = !!base.crypto;
                        this.timeoutMs = Math.max(1000, Math.min(300000, Number(base.timeout_ms ?? base.timeoutMs ?? 10000)));
                    },

                    buildServiceUrl() {
                        const scheme = this.crypto ? 'https' : 'http';
                        const dev = encodeURIComponent(this.deviceId);
                        const t = Math.max(1000, Math.min(300000, Number(this.timeoutMs)));
                        return `${scheme}://${this.ip}:${this.port}/cgi-bin/epos/service.cgi?devid=${dev}&timeout=${t}`;
                    },

                    syncWindowPosPrinterConfig() {
                        if (this.shopId < 1) {
                            window.posPrinterConfig = { driver: 'mock', url: '', timeoutMs: 10000 };
                            return;
                        }
                        window.posPrinterConfig = {
                            driver: 'epson',
                            url: this.buildServiceUrl(),
                            timeoutMs: Number(this.timeoutMs),
                        };
                    },

                    saveLocalOverride() {
                        const payload = {
                            printer_ip: this.ip,
                            printer_port: this.port,
                            device_id: this.deviceId,
                            crypto: this.crypto,
                            timeout_ms: Number(this.timeoutMs),
                        };
                        localStorage.setItem('pos_printer_override', JSON.stringify(payload));
                        this.log('Saved local override: ' + JSON.stringify(payload));
                        this.reloadEffectiveFields();
                        this.syncWindowPosPrinterConfig();
                    },

                    clearLocalOverride() {
                        localStorage.removeItem('pos_printer_override');
                        this.log('Cleared local override.');
                        this.reloadEffectiveFields();
                        this.syncWindowPosPrinterConfig();
                    },

                    log(message) {
                        const ts = new Date().toLocaleTimeString();
                        this.logs.push(`[${ts}] ${message}`);
                        if (this.logs.length > 500) this.logs.shift();
                        this.$nextTick(() => {
                            const el = this.$refs.logBox;
                            if (el) el.scrollTop = el.scrollHeight;
                        });
                    },

                    clearLogs() {
                        this.logs = [];
                        this.log('Log cleared.');
                    },

                    decodeStatus(status) {
                        const s = Number(status || 0);
                        const m = [];
                        if (s & this.ASB_NO_RESPONSE) m.push('Network error');
                        if (s & this.ASB_OFF_LINE) m.push('Printer offline');
                        if (s & this.ASB_COVER_OPEN) m.push('Cover open');
                        if (s & this.ASB_MECHANICAL_ERR) m.push('Mechanical error');
                        if (s & this.ASB_AUTOCUTTER_ERR) m.push('Auto cutter error');
                        if (s & this.ASB_UNRECOVER_ERR) m.push('Unrecoverable error');
                        if (s & this.ASB_AUTORECOVER_ERR) m.push('Auto-recoverable error');
                        if (s & this.ASB_RECEIPT_NEAR_END) m.push('Paper near end');
                        if (s & this.ASB_RECEIPT_END) m.push('Paper out');
                        return m;
                    },

                    ensureSdk() {
                        if (!window.epson || !window.epson.ePOSDevice) {
                            this.healthBadge = 'offline';
                            this.printerStatus = 'SDK missing';
                            this.log('ERROR: ePOS SDK not loaded (epos-2.27.0.js).');
                            return false;
                        }
                        return true;
                    },

                    connect() {
                        if (!this.ensureSdk()) return;
                        if (this.isConnected) {
                            this.log('Already connected.');
                            return;
                        }
                        this.isBusy = true;
                        this.healthBadge = 'warning';
                        this.printerStatus = 'Connecting...';
                        this.syncWindowPosPrinterConfig();
                        try {
                            this.ePosDev = new window.epson.ePOSDevice();
                            this.bindDeviceEvents();
                            this.log(`Connecting ${this.ip}:${this.port} crypto=${this.crypto} ...`);
                            this.ePosDev.connect(this.ip, String(this.port), (data) => this.onConnectResult(data));
                        } catch (e) {
                            this.isBusy = false;
                            this.healthBadge = 'offline';
                            this.printerStatus = 'Connect failed';
                            this.log('CONNECT THROW: ' + (e && e.message ? e.message : String(e)));
                        }
                    },

                    onConnectResult(data) {
                        this.log('connect callback: ' + String(data));
                        if (data !== 'OK' && data !== 'SSL_CONNECT_OK') {
                            this.isBusy = false;
                            this.isConnected = false;
                            this.healthBadge = 'offline';
                            this.printerStatus = 'Connect failed';
                            return;
                        }
                        const options = { crypto: this.crypto, buffer: false };
                        this.ePosDev.createDevice(this.deviceId, this.ePosDev.DEVICE_TYPE_PRINTER, options, (dev, code) =>
                            this.onCreateDevice(dev, code)
                        );
                    },

                    onCreateDevice(deviceObj, code) {
                        if (!deviceObj) {
                            this.isBusy = false;
                            this.isConnected = false;
                            this.healthBadge = 'offline';
                            this.printerStatus = 'createDevice failed';
                            this.log('createDevice failed: ' + String(code));
                            return;
                        }
                        this.printer = deviceObj;
                        this.isBusy = false;
                        this.isConnected = true;
                        this.healthBadge = 'online';
                        this.printerStatus = 'Connected';
                        this.log('Printer device acquired.');
                        this.bindPrinterEvents();
                    },

                    bindDeviceEvents() {
                        if (!this.ePosDev) return;
                        this.ePosDev.onreconnecting = () => {
                            this.healthBadge = 'warning';
                            this.printerStatus = 'Reconnecting...';
                            this.log('onreconnecting');
                        };
                        this.ePosDev.onreconnect = () => {
                            this.healthBadge = 'online';
                            this.printerStatus = 'Reconnected';
                            this.log('onreconnect');
                        };
                        this.ePosDev.ondisconnect = () => {
                            this.healthBadge = 'offline';
                            this.printerStatus = 'Disconnected';
                            this.isConnected = false;
                            this.log('ondisconnect');
                        };
                    },

                    bindPrinterEvents() {
                        if (!this.printer) return;
                        this.printer.onreceive = (res) => {
                            const ok = !!(res && res.success);
                            const code = res && res.code != null ? res.code : 'UNKNOWN';
                            const st = Number((res && res.status) || 0);
                            this.healthBadge = ok ? 'online' : 'warning';
                            this.printerStatus = ok ? 'Print acknowledged' : 'Print failed';
                            this.log('onreceive success=' + ok + ' code=' + code + ' status=0x' + st.toString(16));
                            this.decodeStatus(st).forEach((x) => this.log('  status: ' + x));
                        };
                        this.printer.onstatuschange = (status) => {
                            const s = Number(status || 0);
                            const parts = this.decodeStatus(s);
                            this.healthBadge = parts.length ? 'warning' : 'online';
                            this.printerStatus = parts.length ? parts.join(', ') : 'Online';
                            this.log('onstatuschange 0x' + s.toString(16) + ' ' + (parts.join(' | ') || 'OK'));
                        };
                        this.printer.ononline = () => {
                            this.healthBadge = 'online';
                            this.printerStatus = 'Online';
                            this.log('ononline');
                        };
                        this.printer.onoffline = () => {
                            this.healthBadge = 'offline';
                            this.printerStatus = 'Offline';
                            this.log('onoffline');
                        };
                        this.printer.oncoveropen = () => {
                            this.healthBadge = 'warning';
                            this.printerStatus = 'Cover open';
                            this.log('oncoveropen');
                        };
                        this.printer.onpaperend = () => {
                            this.healthBadge = 'warning';
                            this.log('onpaperend');
                        };
                        this.printer.onpaperout = () => {
                            this.healthBadge = 'warning';
                            this.log('onpaperout');
                        };
                    },

                    testPrint() {
                        if (!this.printer || !this.isConnected) {
                            this.log('Test print blocked: not connected.');
                            return;
                        }
                        try {
                            const p = this.printer;
                            const now = new Date();
                            this.log('Sending test print...');
                            p.addTextAlign(p.ALIGN_CENTER);
                            p.addTextStyle(false, false, true, p.COLOR_1);
                            p.addTextDouble(true, true);
                            p.addText('DIAGNOSTIC PRINT SUCCESS\n');
                            p.addTextDouble(false, false);
                            p.addTextStyle(false, false, false, p.COLOR_1);
                            p.addFeedLine(1);
                            p.addText('TIME: ' + now.toISOString() + '\n');
                            p.addText('TARGET: ' + this.ip + ':' + this.port + '\n');
                            p.addFeedLine(2);
                            p.addCut(p.CUT_FEED);
                            p.send();
                        } catch (e) {
                            this.healthBadge = 'warning';
                            this.printerStatus = 'Test print error';
                            this.log('TEST PRINT THROW: ' + (e && e.message ? e.message : String(e)));
                        }
                    },

                    disconnect() {
                        this.safeDisconnect();
                        this.healthBadge = 'offline';
                        this.printerStatus = 'Disconnected (manual)';
                        this.log('Disconnected by operator.');
                    },

                    safeDisconnect() {
                        try {
                            if (this.ePosDev && typeof this.ePosDev.disconnect === 'function') {
                                this.ePosDev.disconnect();
                            }
                        } catch (e) {
                            this.log('disconnect warning: ' + (e && e.message ? e.message : String(e)));
                        } finally {
                            this.printer = null;
                            this.ePosDev = null;
                            this.isConnected = false;
                            this.isBusy = false;
                        }
                    },
                };
            }
        </script>
    @endpush
</x-filament-panels::page>
