@php
    /** @var string $businessDate */
    /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $rows */
@endphp

<x-filament-widgets::widget>
    <x-filament::section :compact="true">
        <x-slot name="heading">
            <span class="inline-flex flex-wrap items-center gap-1.5">
                <span class="inline-flex items-center gap-1 rounded-r-md border-l-4 border-red-500 bg-white py-0.5 pl-2 pr-2 text-[11px] font-bold text-gray-950 shadow-sm ring-1 ring-gray-200 dark:border-red-400 dark:bg-gray-950 dark:text-white dark:ring-gray-700 sm:text-xs">
                    <span class="select-none text-[10px]" aria-hidden="true">🍄</span>
                    本日の出勤状況
                </span>
                <span class="rounded-md border border-sky-200 bg-sky-50 px-1.5 py-0.5 text-[11px] font-bold font-mono tabular-nums text-sky-950 shadow-sm ring-1 ring-sky-100 dark:border-sky-600/40 dark:bg-sky-950/50 dark:text-sky-100 dark:ring-sky-800/40">
                    {{ $businessDate }}
                </span>
            </span>
        </x-slot>

        @if ($rows->isEmpty())
            <p class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-2 py-2 text-[11px] font-medium text-gray-600 dark:border-gray-600 dark:bg-gray-900/50 dark:text-gray-300 sm:text-xs">表示するスタッフがありません。</p>
        @else
            <div class="-mx-1 overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-950">
                <table class="w-full min-w-[18rem] border-collapse text-left text-[11px] leading-snug text-gray-900 sm:text-xs dark:text-gray-100">
                    <thead>
                        <tr class="border-b border-sky-100 bg-sky-50 text-sky-950 dark:border-sky-900/50 dark:bg-sky-950/40 dark:text-sky-100">
                            <th class="whitespace-nowrap px-1 py-1 font-bold sm:px-2 sm:py-1.5">
                                スタッフ
                            </th>
                            <th class="whitespace-nowrap border-l-4 border-yellow-400 bg-yellow-50/50 px-1 py-1 font-bold text-yellow-950 dark:border-yellow-500 dark:bg-yellow-950/25 dark:text-yellow-100 sm:px-2 sm:py-1.5">
                                ☀ L
                            </th>
                            <th class="whitespace-nowrap border-l-4 border-emerald-600 bg-emerald-50/40 px-1 py-1 font-bold text-emerald-950 dark:border-emerald-500 dark:bg-emerald-950/30 dark:text-emerald-100 sm:px-2 sm:py-1.5">
                                🌙 D
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                /** @var \App\Models\Staff $staff */
                                $staff = $row['staff'];
                                $roleLabel = (string) ($row['role_label'] ?? 'Other');
                                $roleCategory = (string) ($row['role_category'] ?? 'other');
                                $roleColor = (string) ($row['role_color'] ?? 'gray');

                                $roleShort = match ($roleCategory) {
                                    'kitchen' => 'Kit',
                                    'hall' => 'Hal',
                                    default => 'Oth',
                                };

                                $badgeClass = match ($roleColor) {
                                    'red' => 'border border-red-200 bg-red-50 text-red-900 ring-1 ring-red-100 dark:border-red-700/50 dark:bg-red-950/40 dark:text-red-100 dark:ring-red-900/30',
                                    'green' => 'border border-emerald-200 bg-emerald-50 text-emerald-900 ring-1 ring-emerald-100 dark:border-emerald-700/50 dark:bg-emerald-950/40 dark:text-emerald-100 dark:ring-emerald-800/40',
                                    default => 'border border-gray-200 bg-gray-50 text-gray-800 ring-1 ring-gray-100 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 dark:ring-gray-800',
                                };

                                $lPlanned = $row['lunch_scheduled_start'] ?? null;
                                $lStatus = $row['lunch_status'] ?? 'none';
                                $lInTime = $row['lunch_in_time'] ?? null;

                                $dPlanned = $row['dinner_scheduled_start'] ?? null;
                                $dStatus = $row['dinner_status'] ?? 'none';
                                $dInTime = $row['dinner_in_time'] ?? null;

                                $renderMeal = function (
                                    string $status,
                                    ?string $plannedStart,
                                    ?string $actualInTime,
                                ): string {
                                    if ($status === 'none') {
                                        return '<span class="text-[11px] text-gray-400 dark:text-gray-500">—</span>';
                                    }

                                    $plannedDisp = e($plannedStart !== null && $plannedStart !== '' ? $plannedStart : '-');
                                    $actualDisp = e($actualInTime ?? '');

                                    $left = '<span class="font-mono tabular-nums text-[11px] font-medium text-gray-600 dark:text-gray-300">'.$plannedDisp.'</span>';
                                    $arrow = '<span class="mx-0.5 text-[11px] text-yellow-600 dark:text-yellow-400">▶</span>';

                                    return match ($status) {
                                        'clocked' => $left.$arrow.'<span class="text-[11px] select-none">🟢</span>'.
                                            '<span class="font-mono tabular-nums text-[11px] font-semibold text-gray-950 dark:text-white">'.$actualDisp.'</span>',
                                        'extra' => $left.$arrow.'<span class="text-[11px] select-none">🆘</span>'.
                                            '<span class="font-mono tabular-nums text-[11px] font-semibold text-gray-950 dark:text-white">'.$actualDisp.'</span>',
                                        'late' => $left.$arrow.'<span class="text-[11px] select-none">🔴</span>'.
                                            '<span class="text-[11px] font-bold text-red-700 dark:text-red-400">未出勤</span>',
                                        'future' => $left.$arrow.'<span class="text-[11px] select-none text-sky-500 dark:text-sky-400">⚪</span>',
                                        default => $left.$arrow.'<span class="text-[11px] select-none text-gray-400">—</span>',
                                    };
                                };
                            @endphp

                            <tr class="odd:bg-white even:bg-gray-50/70 hover:bg-sky-50/40 dark:odd:bg-gray-950 dark:even:bg-gray-900/35 dark:hover:bg-sky-950/25">
                                <td class="px-1 py-0.5 align-middle sm:px-2 sm:py-1">
                                    <div class="flex min-w-0 flex-wrap items-center gap-x-1 gap-y-0.5">
                                        <span class="inline-flex shrink-0 items-center rounded-md px-1 py-0.5 text-[10px] font-extrabold leading-none shadow-sm {{ $badgeClass }}">
                                            {{ $roleShort }}
                                        </span>
                                        <span class="min-w-0 truncate font-semibold text-gray-950 dark:text-white">{{ $staff->name }}</span>
                                    </div>
                                </td>
                                <td class="border-l-4 border-yellow-300/70 bg-yellow-50/30 px-1 py-0.5 align-middle dark:border-yellow-600/40 dark:bg-yellow-950/15 sm:px-2 sm:py-1">
                                    <div class="flex flex-wrap items-center gap-x-0.5 gap-y-0.5">
                                        {!! $renderMeal($lStatus, $lPlanned, $lInTime) !!}
                                    </div>
                                </td>
                                <td class="border-l-4 border-emerald-500/50 bg-emerald-50/25 px-1 py-0.5 align-middle dark:border-emerald-600/40 dark:bg-emerald-950/20 sm:px-2 sm:py-1">
                                    <div class="flex flex-wrap items-center gap-x-0.5 gap-y-0.5">
                                        {!! $renderMeal($dStatus, $dPlanned, $dInTime) !!}
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
