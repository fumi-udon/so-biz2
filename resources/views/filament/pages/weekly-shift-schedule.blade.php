@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Staff> $staffs */
    /** @var array<string, array{lunch: array, dinner: array}> $shiftGrid */
    /** @var array<string, string> $dayLabels */
    /** @var string $todayDayKey */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Attendance> $attendancesToday */
    /** @var array<int, array{lunch: string, dinner: string}> $liveByStaff */
@endphp

<x-filament-panels::page>
    <div wire:poll.30s class="space-y-6">
    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/5">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr>
                    <th class="px-4 py-3 font-semibold text-gray-900 dark:text-white whitespace-nowrap">スタッフ名</th>
                    @foreach($dayLabels as $dayKey => $dayLabel)
                        <th @class([
                            'px-4 py-3 font-semibold text-gray-900 dark:text-white text-center whitespace-nowrap',
                            'bg-blue-50/80 dark:bg-blue-950/30 ring-1 ring-inset ring-blue-200/60 dark:ring-blue-500/20' => $dayKey === $todayDayKey,
                        ])>
                            @if($dayKey === $todayDayKey)
                                <span class="mr-1 inline-block h-1.5 w-1.5 rounded-full bg-blue-500 align-middle" title="本日（営業日）"></span>
                            @endif
                            {{ $dayLabel }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                @foreach($staffs as $staff)
                <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition duration-75">
                    <td class="px-4 py-4 font-medium text-gray-900 dark:text-white whitespace-nowrap">
                        {{ $staff->name }}
                    </td>
                    @foreach($dayLabels as $dayKey => $dayLabel)
                        @php
                            $dayShift = $staff->fixed_shifts[$dayKey] ?? null;
                            $lunch = is_array($dayShift) ? ($dayShift['lunch'] ?? null) : null;
                            $dinner = is_array($dayShift) ? ($dayShift['dinner'] ?? null) : null;
                            $isTodayCol = $dayKey === $todayDayKey;
                            $att = $isTodayCol ? $attendancesToday->get($staff->id) : null;
                            $live = $liveByStaff[$staff->id] ?? ['lunch' => 'none', 'dinner' => 'none'];
                        @endphp
                        <td @class([
                            'px-4 py-4 text-center align-top',
                            'bg-blue-50/50 dark:bg-blue-950/20' => $isTodayCol,
                        ])>
                            @if(!$lunch && !$dinner)
                                @if($isTodayCol && ($live['lunch'] === 'extra' || $live['dinner'] === 'extra'))
                                    <div class="flex flex-col gap-y-1.5 items-center">
                                        @if($live['lunch'] === 'extra')
                                            <span class="inline-flex items-center gap-1 rounded-md bg-rose-50 px-2 py-1 text-xs font-medium text-rose-800 ring-1 ring-inset ring-rose-200/70 dark:bg-rose-950/40 dark:text-rose-200 dark:ring-rose-500/30 whitespace-nowrap" title="臨時出勤">
                                                {{ \App\Filament\Pages\WeeklyShiftSchedule::liveStatusIcon('extra') }} ☀️ 臨時 {{ $att?->lunch_in_at?->format('H:i') ?? '—' }}
                                            </span>
                                        @endif
                                        @if($live['dinner'] === 'extra')
                                            <span class="inline-flex items-center gap-1 rounded-md bg-rose-50 px-2 py-1 text-xs font-medium text-rose-800 ring-1 ring-inset ring-rose-200/70 dark:bg-rose-950/40 dark:text-rose-200 dark:ring-rose-500/30 whitespace-nowrap" title="臨時出勤">
                                                {{ \App\Filament\Pages\WeeklyShiftSchedule::liveStatusIcon('extra') }} 🌙 臨時 {{ $att?->dinner_in_at?->format('H:i') ?? '—' }}
                                            </span>
                                        @endif
                                    </div>
                                @else
                                    <span class="inline-flex text-gray-400 dark:text-gray-500 text-xs font-medium bg-gray-50 dark:bg-white/5 px-2 py-1 rounded-md">
                                        Repos
                                    </span>
                                @endif
                            @else
                                <div class="flex flex-col gap-y-1.5 items-center">
                                    @if($lunch)
                                        <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-400 dark:ring-amber-400/20 whitespace-nowrap">
                                            @if($isTodayCol && ($live['lunch'] ?? 'none') !== 'none')
                                                <span class="select-none" title="打刻ステータス">{{ \App\Filament\Pages\WeeklyShiftSchedule::liveStatusIcon($live['lunch']) }}</span>
                                            @endif
                                            ☀️ {{ $lunch[0] }} - {{ $lunch[1] ?? $lunch[0] }}
                                        </span>
                                    @elseif($isTodayCol && ($live['lunch'] ?? 'none') === 'extra')
                                        <span class="inline-flex items-center gap-1 rounded-md bg-rose-50 px-2 py-1 text-xs font-medium text-rose-800 ring-1 ring-inset ring-rose-200/70 dark:bg-rose-950/40 dark:text-rose-200 whitespace-nowrap" title="臨時出勤">
                                            {{ \App\Filament\Pages\WeeklyShiftSchedule::liveStatusIcon('extra') }} ☀️ 臨時 {{ $att?->lunch_in_at?->format('H:i') ?? '—' }}
                                        </span>
                                    @endif
                                    @if($dinner)
                                        <span class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-700/10 dark:bg-indigo-400/10 dark:text-indigo-400 dark:ring-indigo-400/30 whitespace-nowrap">
                                            @if($isTodayCol && ($live['dinner'] ?? 'none') !== 'none')
                                                <span class="select-none" title="ステータス">{{ \App\Filament\Pages\WeeklyShiftSchedule::liveStatusIcon($live['dinner']) }}</span>
                                            @endif
                                            🌙 {{ $dinner[0] }} - {{ $dinner[1] ?? $dinner[0] }}
                                        </span>
                                    @elseif($isTodayCol && ($live['dinner'] ?? 'none') === 'extra')
                                        <span class="inline-flex items-center gap-1 rounded-md bg-rose-50 px-2 py-1 text-xs font-medium text-rose-800 ring-1 ring-inset ring-rose-200/70 dark:bg-rose-950/40 dark:text-rose-200 whitespace-nowrap" title="臨時出勤">
                                            {{ \App\Filament\Pages\WeeklyShiftSchedule::liveStatusIcon('extra') }} 🌙 臨時 {{ $att?->dinner_in_at?->format('H:i') ?? '—' }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="px-4 py-2 border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">日別・時間帯別 人員配置</h3>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                Role別人数（上部）・本日列は打刻ステータス: 🟢出勤済 🆘臨時 🔴遅刻/未打刻 ⚪予定前
            </p>
        </div>
        <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/5">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr>
                    <th class="px-3 py-2 font-semibold text-gray-900 dark:text-white whitespace-nowrap w-28">曜日</th>
                    <th class="px-3 py-2 font-semibold text-gray-900 dark:text-white min-w-48">☀️ ランチ (Lunch)</th>
                    <th class="px-3 py-2 font-semibold text-gray-900 dark:text-white min-w-48">🌙 ディナー (Dinner)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                @foreach($dayLabels as $dayKey => $dayLabel)
                <tr @class([
                    'hover:bg-gray-50 dark:hover:bg-white/5 transition duration-75 align-top',
                    'bg-blue-50/40 dark:bg-blue-950/25' => $dayKey === $todayDayKey,
                ])>
                    <td @class([
                        'px-3 py-2 font-medium text-gray-900 dark:text-white whitespace-nowrap border-r border-gray-200 dark:border-white/5',
                        'bg-blue-50/70 dark:bg-blue-950/30' => $dayKey === $todayDayKey,
                    ])>
                        @if($dayKey === $todayDayKey)
                            <span class="mr-1 inline-block h-1.5 w-1.5 rounded-full bg-blue-500 align-middle"></span>
                        @endif
                        {{ $dayLabel }}
                    </td>

                    @foreach (['lunch', 'dinner'] as $meal)
                        @php
                            $block = $shiftGrid[$dayKey][$meal] ?? ['assignments' => [], 'counts' => ['kitchen' => 0, 'hall' => 0, 'other' => 0], 'live_extras' => []];
                            $counts = $block['counts'];
                            $assignments = $block['assignments'];
                            $liveExtras = $block['live_extras'] ?? [];
                        @endphp
                        <td @class([
                            'px-3 py-2 align-top border-r border-gray-200 last:border-r-0 dark:border-white/5',
                            'bg-blue-50/30 dark:bg-blue-950/20' => $dayKey === $todayDayKey,
                        ])>
                            @if(count($assignments) === 0 && count($liveExtras) === 0)
                                <span class="text-xs text-gray-400 dark:text-gray-500">出勤予定なし</span>
                            @else
                                <div class="flex flex-col gap-y-2">
                                    <div class="text-[10px] font-bold pb-1 mb-0.5 border-b border-gray-200 dark:border-white/10 leading-tight flex flex-wrap gap-x-1.5 gap-y-0.5 items-baseline">
                                        @if($counts['kitchen'] > 0)
                                            <span class="text-red-600 dark:text-red-400">🔪Kit {{ $counts['kitchen'] }}</span>
                                        @endif
                                        @if($counts['hall'] > 0)
                                            <span class="text-green-600 dark:text-green-400">🍽Hal {{ $counts['hall'] }}</span>
                                        @endif
                                        @if($counts['other'] > 0)
                                            <span class="text-gray-600 dark:text-gray-400">他 {{ $counts['other'] }}</span>
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($assignments as $row)
                                            @php
                                                $cat = $row['category'];
                                                $chip = match ($cat) {
                                                    'kitchen' => 'bg-red-100 text-red-800 ring-red-200/80 dark:bg-red-950/50 dark:text-red-200 dark:ring-red-500/30',
                                                    'hall' => 'bg-green-100 text-green-800 ring-green-200/80 dark:bg-green-950/50 dark:text-green-200 dark:ring-green-500/30',
                                                    default => 'bg-gray-100 text-gray-800 ring-gray-200/70 dark:bg-gray-800 dark:text-gray-100 dark:ring-white/10',
                                                };
                                                $shift = $row['shift'];
                                                $end = $shift[1] ?? $shift[0] ?? '';
                                                $ls = $row['live_status'] ?? null;
                                            @endphp
                                            <span class="inline-flex max-w-full flex-wrap items-center gap-x-1 rounded-md px-1.5 py-0.5 text-[11px] font-medium ring-1 ring-inset {{ $chip }}">
                                                @if($ls && $ls !== 'none')
                                                    <span class="select-none shrink-0" title="打刻">{{ \App\Filament\Pages\WeeklyShiftSchedule::liveStatusIcon($ls) }}</span>
                                                @endif
                                                <span class="font-semibold uppercase tracking-tight">[{{ $row['role_display'] }}]</span>
                                                <span>{{ $row['staff']->name }}</span>
                                                <span class="font-mono tabular-nums opacity-90">({{ $shift[0] }}–{{ $end }})</span>
                                            </span>
                                        @endforeach
                                        @foreach($liveExtras as $extra)
                                            @php
                                                $ecat = $extra['category'];
                                                $echip = match ($ecat) {
                                                    'kitchen' => 'bg-rose-100 text-rose-900 ring-rose-200/80 dark:bg-rose-950/50 dark:text-rose-100',
                                                    'hall' => 'bg-rose-100 text-rose-900 ring-rose-200/80 dark:bg-rose-950/50 dark:text-rose-100',
                                                    default => 'bg-rose-100 text-rose-900 ring-rose-200/80 dark:bg-rose-950/50 dark:text-rose-100',
                                                };
                                            @endphp
                                            <span class="inline-flex max-w-full flex-wrap items-center gap-x-1 rounded-md px-1.5 py-0.5 text-[11px] font-medium ring-1 ring-inset {{ $echip }}" title="予定なし・打刻あり">
                                                <span class="select-none shrink-0">🆘</span>
                                                <span class="font-semibold uppercase tracking-tight">[{{ $extra['role_display'] }}]</span>
                                                <span>{{ $extra['staff']->name }}</span>
                                                <span class="font-mono tabular-nums opacity-90">{{ $extra['in_at']?->format('H:i') ?? '—' }}</span>
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    </div>
</x-filament-panels::page>
