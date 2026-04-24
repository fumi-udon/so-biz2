<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b1120">
    <title>KDS · {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        html, body { background-color: #0b1120; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="h-[100dvh] max-h-[100dvh] overflow-hidden overscroll-none bg-slate-950 text-slate-100 antialiased">
    @isset($slot)
        {{ $slot }}
    @else
        @yield('content')
    @endisset

    @livewireScripts
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.hook('request', ({ fail }) => {
                fail(({ status, preventDefault }) => {
                    if (status === 419 || status === 401) {
                        if (typeof preventDefault === 'function') {
                            preventDefault();
                        }
                        window.location.reload();
                    }
                });
            });
        });

        /*
         * Bistro 最適化: Echo (Pusher) → Livewire の「直列化ブリッジ」。
         *
         * - Echo は単なる "ベル" として扱う（payload は shop_id / action のみ）。
         * - Pusher が連続発火しても 300ms デバウンスで 1 回に集約。
         * - Livewire 側のメソッド呼び出しは `$wire.refreshTickets()` のみ。
         *   これにより wire:poll.10s と DOM Morph が競合しない（Livewire が
         *   リクエストを 1 本ずつ直列化するため）。
         * - Pusher 障害時は何も起こらず、wire:poll.10s が 10 秒以内に追従する。
         */
        document.addEventListener('alpine:init', () => {
            window.Alpine.data('kdsEchoBridge', (opts = {}) => ({
                shopId: Number(opts.shopId) || 0,
                kitchenIds: Array.isArray(opts.kitchenIds) ? opts.kitchenIds.map((n) => Number(n)) : [],
                hallIds: Array.isArray(opts.hallIds) ? opts.hallIds.map((n) => Number(n)) : [],
                filterStrict: Boolean(opts.filterStrict),
                showFilterConfigWarning: Boolean(opts.showFilterConfigWarning),
                columnFilterMetas: Array.isArray(opts.columnFilterMetas) ? opts.columnFilterMetas : [],
                showKitchen: true,
                showHall: true,
                pendingEchoReload: null,
                channel: null,
                privateChannel: null,
                status: 'connecting',
                pusherBound: false,

                pushStatus(next) {
                    this.status = next;
                    try {
                        this.$wire.syncRealtimeState(next);
                    } catch (e) {
                        // noop
                    }
                },

                loadKdsFilterTogglesFromStorage() {
                    if (!this.shopId) {
                        return;
                    }
                    try {
                        const raw = localStorage.getItem('kds.filters.v1.' + this.shopId);
                        if (!raw) {
                            return;
                        }
                        const p = JSON.parse(raw);
                        if (typeof p.showKitchen === 'boolean') {
                            this.showKitchen = p.showKitchen;
                        }
                        if (typeof p.showHall === 'boolean') {
                            this.showHall = p.showHall;
                        }
                    } catch (e) {
                        /* ignore */
                    }
                },

                persistKdsFilterToggles() {
                    if (!this.shopId) {
                        return;
                    }
                    try {
                        localStorage.setItem(
                            'kds.filters.v1.' + this.shopId,
                            JSON.stringify({ showKitchen: this.showKitchen, showHall: this.showHall }),
                        );
                    } catch (e) {
                        /* ignore */
                    }
                },

                ticketVisible(cat) {
                    if (!this.filterStrict) {
                        return true;
                    }
                    if (cat === null || cat === undefined) {
                        return true;
                    }
                    const c = Number(cat);
                    if (Number.isNaN(c)) {
                        return true;
                    }
                    const inK = this.kitchenIds.includes(c);
                    const inH = this.hallIds.includes(c);
                    if (!inK && !inH) {
                        return true;
                    }
                    if (!this.showKitchen && !this.showHall) {
                        return true;
                    }
                    return (this.showKitchen && inK) || (this.showHall && inH);
                },

                visibleTicketCountForColumn(idx) {
                    const meta = this.columnFilterMetas[idx] || [];
                    let n = 0;
                    for (let i = 0; i < meta.length; i++) {
                        if (this.ticketVisible(meta[i].c)) {
                            n++;
                        }
                    }
                    return n;
                },

                initEcho() {
                    this.loadKdsFilterTogglesFromStorage();
                    if (!this.shopId || typeof window.Echo === 'undefined') {
                        this.pushStatus('disconnected');
                        return;
                    }

                    try {
                        const onConfirmed = () => {
                                this.pushStatus('connected');
                                try {
                                    this.$wire.markRealtimeEventReceived();
                                } catch (e) {
                                    // noop
                                }
                                this.scheduleRefresh();
                            };
                        this.channel = window.Echo
                            .channel('pos.shop.' + this.shopId)
                            .listen('.kds.orders.confirmed', onConfirmed);
                        this.privateChannel = window.Echo
                            .private('rt.shop.' + this.shopId + '.orders')
                            .listen('.kds.orders.confirmed', onConfirmed);
                        this.bindPusherState();
                    } catch (e) {
                        // Echo 不在/接続失敗は無視（wire:poll.10s が真実の源泉）。
                        this.pushStatus('error');
                        if (window.console && console.warn) {
                            console.warn('[KDS] Echo subscription failed', e);
                        }
                    }
                },

                bindPusherState() {
                    if (this.pusherBound || !window.Echo || !window.Echo.connector || !window.Echo.connector.pusher) {
                        return;
                    }
                    const connection = window.Echo.connector.pusher.connection;
                    if (!connection || typeof connection.bind !== 'function') {
                        return;
                    }
                    this.pusherBound = true;
                    connection.bind('connecting', () => this.pushStatus('connecting'));
                    connection.bind('connected', () => this.pushStatus('connected'));
                    connection.bind('disconnected', () => this.pushStatus('disconnected'));
                    connection.bind('unavailable', () => this.pushStatus('error'));
                    connection.bind('failed', () => this.pushStatus('error'));
                    connection.bind('error', () => this.pushStatus('error'));
                },

                scheduleRefresh() {
                    if (this.pendingEchoReload) {
                        clearTimeout(this.pendingEchoReload);
                    }
                    this.pendingEchoReload = setTimeout(() => {
                        this.pendingEchoReload = null;
                        try {
                            this.$wire.refreshTickets();
                        } catch (e) {
                            // Livewire コンポーネントが未確立の瞬間は黙って捨てる。
                        }
                    }, 300);
                },

                destroy() {
                    if (this.pendingEchoReload) {
                        clearTimeout(this.pendingEchoReload);
                        this.pendingEchoReload = null;
                    }
                    try {
                        if (this.shopId && window.Echo) {
                            window.Echo.leave('pos.shop.' + this.shopId);
                            window.Echo.leave('private-rt.shop.' + this.shopId + '.orders');
                        }
                    } catch (e) { /* noop */ }
                    this.pushStatus('disconnected');
                },
            }));
        });
    </script>
</body>
</html>
