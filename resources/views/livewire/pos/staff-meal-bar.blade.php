<div @class([
    'w-full max-w-full shrink-0 max-md:w-full',
    'min-w-0 max-w-full' => $inlineInFooter,
])>
    <div
        @class([
            'shrink-0 rounded border border-slate-200/90 bg-slate-100/90 px-1.5 py-0.5 dark:border-slate-600/50 dark:bg-slate-800/50',
            'min-w-0' => $inlineInFooter,
            'border-t border-slate-200/80 px-2 py-0.5 dark:border-slate-600/40' => ! $inlineInFooter,
            'hidden' => ! $this->showStaffMealBar,
        ])
        data-staff-meal-bar="true"
        role="region"
        aria-label="{{ __('pos.staff_tile_heading') }}"
        aria-expanded="{{ $staffDoorOpen ? 'true' : 'false' }}"
        aria-hidden="{{ $this->showStaffMealBar ? 'false' : 'true' }}"
    >
        <div
            class="flex max-w-full items-center justify-between gap-1 px-0.5 pb-0.5 max-md:flex md:hidden"
        >
            <span class="truncate text-[10px] font-extrabold uppercase leading-none tracking-wide text-gray-950 dark:text-gray-100">
                {{ __('pos.staff_tile_heading') }}
            </span>
            <span class="shrink-0 text-[10px] font-semibold text-slate-500 dark:text-slate-400" aria-hidden="true">↔</span>
        </div>
        <div
            class="max-md:relative max-md:w-full max-md:rounded-md max-md:bg-white/95 max-md:ring-1 max-md:ring-slate-300/70 max-md:shadow-[inset_-10px_0_10px_-8px_rgba(15,23,42,0.06)] max-md:dark:bg-slate-900/95 max-md:dark:ring-slate-600/70 flex w-full min-w-0 max-w-full flex-nowrap items-center justify-start gap-0.5 overflow-x-auto overflow-y-visible py-0.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            x-data="{
                optimisticStaffTableId: null,
                peerStaffTid: null,
                clickStaffTile(tid, tableName) {
                    const token = Date.now()
                    this.optimisticStaffTableId = tid;
                    window.dispatchEvent(
                        new CustomEvent('show-local-skeleton', {
                            detail: {
                                tid: tid,
                                token: token,
                                tableName: typeof tableName === 'string' ? tableName : '',
                            },
                            bubbles: true,
                        }),
                    )
                },
            }"
            x-on:pos-floor-peer-sync.window="peerStaffTid = ($event.detail && $event.detail.staffFloorTid != null) ? Number($event.detail.staffFloorTid) : null"
            x-on:pos-tile-interaction-ended.window="optimisticStaffTableId = null; peerStaffTid = null"
        >
            <span
                class="inline-flex shrink-0 pl-0.5 text-slate-500 dark:text-slate-400"
                title="{{ __('pos.staff_tile_heading') }}"
            >
                <span class="sr-only">{{ __('pos.staff_tile_heading') }}</span>
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </span>
            @if (count($staffTiles) > 0)
            @foreach ($staffTiles as $tile)
                @php
                    $tid = (int) $tile['restaurantTableId'];
                    $sid = (int) ($tile['activeTableSessionId'] ?? 0);
                    $sn = $tile['activeSessionStaffName'] ?? null;
                    $label = is_string($sn) && trim($sn) !== '' ? trim($sn) : null;
                    $isStaffSel = $this->floorSelectedStaffTableId !== null && $this->floorSelectedStaffTableId === $tid;
                    $staffSurface = $this->tileSurfaceClasses($tile);
                    $previewTableName =
                        (string) ($tile['restaurantTableName'] ?? '') !== ''
                            ? (string) $tile['restaurantTableName']
                            : (string) __('pos.table_name_fallback', ['id' => $tid]);
                @endphp
                <button
                    type="button"
                    wire:click="openTableContext({{ $tid }}, {{ $sid > 0 ? $sid : 'null' }})"
                    wire:key="staff-meal-{{ $tid }}"
                    @pointerdown="clickStaffTile({{ $tid }}, @js($previewTableName))"
                    x-bind:class="{
                        '!z-10 !scale-110 ring-4 ring-inset ring-amber-600 transition-none duration-0 ease-linear will-change-transform dark:ring-amber-400': @js($isStaffSel) || optimisticStaffTableId === {{ $tid }} || peerStaffTid === {{ $tid }},
                    }"
                    data-ui-status="{{ $tile['uiStatus'] ?? 'free' }}"
                    data-category="staff"
                    title="@if ($label !== null){{ $label }} — @endif#{{ $tid }}{{ (string)($tile['restaurantTableName'] ?? '') !== '' ? ' ' . (string) $tile['restaurantTableName'] : '' }}"
                    @class([
                        'relative z-0 inline-flex min-h-6 max-w-full shrink-0 items-center justify-center gap-0.5 whitespace-nowrap rounded border px-1 py-0.5 text-[9px] leading-tight shadow-sm focus:outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600 dark:focus-visible:outline-sky-300 '.$staffSurface,
                        'font-medium text-slate-800 dark:text-slate-100' => ! $isStaffSel,
                        'font-black' => $isStaffSel,
                    ])
                >
                    @if ($label !== null)
                        <span @class(['max-w-[6.5rem] truncate', 'font-semibold' => ! $isStaffSel, 'font-black' => $isStaffSel])>{{ $label }}</span>
                    @else
                        <span @class(['tabular-nums', 'font-semibold' => ! $isStaffSel, 'font-black' => $isStaffSel])>#{{ $tid }}</span>
                    @endif
                </button>
            @endforeach
            @endif
        </div>
    </div>
</div>
