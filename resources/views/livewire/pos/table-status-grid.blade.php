@php
    $grouped = $this->groupedTiles;
    $customerTiles = $grouped['customer'];
@endphp

<div
    class="h-full w-full max-w-full min-h-0 min-w-0 max-md:max-h-[45vh] max-md:overflow-y-auto max-md:pt-4"
    x-data="{
        selectedTableId: null,
        optimisticTableId: null,
        clickTile(tid, tableName, sessionId) {
            const token = Date.now()
            this.optimisticTableId = null;
            this.$nextTick(() => {
                this.optimisticTableId = tid;
            })
            const sid =
                typeof sessionId === 'number' &&
                Number.isFinite(sessionId) &&
                sessionId > 0
                    ? sessionId
                    : null
            window.dispatchEvent(
                new CustomEvent('show-local-skeleton', {
                    detail: {
                        tid: tid,
                        token: token,
                        tableName: typeof tableName === 'string' ? tableName : '',
                        sessionId: sid,
                    },
                    bubbles: true,
                }),
            )
        },
    }"
    x-on:pos-tile-interaction-ended.window="optimisticTableId = null; selectedTableId = null"
    x-on:pos-customer-grid-clear-selection.window="selectedTableId = null"
>
    <div
        class="flex h-full min-w-0 flex-col overflow-hidden rounded-lg border-2 border-blue-200 bg-white/95 p-1 dark:border-blue-700 dark:bg-slate-900 sm:rounded-xl"
    >
        @if (count($customerTiles) === 0)
            <p class="text-xs text-gray-700 dark:text-gray-200 sm:text-sm">
                {{ __('pos.table_status_no_tiles') }}
            </p>
        @else
<div
                class="grid h-full min-h-0 w-full min-w-0 max-w-full auto-rows-fr grid-cols-3 content-stretch items-stretch justify-items-stretch gap-[8px] overflow-visible py-0.5 sm:grid-cols-3 sm:gap-[8px]"
            >
                @foreach ($customerTiles as $tile)
                    @php
                        $tid = (int) $tile['restaurantTableId'];
                        $sid = (int) ($tile['activeTableSessionId'] ?? 0);
                        $status = (string) ($tile['uiStatus'] ?? 'free');
                        $orderCount = (int) ($tile['relevantPosOrderCount'] ?? 0);
                        $totalMinor = (int) ($tile['sessionTotalMinor'] ?? 0);
                        $totalLabel = \App\Support\MenuItemMoney::formatMinorForDisplay($totalMinor);
                        $titleTone = in_array($status, ['alert', 'pending'], true)
                            ? 'text-white'
                            : 'text-slate-900 dark:text-white';
                        $metaTone = in_array($status, ['alert', 'pending'], true)
                            ? 'text-white/90'
                            : 'text-slate-700 dark:text-slate-200';
                        $lineTitle = 'font-extrabold '.$titleTone;
                        $lineMeta = 'font-semibold '.$metaTone;
                        $lineTotal = 'font-extrabold tabular-nums '.$titleTone;
                        $previewTableName =
                            (string) ($tile['restaurantTableName'] ?? '') !== ''
                                ? (string) $tile['restaurantTableName']
                                : (string) __('pos.table_name_fallback', ['id' => $tid]);
                    @endphp
                    <div
                        class="h-full w-full min-w-0"
                        wire:key="table-{{ $tid }}"
                    >
                        <button
                            type="button"
                            @click="
                                selectedTableId = {{ $tid }};
                                window.dispatchEvent(new CustomEvent('pos-tile-interaction-started', { bubbles: true }));
                                if (window.Livewire && typeof window.Livewire.dispatchTo === 'function') {
                                    window.Livewire.dispatchTo('pos.table-action-host', 'pos-action-host-opened', {
                                        tableId: {{ $tid }},
                                        sessionId: {{ $sid > 0 ? $sid : 'null' }},
                                        tableName: @js((string) ($tile['restaurantTableName'] ?? '')),
                                    });
                                }
                                if (window.matchMedia('(max-width: 767px)').matches) {
                                    const pane = document.querySelector('[data-pos-order-pane]');
                                    if (pane && typeof pane.scrollIntoView === 'function') {
                                        pane.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                    }
                                }
                            "
                            @pointerdown="clickTile({{ $tid }}, @js($previewTableName), @js($sid > 0 ? $sid : null))"
                            x-bind:class="{
                                '!z-10 !scale-110 transition-none duration-0 ease-linear will-change-transform': optimisticTableId === {{ $tid }},
                            }"
                            @class([
                                'relative z-0 flex h-full min-h-[96px] w-full touch-manipulation flex-col rounded-md border-2 border-transparent p-0 py-[13px] text-left text-[10px] font-bold leading-none active:scale-[0.99] focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-1 focus-visible:ring-offset-white dark:focus-visible:ring-offset-gray-900 sm:min-h-[104px] sm:text-xs',
                            ])
                            data-ui-status="{{ $tile['uiStatus'] ?? 'free' }}"
                            data-category="{{ $tile['category'] ?? '' }}"
                        >
                            <div
                                @class([
                                    'box-border flex h-full min-h-0 min-w-0 flex-col justify-center gap-0 overflow-hidden rounded-sm px-1.5 py-[7px] sm:px-2',
                                    $this->tileSurfaceClasses($tile),
                                ])
                                :class="{
                                    'ring-4 ring-inset ring-amber-600 transition-none duration-0 ease-linear dark:ring-amber-400': optimisticTableId === {{ $tid }},
                                }"
                            >
                                <div
                                    class="line-clamp-1 text-[10px] leading-tight sm:text-xs {{ $lineTitle }}"
                                    :class="selectedTableId === {{ $tid }} ? '!font-black' : ''"
                                >
                                    @if ($tile['restaurantTableName'] !== '')
                                        {{ $tile['restaurantTableName'] }}
                                    @else
                                        {{ __('pos.table_name_fallback', ['id' => $tid]) }}
                                    @endif
                                </div>
                                <div
                                    class="line-clamp-1 text-[9px] leading-tight sm:text-[10px] {{ $lineMeta }}"
                                    :class="selectedTableId === {{ $tid }} ? '!font-black' : ''"
                                >
                                    {{ trans_choice('pos.tile_order_count', $orderCount, ['count' => $orderCount]) }}
                                </div>
                                <div
                                    class="line-clamp-1 text-[10px] leading-tight sm:text-xs {{ $lineTotal }}"
                                    :class="selectedTableId === {{ $tid }} ? '!font-black tabular-nums' : ''"
                                >
                                    {{ $totalLabel }}
                                </div>
                            </div>
                        </button>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Takeaway (200–219): UI frozen — section removed (restore when Takeout ships). --}}
    </div>

    {{-- Staff meal (100–104): parent (`table-dashboard` footer) --}}
    {{-- Takeout+ button: UI frozen — restore with Takeaway section above. --}}
</div>
