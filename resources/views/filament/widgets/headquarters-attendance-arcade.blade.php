@php
    /** @var string $monthLabel */
    /** @var int $maxLate */
    /** @var \Illuminate\Support\Collection<int, array{staff: \App\Models\Staff, late: int, absent_equiv: int, warn: bool}> $rows */
@endphp

<x-filament-widgets::widget>
    <x-filament::section :compact="true">
        <x-slot name="heading">
            <span class="inline-flex items-center gap-1.5 rounded-r-md border-l-4 border-emerald-600 bg-white py-0.5 pl-2 pr-2 text-[11px] font-bold tracking-tight text-gray-950 shadow-sm ring-1 ring-gray-200 dark:border-emerald-500 dark:bg-gray-950 dark:text-white dark:ring-gray-700 sm:text-xs">
                <span class="select-none text-[10px]" aria-hidden="true">🌟</span>
                出勤サマリー（当月）
            </span>
        </x-slot>
        <x-slot name="description">
            <div class="space-y-1">
                <span class="inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50/90 px-1.5 py-0.5 text-[11px] font-medium text-emerald-950 shadow-sm dark:border-emerald-600/40 dark:bg-emerald-950/40 dark:text-emerald-100">
                    <span class="select-none opacity-80" aria-hidden="true">🪙</span>
                    対象期間：{{ $monthLabel }}（月初〜本日）
                </span>
                <p class="text-[10px] leading-snug text-gray-600 dark:text-gray-400 sm:text-[11px]">
                    <span class="font-semibold text-gray-800 dark:text-gray-200">遅刻（回）</span>＝当月、<span class="font-mono">late_minutes &gt; 0</span> の勤怠レコード件数（シフトごとにカウント）。<br>
                    <span class="font-semibold text-gray-800 dark:text-gray-200">欠席相当（日）</span>＝休業日・欠勤扱いと判定された日数（カレンダー日ベース）。<span class="text-amber-800 dark:text-amber-200">3回以上の遅刻</span>または<span class="text-amber-800 dark:text-amber-200">欠席相当2日以上</span>で行を強調表示します。
                </p>
            </div>
        </x-slot>

        @if ($rows->isEmpty())
            <p class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-2 py-2 text-[11px] font-medium text-gray-600 dark:border-gray-600 dark:bg-gray-900/50 dark:text-gray-300 sm:text-xs">表示するスタッフがありません。</p>
        @else
            <div class="-mx-1 overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-950 md:mx-0">
                <table class="w-full min-w-[20rem] border-collapse text-left text-[11px] leading-tight text-gray-950 sm:min-w-[22rem] sm:text-xs dark:text-gray-100">
                    <thead>
                        <tr class="border-b border-emerald-100 bg-emerald-50 text-emerald-950 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100">
                            <th class="whitespace-nowrap px-1 py-1 font-bold sm:px-2 sm:py-1.5">スタッフ</th>
                            <th class="whitespace-nowrap px-1 py-1 text-right font-bold sm:px-2 sm:py-1.5">
                                <span class="block">遅刻</span>
                                <span class="block text-[9px] font-semibold normal-case opacity-90 sm:text-[10px]">（回）</span>
                            </th>
                            <th class="whitespace-nowrap px-1 py-1 text-right font-bold sm:px-2 sm:py-1.5">
                                <span class="block">欠席相当</span>
                                <span class="block text-[9px] font-semibold normal-case opacity-90 sm:text-[10px]">（日）</span>
                            </th>
                            <th class="min-w-[4rem] px-1 py-1 font-bold sm:min-w-[5rem] sm:px-2 sm:py-1.5">遅刻の目安</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            @php
                                $staff = $r['staff'];
                                $late = (int) $r['late'];
                                $abs = (int) $r['absent_equiv'];
                                $warn = (bool) $r['warn'];
                                $pct = $maxLate > 0 ? min(100, (int) round(100 * $late / $maxLate)) : 0;
                                $latePillClass = match (true) {
                                    $late >= 3 => 'border-red-200 bg-red-50 text-red-900 ring-1 ring-red-100 dark:border-red-700/50 dark:bg-red-950/40 dark:text-red-100',
                                    $late > 0 => 'border-yellow-200 bg-yellow-50 text-yellow-950 ring-1 ring-yellow-100 dark:border-yellow-500/40 dark:bg-yellow-950/40 dark:text-yellow-100',
                                    default => 'border-gray-200 bg-gray-50 text-gray-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400',
                                };
                            @endphp
                            <tr
                                @class([
                                    'odd:bg-white even:bg-gray-50/80 dark:odd:bg-gray-950 dark:even:bg-gray-900/40',
                                    'bg-amber-50/90 ring-1 ring-amber-300/80 dark:bg-amber-950/25 dark:ring-amber-600/40' => $warn,
                                ])
                            >
                                <td class="max-w-[8rem] truncate px-1 py-0.5 font-semibold text-gray-900 dark:text-gray-100 sm:max-w-[12rem] sm:px-2 sm:py-1" title="{{ $staff->name }}">
                                    {{ $staff->name }}
                                </td>
                                <td class="px-1 py-0.5 text-right sm:px-2 sm:py-1">
                                    <span class="inline-flex min-w-[2.75rem] flex-col items-end rounded-md border px-1.5 py-0.5 font-mono tabular-nums font-bold sm:min-w-[3.25rem] {{ $latePillClass }}">
                                        <span class="text-[11px] leading-none sm:text-xs">{{ $late }}</span>
                                        <span class="text-[9px] font-medium leading-none opacity-80">回</span>
                                    </span>
                                </td>
                                <td class="px-1 py-0.5 text-right sm:px-2 sm:py-1">
                                    <span
                                        @class([
                                            'inline-flex min-w-[2.75rem] flex-col items-end rounded-md border px-1.5 py-0.5 font-mono tabular-nums font-bold sm:min-w-[3.25rem]',
                                            'border-amber-200 bg-amber-50 text-amber-950 ring-1 ring-amber-100 dark:border-amber-600/40 dark:bg-amber-950/35 dark:text-amber-100' => $abs > 0,
                                            'border-gray-200 bg-gray-50 text-gray-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400' => $abs === 0,
                                        ])
                                    >
                                        <span class="text-[11px] leading-none sm:text-xs">{{ $abs }}</span>
                                        <span class="text-[9px] font-medium leading-none opacity-80">日</span>
                                    </span>
                                </td>
                                <td class="px-1 py-0.5 align-middle sm:px-2 sm:py-1">
                                    <div class="flex items-center gap-1.5">
                                        <div class="h-2 min-w-[3rem] flex-1 overflow-hidden rounded border border-emerald-200 bg-gray-100 dark:border-emerald-800/50 dark:bg-gray-900 sm:min-w-[4rem]">
                                            <div
                                                class="h-full rounded-sm bg-gradient-to-r from-emerald-500 to-sky-400 dark:from-emerald-600 dark:to-sky-500"
                                                style="width: {{ $pct }}%"
                                            ></div>
                                        </div>
                                        <span class="shrink-0 font-mono text-[9px] tabular-nums text-gray-500 dark:text-gray-400 sm:text-[10px]">{{ $pct }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
