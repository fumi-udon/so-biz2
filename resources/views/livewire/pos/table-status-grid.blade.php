@php
    $grouped = $this->groupedTiles;
    $customerTiles = $grouped['customer'];
@endphp

<div class="w-full min-h-0 min-w-0">
    <div
        class="min-w-0 overflow-hidden rounded-lg border-2 border-blue-200 bg-white/95 p-1 dark:border-blue-700 dark:bg-slate-900 sm:rounded-xl"
    >
        @if (count($customerTiles) === 0)
            <p class="text-xs text-gray-700 dark:text-gray-200 sm:text-sm">
                {{ __('pos.table_status_no_tiles') }}
            </p>
        @else
            <p class="mb-1 text-[10px] font-extrabold uppercase leading-none tracking-wider text-blue-700 dark:text-blue-300 sm:text-xs">SHOP LOG NAME</p>
            <div
                class="grid w-full min-w-0 grid-cols-5 content-start justify-items-stretch gap-1 overflow-visible py-0.5 sm:gap-1.5"
                x-data="{
                    optimisticTableId: null,
                    clickTile(tid) {
                        this.optimisticTableId = tid;
                    },
                }"
                x-on:pos-tile-interaction-ended.window="optimisticTableId = null"
            >
                @foreach ($customerTiles as $tile)
                    @php
                        $tid = (int) $tile['restaurantTableId'];
                        $sid = (int) ($tile['activeTableSessionId'] ?? 0);
                        $isSelected = $selectedTableId === $tid;
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
                        $lineTitle = $isSelected ? 'font-black '.$titleTone : 'font-extrabold '.$titleTone;
                        $lineMeta = $isSelected ? 'font-black '.$metaTone : 'font-semibold '.$metaTone;
                        $lineTotal = $isSelected ? 'font-black tabular-nums '.$titleTone : 'font-extrabold tabular-nums '.$titleTone;
                    @endphp
                    <div
                        class="w-full min-w-0"
                        wire:key="table-{{ $tid }}"
                    >
                        <button
                            type="button"
                            wire:click="openTableContext({{ $tid }}, {{ $sid }})"
                            @pointerdown="clickTile({{ $tid }})"
                            @click="clickTile({{ $tid }})"
                            x-bind:class="{
                                '!z-10 !scale-110 transition-none duration-0 ease-linear will-change-transform': @js($isSelected) || optimisticTableId === {{ $tid }},
                            }"
                            @class([
                                'relative z-0 flex w-full touch-manipulation flex-col rounded-md border-2 border-transparent p-0 py-[2px] text-left text-[10px] font-bold leading-none active:scale-[0.99] focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-1 focus-visible:ring-offset-white dark:focus-visible:ring-offset-gray-900 sm:text-xs',
                            ])
                            data-ui-status="{{ $tile['uiStatus'] ?? 'free' }}"
                            data-category="{{ $tile['category'] ?? '' }}"
                        >
                            <div
                                @class([
                                    'box-border flex min-h-0 min-w-0 flex-col justify-center gap-0 overflow-hidden rounded-sm px-1 py-[2px] sm:px-1.5',
                                    $this->tileSurfaceClasses($tile),
                                ])
                                :class="{
                                    'ring-4 ring-inset ring-amber-600 transition-none duration-0 ease-linear dark:ring-amber-400': @js($isSelected) || optimisticTableId === {{ $tid }},
                                }"
                            >
                                <div class="line-clamp-1 text-[10px] leading-tight sm:text-xs {{ $lineTitle }}">
                                    @if ($tile['restaurantTableName'] !== '')
                                        {{ $tile['restaurantTableName'] }}
                                    @else
                                        {{ __('pos.table_name_fallback', ['id' => $tid]) }}
                                    @endif
                                </div>
                                <div class="line-clamp-1 text-[9px] leading-tight sm:text-[10px] {{ $lineMeta }}">
                                    {{ trans_choice('pos.tile_order_count', $orderCount, ['count' => $orderCount]) }}
                                </div>
                                <div class="line-clamp-1 text-[10px] leading-tight sm:text-xs {{ $lineTotal }}">
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
