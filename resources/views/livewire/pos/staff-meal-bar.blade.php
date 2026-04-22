<div @class([
    'w-full shrink-0',
    'min-w-0 max-w-full' => $inlineInFooter,
])>
    @if ($shopId > 0 && ! $isPollingPaused)
        <div
            wire:poll.10s="loadStaffTiles"
            class="hidden"
            aria-hidden="true"
        ></div>
    @endif

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
            class="flex w-full min-w-0 max-w-full flex-nowrap items-center justify-start gap-0.5 overflow-x-auto overflow-y-visible py-0.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
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
                @endphp
                <button
                    type="button"
                    wire:click="openTableContext({{ $tid }}, {{ $sid > 0 ? $sid : 'null' }})"
                    wire:key="staff-meal-{{ $tid }}"
                    x-data="{ flash: false, flashTimer: null }"
                    x-on:click="
                        flash = true;
                        if (flashTimer) clearTimeout(flashTimer);
                        flashTimer = setTimeout(() => { flash = false; flashTimer = null }, 450);
                    "
                    x-bind:class="{ 'pos-tile-select-flash': flash }"
                    data-ui-status="{{ $tile['uiStatus'] ?? 'free' }}"
                    data-category="staff"
                    title="@if ($label !== null){{ $label }} — @endif#{{ $tid }}{{ (string)($tile['restaurantTableName'] ?? '') !== '' ? ' ' . (string) $tile['restaurantTableName'] : '' }}"
                    @class([
                        'relative z-0 inline-flex min-h-6 max-w-full shrink-0 items-center justify-center gap-0.5 whitespace-nowrap rounded border px-1 py-0.5 text-[9px] leading-tight shadow-sm transition duration-150 ease-out focus:outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600 dark:focus-visible:outline-sky-300 '.$staffSurface,
                        '!z-10 !scale-110 ring-4 ring-inset ring-amber-600 dark:ring-amber-400' => $isStaffSel,
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
