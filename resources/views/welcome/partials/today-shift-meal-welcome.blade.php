{{-- welcome 専用: Filament 非依存（Vite ビルドの Tailwind） --}}
@php
    /** @var array{assignments: list<array<string, mixed>>, counts: array{kitchen: int, hall: int, other: int}, live_extras?: list<array<string, mixed>>} $block */
    $counts = $block['counts'] ?? ['kitchen' => 0, 'hall' => 0, 'other' => 0];
    $assignments = $block['assignments'] ?? [];
    $liveExtras = $block['live_extras'] ?? [];
    $mealTint = $mealTint ?? 'amber';
@endphp

@if(count($assignments) === 0 && count($liveExtras) === 0)
    <p class="px-1 py-2 text-center font-mono text-[10px] text-gray-500 dark:text-gray-400">—</p>
@else
    <div class="min-w-0">
        @if($counts['kitchen'] + $counts['hall'] + $counts['other'] > 0)
            <div @class([
                'flex flex-wrap items-center gap-x-1 gap-y-0 border-b border-sky-500/35 px-1 py-0.5 font-mono text-[9px] font-bold leading-none text-gray-950 dark:text-gray-100',
                'bg-amber-200/50 dark:bg-amber-950/40' => $mealTint === 'amber',
                'bg-indigo-200/50 dark:bg-indigo-950/40' => $mealTint === 'indigo',
            ])>
                @if($mealTint === 'amber')
                    <span class="text-[10px]" title="Lunch · AM" aria-hidden="true">☀️</span>
                @else
                    <span class="text-[10px]" title="Dinner · PM" aria-hidden="true">🌙</span>
                @endif
                @if($counts['kitchen'] > 0)
                    <span class="inline-flex items-center gap-1 text-red-800 dark:text-red-300" title="Kitchen">
                        <span class="text-[9px] font-black tabular-nums">Kit:{{ $counts['kitchen'] }}</span>
                    </span>
                @endif
                @if($counts['hall'] > 0)
                    <span class="inline-flex items-center gap-0.5 text-emerald-900 dark:text-emerald-300" title="Hall">
                        <span class="text-[9px] font-black tabular-nums">Ser:{{ $counts['hall'] }}</span>
                    </span>
                @endif
                @if($counts['other'] > 0)
                    <span class="inline-flex items-center gap-0.5 text-gray-800 dark:text-gray-200" title="Autre">
                        <span class="text-[9px] font-black tabular-nums">Mgt:{{ $counts['other'] }}</span>
                    </span>
                @endif
            </div>
        @endif
        <ul @class([
            'divide-y font-mono text-[10px] leading-snug text-gray-950 dark:text-gray-100',
            'divide-amber-500/30 dark:divide-amber-500/20' => $mealTint === 'amber',
            'divide-indigo-500/30 dark:divide-indigo-500/20' => $mealTint === 'indigo',
        ])>
            @foreach($assignments as $row)
                @php
                    $shift = $row['shift'];
                    $end = $shift[1] ?? $shift[0] ?? '';
                    $ls = $row['live_status'] ?? null;
                @endphp
                <li class="flex min-w-0 items-center gap-0.5 px-1 py-0.5">
                    @if($ls && $ls !== 'none')
                        <span class="shrink-0 select-none" title="打刻">{{ \App\Services\WeeklyShiftGridService::liveStatusIcon($ls) }}</span>
                    @endif
                    <span class="shrink-0 text-[10px]" aria-hidden="true">{{ \App\Services\WeeklyShiftGridService::staffRoleEmoji($row['staff']) }}</span>
                    <span class="min-w-0 flex-1 truncate text-xs font-medium text-gray-900 dark:text-gray-100" title="{{ $row['staff']->name }}">{{ $row['staff']->name }}</span>
                    <span class="inline-flex shrink-0 items-center rounded-full bg-gray-100 px-1.5 py-0 font-mono text-[10px] font-semibold tabular-nums text-gray-800 dark:bg-gray-800 dark:text-gray-100">{{ $shift[0] }}–{{ $end }}</span>
                </li>
            @endforeach
            @foreach($liveExtras as $extra)
                <li class="flex min-w-0 items-center gap-0.5 bg-rose-100/80 px-1 py-0.5 text-rose-950 dark:bg-rose-950/50 dark:text-rose-100">
                    <span class="shrink-0 text-[10px]" title="Extra" aria-hidden="true">⚠️</span>
                    <span class="shrink-0 text-[10px]" aria-hidden="true">{{ \App\Services\WeeklyShiftGridService::staffRoleEmoji($extra['staff']) }}</span>
                    <span class="min-w-0 flex-1 truncate text-xs font-medium" title="{{ $extra['staff']->name }}">{{ $extra['staff']->name }}</span>
                    <span class="inline-flex shrink-0 items-center rounded-full bg-white/90 px-1.5 py-0 font-mono text-[10px] font-semibold tabular-nums text-rose-950 dark:bg-gray-900/90 dark:text-rose-100">{{ $extra['in_at']?->format('H:i') ?? '—' }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
