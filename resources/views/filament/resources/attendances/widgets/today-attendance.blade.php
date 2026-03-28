@php
    /** @var string $businessDate */
    /** @var \Illuminate\Support\Collection<int, array{staff: \App\Models\Staff, attendance: \App\Models\Attendance|null, status: string}> $staffRows */
@endphp

<div class="fi-wi-widget fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="fi-section-header-ctn border-b border-gray-200 px-4 py-3 dark:border-white/10 sm:px-6">
        <h3 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
            本日の出勤状況
        </h3>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            営業日（6時基準）: <span class="font-mono text-gray-700 dark:text-gray-300">{{ $businessDate }}</span>
            <span class="ml-2 inline-flex items-center gap-1">
                <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span> 勤務中
                <span class="inline-block h-2 w-2 rounded-full bg-gray-300 dark:bg-gray-600"></span> 退勤済・未出勤
            </span>
        </p>
    </div>

    <div class="p-4 sm:p-6">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($staffRows as $row)
                @php
                    $isWorking = $row['status'] === 'working';
                @endphp
                <div
                    @class([
                        'rounded-lg border-2 p-3 transition-shadow',
                        'border-emerald-500 bg-emerald-50 shadow-md ring-1 ring-emerald-500/20 dark:border-emerald-400 dark:bg-emerald-950/40 dark:ring-emerald-400/20' => $isWorking,
                        'border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-gray-800/60' => ! $isWorking,
                    ])
                >
                    <div class="flex items-start justify-between gap-2">
                        <span class="font-medium text-gray-900 dark:text-white">{{ $row['staff']->name }}</span>
                        @if ($isWorking)
                            <span class="shrink-0 rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
                                勤務中
                            </span>
                        @else
                            <span class="shrink-0 rounded-full bg-gray-400 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white dark:bg-gray-600">
                                退勤済・未出勤
                            </span>
                        @endif
                    </div>
                    @if ($row['attendance'])
                        <dl class="mt-2 space-y-0.5 text-xs text-gray-600 dark:text-gray-400">
                            <div class="flex justify-between gap-2 font-mono">
                                <span>L</span>
                                <span>
                                    {{ $row['attendance']->lunch_in_at?->format('H:i') ?? '—' }}
                                    →
                                    {{ $row['attendance']->lunch_out_at?->format('H:i') ?? '—' }}
                                </span>
                            </div>
                            <div class="flex justify-between gap-2 font-mono">
                                <span>D</span>
                                <span>
                                    {{ $row['attendance']->dinner_in_at?->format('H:i') ?? '—' }}
                                    →
                                    {{ $row['attendance']->dinner_out_at?->format('H:i') ?? '—' }}
                                </span>
                            </div>
                        </dl>
                    @else
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">本日の打刻なし</p>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($staffRows->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">アクティブなスタッフがいません。</p>
        @endif
    </div>
</div>
