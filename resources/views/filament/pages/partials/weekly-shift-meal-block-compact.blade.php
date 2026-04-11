@php
    /** @var array{assignments: list<array<string, mixed>>, counts: array{kitchen: int, hall: int, other: int}, live_extras?: list<array<string, mixed>>} $block */
    $counts = $block['counts'] ?? ['kitchen' => 0, 'hall' => 0, 'other' => 0];
    $assignments = $block['assignments'] ?? [];
    $liveExtras = $block['live_extras'] ?? [];
    $isToday = $isToday ?? false;
    $mealTint = $mealTint ?? 'amber';
@endphp

@if(count($assignments) === 0 && count($liveExtras) === 0)
    <p class="px-1 py-2 text-center font-mono text-[10px] text-gray-500 dark:text-gray-400">—</p>
@else
    <div class="min-w-0">
        @if($counts['kitchen'] + $counts['hall'] + $counts['other'] > 0)
            <div @class([
                'flex flex-wrap items-center gap-x-1 gap-y-0 border-b px-1 py-0.5 font-mono text-[9px] font-bold leading-none text-gray-950 dark:text-sky-100',
                'border-sky-500/35 dark:border-sky-400/25',
                'bg-amber-200/50 dark:bg-amber-950/50' => $mealTint === 'amber',
                'bg-indigo-200/50 dark:bg-indigo-950/50' => $mealTint === 'indigo',
            ])>
                @if($mealTint === 'amber')
                    <x-filament::icon icon="heroicon-m-sun" class="h-3 w-3 shrink-0 text-amber-700 dark:text-amber-400" />
                @else
                    <x-filament::icon icon="heroicon-m-moon" class="h-3 w-3 shrink-0 text-indigo-700 dark:text-indigo-300" />
                @endif
                @if($counts['kitchen'] > 0)
                    <span class="inline-flex items-center gap-1 text-red-700 dark:text-red-300" title="Kitchen">
                        <span class="text-[9px] font-black tabular-nums">Kit:{{ $counts['kitchen'] }}</span>
                    </span>
                @endif
                @if($counts['hall'] > 0)
                    <span class="inline-flex items-center gap-0.5 text-emerald-800 dark:text-emerald-300" title="Hall">
                        <span class="text-[9px] font-black tabular-nums">Ser:{{ $counts['hall'] }}</span>
                    </span>
                @endif
                @if($counts['other'] > 0)
                    <span class="inline-flex items-center gap-0.5 text-gray-700 dark:text-gray-300" title="Autre">
                        <span class="text-[9px] font-black tabular-nums">Mgt:{{ $counts['other'] }}</span>
                    </span>
                @endif
            </div>
        @endif
        <ul @class([
            'divide-y font-mono text-[10px] leading-snug text-gray-950 dark:text-sky-50',
            'divide-amber-500/30 dark:divide-amber-500/20' => $mealTint === 'amber',
            'divide-indigo-500/30 dark:divide-indigo-500/20' => $mealTint === 'indigo',
        ])>
            @foreach($assignments as $row)
                @php
                    $shift = $row['shift'];
                    $end = $shift[1] ?? $shift[0] ?? '';
                    $ls = $row['live_status'] ?? null;
                @endphp
                <li wire:key="meal-compact-assign-{{ $mealTint }}-{{ $row['staff']->id }}-{{ $loop->index }}" class="flex min-w-0 items-center gap-0.5 px-1 py-0.5">
                    @if($isToday && $ls && $ls !== 'none')
                        <span class="shrink-0 select-none" title="{{ __('hq.weekly_title_punch', [], 'fr') }}">{{ \App\Filament\Pages\WeeklyShiftSchedule::liveStatusIcon($ls) }}</span>
                    @endif
                    @include('filament.pages.partials.weekly-shift-staff-role-icon', ['staff' => $row['staff'], 'class' => 'h-3 w-3 shrink-0 text-gray-700 dark:text-gray-300'])
                    <span class="min-w-0 flex-1 truncate text-xs font-medium" title="{{ $row['staff']->name }}">{{ $row['staff']->name }}</span>
                    <span class="inline-flex shrink-0 items-center rounded-full bg-gray-100 px-1.5 py-0 font-mono text-[9px] font-semibold tabular-nums text-gray-800 dark:bg-gray-800 dark:text-gray-100">{{ $shift[0] }}–{{ $end }}</span>
                </li>
            @endforeach
            @foreach($liveExtras as $extra)
                <li wire:key="meal-compact-extra-{{ $mealTint }}-{{ $extra['staff']->id }}-{{ $loop->index }}" class="flex min-w-0 items-center gap-0.5 bg-rose-100/60 px-1 py-0.5 text-rose-950 dark:bg-rose-950/40 dark:text-rose-100">
                    <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-3 w-3 shrink-0 text-rose-700 dark:text-rose-300" />
                    @include('filament.pages.partials.weekly-shift-staff-role-icon', ['staff' => $extra['staff'], 'class' => 'h-3 w-3 shrink-0 text-rose-700 dark:text-rose-200'])
                    <span class="min-w-0 flex-1 truncate text-xs font-medium" title="{{ $extra['staff']->name }}">{{ $extra['staff']->name }}</span>
                    <span class="inline-flex shrink-0 items-center rounded-full bg-white/80 px-1.5 py-0 font-mono text-[9px] font-semibold tabular-nums text-rose-950 dark:bg-gray-900/85 dark:text-rose-100">{{ $extra['in_at']?->format('H:i') ?? '—' }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
