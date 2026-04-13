{{-- 本日の AM|PM 人員（週次シフト表モバイルブロックに準拠・Filament 非依存） --}}
@php
    /** @var array{dayLabel: string, dateLabel: string, lunch: array, dinner: array} $todayShiftPanel */
    $dayLabel = $todayShiftPanel['dayLabel'] ?? '';
    $dateLabel = $todayShiftPanel['dateLabel'] ?? '';
    $blockLunch = $todayShiftPanel['lunch'] ?? ['assignments' => [], 'counts' => ['kitchen' => 0, 'hall' => 0, 'other' => 0], 'live_extras' => []];
    $blockDinner = $todayShiftPanel['dinner'] ?? ['assignments' => [], 'counts' => ['kitchen' => 0, 'hall' => 0, 'other' => 0], 'live_extras' => []];
@endphp

<section class="mx-auto mt-4 w-full max-w-5xl px-3 pb-2" aria-labelledby="today-shift-roster-heading">
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:shadow-gray-950/40">
        <div class="border-b border-slate-200 bg-slate-50 px-3 py-2 sm:px-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                <span class="font-medium text-gray-800 dark:text-gray-100">TODAYS SHIFTS </span>
                <span class="mx-1 text-gray-400 dark:text-gray-500">·</span>
                <span>{{ $dayLabel }}</span>
                <span class="mx-1 text-gray-400 dark:text-gray-500">·</span>
                <span class="font-mono tabular-nums">{{ $dateLabel }}</span>
                <span class="mx-1 text-gray-400 dark:text-gray-500">—</span>
                <span class="text-gray-500 dark:text-gray-400" lang="fr">打刻: 🟢 🆘 🔴 ⚪</span>
            </p>
        </div>

        <div class="p-1.5 sm:p-2">
            <div class="mx-auto w-full max-w-3xl">
                <div
                    class="overflow-hidden rounded-sm border-2 border-black shadow-[4px_4px_0_0_rgba(0,0,0,0.85)] ring-2 ring-yellow-400 dark:border-sky-500 dark:shadow-[4px_4px_0_0_rgba(0,0,0,0.5)] dark:ring-yellow-500/60"
                    aria-label="{{ $dayLabel }}"
                >
                    <table class="w-full table-fixed border-collapse border-0 text-left text-gray-950 dark:text-gray-100">
                        <colgroup>
                            <col class="w-[50%]" />
                            <col class="w-[50%]" />
                        </colgroup>
                        <thead>
                            <tr class="border-b-2 border-black bg-gradient-to-r from-sky-300 via-sky-200 to-cyan-200 dark:border-sky-600 dark:from-slate-800 dark:via-slate-800 dark:to-slate-900">
                                <th colspan="2" class="px-2 py-1.5 font-black uppercase tracking-[0.12em] text-black dark:text-white">
                                    <span class="mr-1 inline-block h-2 w-2 rounded-full bg-red-600 align-middle" title="本日"></span>
                                    <span class="inline-block max-w-full truncate align-middle text-[11px] normal-case tracking-normal" title="{{ $dayLabel }}">{{ $dayLabel }}</span>
                                </th>
                            </tr>
                            <tr class="border-b-2 border-black bg-sky-100 dark:border-sky-600 dark:bg-slate-800">
                                <th class="border-r-2 border-black px-1 py-1 text-center dark:border-sky-600">
                                    <span class="inline-flex items-center justify-center gap-0.5 whitespace-nowrap" title="Lunch · AM">
                                        <span class="text-sm" aria-hidden="true">☀️</span>
                                        <span class="text-[9px] font-black uppercase tracking-tight text-amber-950 dark:text-amber-100">AM</span>
                                    </span>
                                </th>
                                <th class="px-1 py-1 text-center">
                                    <span class="inline-flex items-center justify-center gap-0.5 whitespace-nowrap" title="Dinner · PM">
                                        <span class="text-sm" aria-hidden="true">🌙</span>
                                        <span class="text-[9px] font-black uppercase tracking-tight text-indigo-950 dark:text-indigo-100">PM</span>
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="align-top bg-sky-50 dark:bg-slate-950">
                                <td class="border-r-2 border-black p-0 align-top dark:border-sky-600">
                                    <div class="min-h-[4rem] min-w-0 border border-sky-400/80 bg-sky-50 dark:border-sky-700 dark:bg-slate-900">
                                        @include('welcome.partials.today-shift-meal-welcome', [
                                            'block' => $blockLunch,
                                            'mealTint' => 'amber',
                                        ])
                                    </div>
                                </td>
                                <td class="p-0 align-top">
                                    <div class="min-h-[4rem] min-w-0 border border-sky-400/80 bg-sky-50 dark:border-sky-700 dark:bg-slate-900">
                                        @include('welcome.partials.today-shift-meal-welcome', [
                                            'block' => $blockDinner,
                                            'mealTint' => 'indigo',
                                        ])
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
