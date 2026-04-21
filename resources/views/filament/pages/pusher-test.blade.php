<x-filament-panels::page>
    <div
        class="space-y-3"
        x-data="{
            shopId: {{ (int) $this->shopId }},
            status: 'idle',
            lastReceivedAt: null,
            lastPayload: null,
            init() {
                if (!window.Echo || this.shopId < 1) {
                    this.status = 'echo-missing';
                    return;
                }
                this.status = 'subscribing';
                try {
                    window.Echo.channel('pos.shop.' + this.shopId)
                        .listen('.kds.orders.confirmed', (payload) => {
                            this.lastReceivedAt = new Date().toLocaleTimeString('en-GB', { hour12: false });
                            this.lastPayload = payload;
                            this.status = 'success';
                        });
                    this.status = 'subscribed';
                } catch (e) {
                    this.status = 'subscribe-error';
                }
            },
        }"
        x-init="init()"
    >
        <div class="rounded-lg border border-gray-200 bg-white p-4 text-gray-950 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
            <h2 class="text-sm font-bold">Pusher debug endpoint</h2>
            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                Channel: <span class="font-mono">pos.shop.{{ (int) $this->shopId }}</span>
                @if ($this->shopName !== '')
                    · Shop: {{ $this->shopName }}
                @endif
            </p>
            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                Status:
                <span class="font-semibold" x-text="status"></span>
            </p>
            @if ($this->lastDispatchAt !== null)
                <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                    Last dispatch: <span class="font-mono">{{ $this->lastDispatchAt }}</span>
                </p>
            @endif
        </div>

        <div class="flex items-center gap-2">
            <x-filament::button wire:click="pingPusher">
                Ping Pusher
            </x-filament::button>
        </div>

        <div class="rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-emerald-900 dark:border-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-200">
            <p class="text-sm font-bold">
                <span x-show="lastReceivedAt">SUCCESS: received at <span class="font-mono" x-text="lastReceivedAt"></span></span>
                <span x-show="!lastReceivedAt">Waiting for event...</span>
            </p>
            <pre class="mt-2 overflow-x-auto text-xs" x-show="lastPayload" x-text="JSON.stringify(lastPayload, null, 2)"></pre>
        </div>
    </div>
</x-filament-panels::page>

