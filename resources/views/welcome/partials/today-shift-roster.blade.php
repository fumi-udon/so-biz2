{{-- 本日の AM|PM 人員（週次シフト表モバイルブロックに準拠・Filament 非依存） --}}
@php
    /** @var array{dayLabel: string, dateLabel: string, lunch: array, dinner: array} $todayShiftPanel */
    $dayLabel = $todayShiftPanel['dayLabel'] ?? '';
    $dateLabel = $todayShiftPanel['dateLabel'] ?? '';
    $blockLunch = $todayShiftPanel['lunch'] ?? ['assignments' => [], 'counts' => ['kitchen' => 0, 'hall' => 0, 'other' => 0], 'live_extras' => []];
    $blockDinner = $todayShiftPanel['dinner'] ?? ['assignments' => [], 'counts' => ['kitchen' => 0, 'hall' => 0, 'other' => 0], 'live_extras' => []];
@endphp

<section class="mx-auto mt-4 w-full max-w-5xl px-3 pb-2" aria-label="Effectif du jour">
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-50 px-3 py-2 sm:px-4">
            <p class="mt-0.5 text-xs text-slate-600">
                <span class="font-medium text-slate-800">Aujourd'hui</span>
                <span class="mx-1 text-slate-400">·</span>
                <span>{{ $dayLabel }}</span>
                <span class="mx-1 text-slate-400">·</span>
                <span class="font-mono tabular-nums">{{ $dateLabel }}</span>
                <span class="mx-1 text-slate-400">—</span>
                <span class="text-slate-500">🟢 🆘 🔴 ⚪</span>
            </p>
        </div>
   

        <div class="p-1.5 sm:p-2">
            <div class="mx-auto w-full max-w-3xl">
                <div
                    class="overflow-hidden rounded-sm border-2 border-black shadow-[4px_4px_0_0_rgba(0,0,0,0.85)] ring-2 ring-yellow-400"
                    aria-label="{{ $dayLabel }}"
                >
                    <table class="w-full table-fixed border-collapse border-0 text-left text-slate-950">
                        <colgroup>
                            <col class="w-[50%]" />
                            <col class="w-[50%]" />
                        </colgroup>
                        <thead>
                            <tr class="border-b-2 border-black bg-gradient-to-r from-sky-300 via-sky-200 to-cyan-200">
                                <th colspan="2" class="px-2 py-1.5 font-black uppercase tracking-[0.12em] text-black">
                                    <span class="mr-1 inline-block h-2 w-2 rounded-full bg-red-600 align-middle" title="本日"></span>
                                    <span class="inline-block max-w-full truncate align-middle text-[11px] normal-case tracking-normal" title="{{ $dayLabel }}">{{ $dayLabel }}</span>
                                </th>
                            </tr>
                            <tr class="border-b-2 border-black bg-sky-100">
                                <th class="border-r-2 border-black px-1 py-1 text-center">
                                    <span class="inline-flex items-center justify-center gap-0.5 whitespace-nowrap" title="Lunch · AM">
                                        <span class="text-sm" aria-hidden="true">☀️</span>
                                        <span class="text-[9px] font-black uppercase tracking-tight text-amber-950">AM</span>
                                    </span>
                                </th>
                                <th class="px-1 py-1 text-center">
                                    <span class="inline-flex items-center justify-center gap-0.5 whitespace-nowrap" title="Dinner · PM">
                                        <span class="text-sm" aria-hidden="true">🌙</span>
                                        <span class="text-[9px] font-black uppercase tracking-tight text-indigo-950">PM</span>
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="align-top bg-sky-50">
                                <td class="border-r-2 border-black p-0 align-top">
                                    <div class="min-h-[4rem] min-w-0 border border-sky-400/80 bg-sky-50">
                                        @include('welcome.partials.today-shift-meal-welcome', [
                                            'block' => $blockLunch,
                                            'mealTint' => 'amber',
                                        ])
                                    </div>
                                </td>
                                <td class="p-0 align-top">
                                    <div class="min-h-[4rem] min-w-0 border border-sky-400/80 bg-sky-50">
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
