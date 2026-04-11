@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Staff> $staffs */
    /** @var array<string, array{lunch: array, dinner: array}> $shiftGrid */
    /** @var array<string, string> $dayLabels */
    /** @var string $todayDayKey */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Attendance> $attendancesToday */
    /** @var array<int, array{lunch: string, dinner: string}> $liveByStaff */
@endphp

<x-filament-panels::page>
    <div
        class="relative overflow-hidden rounded-2xl border-2 border-sky-300/70 bg-gradient-to-b from-sky-400 via-sky-200 to-sky-100 p-3 shadow-xl dark:border-sky-800/60 dark:from-slate-900 dark:via-sky-950 dark:to-slate-950 sm:p-5"
    >
        <div
            class="pointer-events-none absolute -left-4 top-6 h-16 w-32 rounded-full bg-white/50 blur-sm dark:bg-white/10"
            aria-hidden="true"
        ></div>
        <div
            class="pointer-events-none absolute right-10 top-16 h-10 w-24 rounded-full bg-white/40 blur-sm dark:bg-white/10"
            aria-hidden="true"
        ></div>
        <div
            class="pointer-events-none absolute bottom-24 left-1/3 h-14 w-40 rounded-full bg-white/30 blur-sm dark:bg-white/5"
            aria-hidden="true"
        ></div>

        <div wire:poll.30s class="relative space-y-6">
    <div class="overflow-x-auto rounded-2xl border-2 border-b-4 border-white/95 bg-white/95 shadow-xl dark:border-slate-600/90 dark:bg-slate-900/95">
        <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/5">
            <thead class="bg-gradient-to-r from-sky-100 to-cyan-50 dark:from-slate-800 dark:to-slate-900">
                <tr>
                    <th class="w-[min(12rem,32vw)] max-w-[12rem] px-2 py-2 text-xs font-black uppercase tracking-wide text-gray-950 dark:text-white whitespace-nowrap sm:px-4 sm:py-3">{{ __('hq.weekly_staff', [], 'fr') }}</th>
                    @foreach($dayLabels as $dayKey => $dayLabel)
                        <th wire:key="shift-col-{{ $dayKey }}" @class([
                            'px-4 py-3 font-black uppercase tracking-wide text-gray-950 dark:text-white text-center whitespace-nowrap',
                            'bg-sky-200/90 dark:bg-sky-950/40 ring-1 ring-inset ring-sky-400/50 dark:ring-sky-500/30' => $dayKey === $todayDayKey,
                        ])>
                            @if($dayKey === $todayDayKey)
                                <span class="mr-1 inline-block h-1.5 w-1.5 rounded-full bg-sky-600 align-middle dark:bg-sky-400" title="{{ __('hq.weekly_today_dot', [], 'fr') }}"></span>
                            @endif
                            {{ $dayLabel }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                @foreach($staffs as $staff)
                <tr wire:key="staff-row-{{ $staff->id }}" class="hover:bg-sky-50/50 dark:hover:bg-white/5 transition duration-75">
                    <td class="max-w-[9rem] px-2 py-2 align-middle sm:max-w-[12rem] sm:px-4 sm:py-3">
                        <span class="inline-flex min-w-0 max-w-full items-center gap-1 whitespace-nowrap" title="{{ $staff->name }}">
                            @include('filament.pages.partials.weekly-shift-staff-role-icon', ['staff' => $staff])
                            <span class="truncate text-xs font-bold text-gray-950 dark:text-white">{{ $staff->name }}</span>
                        </span>
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
                        <td wire:key="staff-{{ $staff->id }}-day-{{ $dayKey }}" @class([
                            'px-2 py-2 sm:px-3 sm:py-3 align-top text-left min-w-0 max-w-[11rem] sm:max-w-none',
                            'bg-sky-100/60 dark:bg-sky-950/25' => $isTodayCol,
                        ])>
                            @if(!$lunch && !$dinner)
                                @if($isTodayCol && ($live['lunch'] === 'extra' || $live['dinner'] === 'extra'))
                                    <div class="grid min-w-0 grid-cols-2 gap-1 items-start">
                                        <div class="min-w-0">
                                            @if($live['lunch'] === 'extra')
                                                <span class="inline-flex w-full min-w-0 items-center gap-0.5 rounded-md bg-rose-50 px-1 py-0.5 text-[10px] font-medium leading-none text-rose-800 ring-1 ring-inset ring-rose-200/70 dark:bg-rose-950/40 dark:text-rose-200 dark:ring-rose-500/30" title="{{ __('hq.weekly_extra_title', [], 'fr') }}">
                                                    <x-filament::icon icon="heroicon-m-sun" class="h-3 w-3 shrink-0 text-amber-500 dark:text-amber-400" />
                                                    <span class="shrink-0">{{ \App\Filament\Pages\WeeklyShiftSchedule::liveStatusIcon('extra') }}</span>
                                                    <span class="inline-flex items-center rounded-full bg-rose-100 px-1.5 py-0.5 font-mono tabular-nums text-[9px] font-semibold text-rose-900 dark:bg-rose-900/50 dark:text-rose-100">{{ $att?->lunch_in_at?->format('H:i') ?? '—' }}</span>
                                                </span>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            @if($live['dinner'] === 'extra')
                                                <span class="inline-flex w-full min-w-0 items-center gap-0.5 rounded-md bg-rose-50 px-1 py-0.5 text-[10px] font-medium leading-none text-rose-800 ring-1 ring-inset ring-rose-200/70 dark:bg-rose-950/40 dark:text-rose-200 dark:ring-rose-500/30" title="{{ __('hq.weekly_extra_title', [], 'fr') }}">
                                                    <x-filament::icon icon="heroicon-m-moon" class="h-3 w-3 shrink-0 text-indigo-500 dark:text-indigo-300" />
                                                    <span class="shrink-0">{{ \App\Filament\Pages\WeeklyShiftSchedule::liveStatusIcon('extra') }}</span>
                                                    <span class="inline-flex items-center rounded-full bg-rose-100 px-1.5 py-0.5 font-mono tabular-nums text-[9px] font-semibold text-rose-900 dark:bg-rose-900/50 dark:text-rose-100">{{ $att?->dinner_in_at?->format('H:i') ?? '—' }}</span>
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <span class="inline-flex rounded-md bg-gray-50 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-gray-600 dark:bg-white/5 dark:text-gray-400 sm:text-xs">
                                        {{ __('hq.weekly_repos', [], 'fr') }}
                                    </span>
                                @endif
                            @else
                                <div class="grid min-w-0 grid-cols-2 gap-1 items-start">
                                    <div class="flex min-w-0 flex-col gap-1">
                                        @if($lunch)
                                            <span class="inline-flex w-full min-w-0 items-center gap-0.5 rounded-md bg-amber-50 px-1 py-0.5 text-[10px] font-medium leading-none text-amber-900 ring-1 ring-inset ring-amber-600/25 dark:bg-amber-400/10 dark:text-amber-200 dark:ring-amber-400/25">
                                                <x-filament::icon icon="heroicon-m-sun" class="h-3 w-3 shrink-0 text-amber-600 dark:text-amber-400" />
                                                @if($isTodayCol && ($live['lunch'] ?? 'none') !== 'none')
                                                    <span class="select-none shrink-0" title="{{ __('hq.weekly_title_status', [], 'fr') }}">{{ \App\Filament\Pages\WeeklyShiftSchedule::liveStatusIcon($live['lunch']) }}</span>
                                                @endif
                                                <span class="inline-flex min-w-0 items-center rounded-full bg-gray-100 px-1.5 py-0.5 font-mono text-[9px] font-semibold tabular-nums text-gray-800 dark:bg-gray-800 dark:text-gray-100">{{ $lunch[0] }}–{{ $lunch[1] ?? $lunch[0] }}</span>
                                            </span>
                                        @elseif($isTodayCol && ($live['lunch'] ?? 'none') === 'extra')
                                            <span class="inline-flex w-full min-w-0 items-center gap-0.5 rounded-md bg-rose-50 px-1 py-0.5 text-[10px] font-medium leading-none text-rose-800 ring-1 ring-inset ring-rose-200/70 dark:bg-rose-950/40 dark:text-rose-200" title="{{ __('hq.weekly_extra_title', [], 'fr') }}">
                                                <x-filament::icon icon="heroicon-m-sun" class="h-3 w-3 shrink-0 text-amber-500 dark:text-amber-400" />
                                                <span class="shrink-0">{{ \App\Filament\Pages\WeeklyShiftSchedule::liveStatusIcon('extra') }}</span>
                                                <span class="inline-flex items-center rounded-full bg-rose-100 px-1.5 py-0.5 font-mono tabular-nums text-[9px] font-semibold text-rose-900 dark:bg-rose-900/50 dark:text-rose-100">{{ $att?->lunch_in_at?->format('H:i') ?? '—' }}</span>
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex min-w-0 flex-col gap-1">
                                        @if($dinner)
                                            <span class="inline-flex w-full min-w-0 items-center gap-0.5 rounded-md bg-indigo-50 px-1 py-0.5 text-[10px] font-medium leading-none text-indigo-900 ring-1 ring-inset ring-indigo-600/20 dark:bg-indigo-400/10 dark:text-indigo-100 dark:ring-indigo-400/25">
                                                <x-filament::icon icon="heroicon-m-moon" class="h-3 w-3 shrink-0 text-indigo-600 dark:text-indigo-300" />
                                                @if($isTodayCol && ($live['dinner'] ?? 'none') !== 'none')
                                                    <span class="select-none shrink-0" title="{{ __('hq.weekly_title_status', [], 'fr') }}">{{ \App\Filament\Pages\WeeklyShiftSchedule::liveStatusIcon($live['dinner']) }}</span>
                                                @endif
                                                <span class="inline-flex min-w-0 items-center rounded-full bg-gray-100 px-1.5 py-0.5 font-mono text-[9px] font-semibold tabular-nums text-gray-800 dark:bg-gray-800 dark:text-gray-100">{{ $dinner[0] }}–{{ $dinner[1] ?? $dinner[0] }}</span>
                                            </span>
                                        @elseif($isTodayCol && ($live['dinner'] ?? 'none') === 'extra')
                                            <span class="inline-flex w-full min-w-0 items-center gap-0.5 rounded-md bg-rose-50 px-1 py-0.5 text-[10px] font-medium leading-none text-rose-800 ring-1 ring-inset ring-rose-200/70 dark:bg-rose-950/40 dark:text-rose-200" title="{{ __('hq.weekly_extra_title', [], 'fr') }}">
                                                <x-filament::icon icon="heroicon-m-moon" class="h-3 w-3 shrink-0 text-indigo-500 dark:text-indigo-300" />
                                                <span class="shrink-0">{{ \App\Filament\Pages\WeeklyShiftSchedule::liveStatusIcon('extra') }}</span>
                                                <span class="inline-flex items-center rounded-full bg-rose-100 px-1.5 py-0.5 font-mono tabular-nums text-[9px] font-semibold text-rose-900 dark:bg-rose-900/50 dark:text-rose-100">{{ $att?->dinner_in_at?->format('H:i') ?? '—' }}</span>
                                            </span>
                                        @endif
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

    <div class="rounded-2xl border-2 border-b-4 border-white/95 bg-white/95 shadow-xl dark:border-slate-600/90 dark:bg-slate-900/95">
        <div class="border-b-2 border-sky-200/80 bg-gradient-to-r from-sky-50 to-white px-3 py-2 dark:border-slate-600 dark:from-slate-800 dark:to-slate-900 sm:px-4">
            <h3 class="text-sm font-black uppercase tracking-wide text-gray-950 dark:text-white">{{ __('hq.weekly_section_title', [], 'fr') }}</h3>
            <p class="mt-0.5 text-xs font-semibold text-gray-600 dark:text-gray-400">
                <span class="xl:hidden">{{ __('hq.weekly_section_hint_mobile', [], 'fr') }}</span>
                <span class="hidden xl:inline">{{ __('hq.weekly_section_hint_desktop', [], 'fr') }}</span>
            </p>
        </div>

        {{-- モバイル・タブレット: HTML table で 50%|50% を保証（Grid が 1 列に落ちる問題を回避） --}}
        <div class="xl:hidden p-1.5 sm:p-2">
            <div class="mx-auto flex w-full max-w-3xl flex-col gap-3">
                @foreach($dayLabels as $dayKey => $dayLabel)
                    @php
                        $isDayToday = $dayKey === $todayDayKey;
                        $blockLunch = $shiftGrid[$dayKey]['lunch'] ?? ['assignments' => [], 'counts' => ['kitchen' => 0, 'hall' => 0, 'other' => 0], 'live_extras' => []];
                        $blockDinner = $shiftGrid[$dayKey]['dinner'] ?? ['assignments' => [], 'counts' => ['kitchen' => 0, 'hall' => 0, 'other' => 0], 'live_extras' => []];
                    @endphp
                    <div wire:key="shift-mobile-day-{{ $dayKey }}"
                        @class([
                            'overflow-hidden rounded-2xl border-2 border-b-4 border-slate-800 bg-white shadow-xl dark:border-slate-600 dark:bg-slate-900',
                            'ring-2 ring-amber-400 dark:ring-amber-500/60' => $isDayToday,
                        ])
                        aria-label="{{ $dayLabel }}"
                    >
                        <table class="w-full table-fixed border-collapse border-0 text-left text-gray-950 dark:text-sky-50">
                            <colgroup>
                                <col class="w-[50%]" />
                                <col class="w-[50%]" />
                            </colgroup>
                            <thead>
                                <tr @class([
                                    'border-b-2 border-black bg-gradient-to-r from-sky-300 via-sky-200 to-cyan-200 dark:border-sky-500 dark:from-slate-800 dark:via-slate-800 dark:to-slate-900',
                                ])>
                                    <th colspan="2" class="px-2 py-1.5 font-black uppercase tracking-[0.12em] text-black dark:text-sky-100">
                                        @if($isDayToday)
                                            <span class="mr-1 inline-block h-2 w-2 rounded-full bg-red-600 align-middle" title="{{ __('hq.weekly_today_dot', [], 'fr') }}"></span>
                                        @endif
                                        <span class="inline-block max-w-full truncate align-middle text-[11px] normal-case tracking-normal" title="{{ $dayLabel }}">{{ $dayLabel }}</span>
                                    </th>
                                </tr>
                                <tr class="border-b-2 border-black bg-sky-100 dark:border-sky-600 dark:bg-slate-800">
                                    <th class="border-r-2 border-black px-1 py-1 text-center dark:border-sky-600">
                                        <span class="inline-flex items-center justify-center gap-0.5 whitespace-nowrap" title="Lunch · AM">
                                            <x-filament::icon icon="heroicon-m-sun" class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                            <span class="text-[9px] font-black uppercase tracking-tight text-amber-950 dark:text-amber-100">AM</span>
                                        </span>
                                    </th>
                                    <th class="px-1 py-1 text-center">
                                        <span class="inline-flex items-center justify-center gap-0.5 whitespace-nowrap" title="Dinner · PM">
                                            <x-filament::icon icon="heroicon-m-moon" class="h-3.5 w-3.5 shrink-0 text-indigo-600 dark:text-indigo-300" />
                                            <span class="text-[9px] font-black uppercase tracking-tight text-indigo-950 dark:text-indigo-100">PM</span>
                                        </span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr @class([
                                    'align-top bg-sky-50 dark:bg-slate-950',
                                    'bg-sky-100/70 dark:bg-slate-900/90' => $isDayToday,
                                ])>
                                    <td class="border-r-2 border-black p-0 align-top dark:border-sky-600">
                                        <div class="min-h-[4rem] min-w-0 border border-sky-400/80 bg-sky-50 dark:border-sky-700 dark:bg-slate-950">
                                            @include('filament.pages.partials.weekly-shift-meal-block-compact', [
                                                'block' => $blockLunch,
                                                'isToday' => $isDayToday,
                                                'mealTint' => 'amber',
                                            ])
                                        </div>
                                    </td>
                                    <td class="p-0 align-top">
                                        <div class="min-h-[4rem] min-w-0 border border-sky-400/80 bg-sky-50 dark:border-sky-700 dark:bg-slate-950">
                                            @include('filament.pages.partials.weekly-shift-meal-block-compact', [
                                                'block' => $blockDinner,
                                                'isToday' => $isDayToday,
                                                'mealTint' => 'indigo',
                                            ])
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ワイド画面: 従来テーブル --}}
        <div class="hidden xl:block overflow-x-auto">
            <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/5">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="w-28 whitespace-nowrap px-3 py-2 text-xs font-black uppercase tracking-wide text-gray-950 dark:text-white">{{ __('hq.weekly_wide_day', [], 'fr') }}</th>
                        <th class="min-w-[12rem] px-3 py-2 text-gray-900 dark:text-white">
                            <span class="inline-flex items-center gap-1 whitespace-nowrap" title="Lunch · AM">
                                <x-filament::icon icon="heroicon-m-sun" class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                <span class="text-[10px] font-bold uppercase tracking-wide text-gray-900 dark:text-gray-100">AM</span>
                            </span>
                        </th>
                        <th class="min-w-[12rem] px-3 py-2 text-gray-900 dark:text-white">
                            <span class="inline-flex items-center gap-1 whitespace-nowrap" title="Dinner · PM">
                                <x-filament::icon icon="heroicon-m-moon" class="h-3.5 w-3.5 shrink-0 text-indigo-600 dark:text-indigo-300" />
                                <span class="text-[10px] font-bold uppercase tracking-wide text-gray-900 dark:text-gray-100">PM</span>
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @foreach($dayLabels as $dayKey => $dayLabel)
                    <tr wire:key="shift-wide-day-{{ $dayKey }}" @class([
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
                                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('hq.weekly_no_schedule', [], 'fr') }}</span>
                                @else
                                    <div class="flex flex-col gap-y-1.5">
                                        <div class="mb-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 border-b border-gray-200 pb-1 text-[9px] font-bold leading-none dark:border-white/10">
                                            @if($counts['kitchen'] > 0)
                                                <span class="inline-flex items-center gap-0.5 whitespace-nowrap text-red-600 dark:text-red-400" title="Kitchen">
                                                    <x-filament::icon icon="heroicon-m-fire" class="h-3 w-3 shrink-0" />
                                                    <span class="text-[9px] font-black tabular-nums">{{ $counts['kitchen'] }}</span>
                                                </span>
                                            @endif
                                            @if($counts['hall'] > 0)
                                                <span class="inline-flex items-center gap-0.5 whitespace-nowrap text-emerald-600 dark:text-emerald-400" title="Hall">
                                                    <x-filament::icon icon="heroicon-m-user" class="h-3 w-3 shrink-0" />
                                                    <span class="text-[9px] font-black tabular-nums">{{ $counts['hall'] }}</span>
                                                </span>
                                            @endif
                                            @if($counts['other'] > 0)
                                                <span class="inline-flex items-center gap-0.5 whitespace-nowrap text-gray-600 dark:text-gray-400" title="Autre">
                                                    <x-filament::icon icon="heroicon-m-clipboard-document" class="h-3 w-3 shrink-0" />
                                                    <span class="text-[9px] font-black tabular-nums">{{ $counts['other'] }}</span>
                                                </span>
                                            @endif
                                        </div>
                                        <div class="flex flex-col gap-1">
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
                                                <span wire:key="shift-wide-assign-{{ $dayKey }}-{{ $meal }}-{{ $row['staff']->id }}-{{ $loop->index }}" class="inline-flex w-full max-w-full min-w-0 items-center gap-0.5 rounded-md px-1 py-0.5 text-[10px] font-medium ring-1 ring-inset {{ $chip }}">
                                                    @if($ls && $ls !== 'none')
                                                        <span class="shrink-0 select-none" title="{{ __('hq.weekly_title_punch', [], 'fr') }}">{{ \App\Filament\Pages\WeeklyShiftSchedule::liveStatusIcon($ls) }}</span>
                                                    @endif
                                                    @include('filament.pages.partials.weekly-shift-staff-role-icon', ['staff' => $row['staff'], 'class' => 'h-3 w-3 shrink-0'])
                                                    <span class="min-w-0 flex-1 truncate" title="{{ $row['staff']->name }} · {{ $row['role_display'] }}">{{ $row['staff']->name }}</span>
                                                    <span class="inline-flex shrink-0 items-center rounded-full bg-white/85 px-1.5 py-0 font-mono text-[9px] font-semibold tabular-nums text-gray-900 dark:bg-gray-900/85 dark:text-gray-100">{{ $shift[0] }}–{{ $end }}</span>
                                                </span>
                                            @endforeach
                                            @foreach($liveExtras as $extra)
                                                @php
                                                    $echip = 'bg-rose-100 text-rose-900 ring-rose-200/80 dark:bg-rose-950/50 dark:text-rose-100';
                                                @endphp
                                                <span wire:key="shift-wide-extra-{{ $dayKey }}-{{ $meal }}-{{ $extra['staff']->id }}-{{ $loop->index }}" class="inline-flex w-full max-w-full min-w-0 items-center gap-0.5 rounded-md px-1 py-0.5 text-[10px] font-medium ring-1 ring-inset {{ $echip }}" title="{{ __('hq.weekly_extra_badge', [], 'fr') }}">
                                                    <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-3 w-3 shrink-0 text-rose-700 dark:text-rose-300" />
                                                    @include('filament.pages.partials.weekly-shift-staff-role-icon', ['staff' => $extra['staff'], 'class' => 'h-3 w-3 shrink-0'])
                                                    <span class="min-w-0 flex-1 truncate" title="{{ $extra['staff']->name }}">{{ $extra['staff']->name }}</span>
                                                    <span class="inline-flex shrink-0 items-center rounded-full bg-white/85 px-1.5 py-0 font-mono text-[9px] font-semibold tabular-nums text-rose-950 dark:bg-gray-900/85 dark:text-rose-100">{{ $extra['in_at']?->format('H:i') ?? '—' }}</span>
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
        </div>
    </div>
</x-filament-panels::page>
