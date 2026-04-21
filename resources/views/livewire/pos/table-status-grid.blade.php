@php
    $grouped = $this->groupedTiles;
    $customerTiles = $grouped['customer'];
@endphp

<div class="w-full min-h-0 min-w-0">
    @if (! $isPollingPaused)
        <div
            wire:poll.10s="loadTiles"
            class="hidden"
            aria-hidden="true"
        ></div>
    @endif

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
                class="grid w-full min-w-0 grid-cols-5 content-start justify-items-stretch gap-1 sm:gap-1.5"
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
                    @endphp
                    <div
                        class="w-full min-w-0"
                        wire:key="table-{{ $tid }}"
                    >
                        <button
                            type="button"
                            wire:click="openTableContext({{ $tid }}, {{ $sid }})"
                            @class([
                                'flex w-full touch-manipulation flex-col rounded-md border-2 p-0 py-[2px] text-left text-[10px] font-bold leading-none transition active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-sky-500 sm:text-xs',
                                'ring-2 ring-sky-500 ring-offset-1 ring-offset-white dark:ring-offset-gray-900' => $isSelected,
                            ])
                            data-ui-status="{{ $tile['uiStatus'] ?? 'free' }}"
                            data-category="{{ $tile['category'] ?? '' }}"
                        >
                            <div
                                class="box-border flex min-h-0 min-w-0 flex-col justify-center gap-0 overflow-hidden rounded-sm px-1 py-[2px] sm:px-1.5 {{ $this->tileSurfaceClasses($tile) }}"
                            >
                                <div class="line-clamp-1 text-[10px] font-extrabold leading-tight sm:text-xs {{ $titleTone }}">
                                    @if ($tile['restaurantTableName'] !== '')
                                        {{ $tile['restaurantTableName'] }}
                                    @else
                                        {{ __('pos.table_name_fallback', ['id' => $tid]) }}
                                    @endif
                                </div>
                                <div class="line-clamp-1 text-[9px] font-semibold leading-tight sm:text-[10px] {{ $metaTone }}">
                                    {{ trans_choice('pos.tile_order_count', $orderCount, ['count' => $orderCount]) }}
                                </div>
                                <div class="line-clamp-1 text-[10px] font-extrabold tabular-nums leading-tight sm:text-xs {{ $titleTone }}">
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
