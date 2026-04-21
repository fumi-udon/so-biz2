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
            class="flex w-full min-w-0 max-w-full flex-nowrap items-center justify-start gap-0.5 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
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
                @endphp
                <button
                    type="button"
                    wire:click="openTableContext({{ $tid }}, {{ $sid }})"
                    wire:key="staff-meal-{{ $tid }}"
                    data-ui-status="{{ $tile['uiStatus'] ?? 'free' }}"
                    data-category="staff"
                    title="#{{ $tid }}{{ (string)($tile['restaurantTableName'] ?? '') !== '' ? ' ' . (string) $tile['restaurantTableName'] : '' }}"
                    class="inline-flex min-h-6 max-w-full shrink-0 items-center justify-center gap-0.5 whitespace-nowrap rounded border px-1 py-0.5 text-[9px] font-medium leading-tight text-slate-800 shadow-sm transition focus:outline-none focus:ring-1 focus:ring-slate-400 focus:ring-offset-1 focus:ring-offset-slate-100 dark:text-slate-100 dark:focus:ring-slate-500 dark:focus:ring-offset-slate-800 {{ $this->tileSurfaceClasses($tile) }}"
                >
                    <span class="tabular-nums">#{{ $tid }}</span>
                </button>
            @endforeach
            @endif
        </div>
    </div>
</div>
