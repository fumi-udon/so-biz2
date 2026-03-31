@php
    /** @var string $businessDate */
    /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $rows */
@endphp

<x-filament-widgets::widget>
    <x-filament::section :compact="true">
        <x-slot name="heading">
            <span class="text-sm font-semibold">本日の出勤状況</span>
            <span class="ml-2 text-xs font-normal text-gray-500 dark:text-gray-400">{{ $businessDate }}</span>
        </x-slot>

        @if ($rows->isEmpty())
            <p class="text-xs text-gray-500 dark:text-gray-400">表示するスタッフがありません。</p>
        @else
            <div class="overflow-x-auto -mx-1">
                <table class="w-full min-w-[18rem] border-collapse text-left text-xs text-gray-900 dark:text-gray-100">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            <th class="whitespace-nowrap py-1 px-2 font-semibold text-gray-600 dark:text-gray-300">
                                スタッフ
                            </th>
                            <th class="whitespace-nowrap py-1 px-2 font-semibold text-amber-700/90 dark:text-amber-400/90">
                                ☀ L
                            </th>
                            <th class="whitespace-nowrap py-1 px-2 font-semibold text-indigo-700/90 dark:text-indigo-400/90">
                                🌙 D
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
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
                                    'red' => 'bg-red-100 text-red-700',
                                    'green' => 'bg-green-100 text-green-700',
                                    default => 'bg-gray-100 text-gray-700',
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
                                        return '<span class="text-[10px] text-gray-400 dark:text-gray-500">—</span>';
                                    }

                                    $plannedDisp = $plannedStart ?: '-';

                                    $left = '<span class="font-mono tabular-nums text-[10px] text-gray-500">'.$plannedDisp.'</span>';
                                    $arrow = '<span class="mx-1 text-[10px] text-gray-300">▶</span>';

                                    return match ($status) {
                                        'clocked' => $left.$arrow.'<span class="text-[10px] select-none">🟢</span>'.
                                            '<span class="font-mono tabular-nums text-[10px] text-gray-900">'.$actualInTime.'</span>',
                                        'extra' => $left.$arrow.'<span class="text-[10px] select-none">🆘</span>'.
                                            '<span class="font-mono tabular-nums text-[10px] text-gray-900">'.$actualInTime.'</span>',
                                        'late' => $left.$arrow.'<span class="text-[10px] select-none">🔴</span>'.
                                            '<span class="text-[10px] text-red-600 dark:text-red-400 ml-1">未出勤</span>',
                                        'future' => $left.$arrow.'<span class="text-[10px] select-none text-gray-500">⚪</span>',
                                        default => $left.$arrow.'<span class="text-[10px] select-none">—</span>',
                                    };
                                };
                            @endphp

                            <tr class="hover:bg-gray-50/80 dark:hover:bg-white/5">
                                <td class="py-1 px-2 align-middle">
                                    <div class="flex min-w-0 flex-wrap items-center gap-x-1.5 gap-y-0.5">
                                        <span class="inline-flex shrink-0 items-center rounded px-1 py-0.5 text-[10px] font-medium leading-none {{ $badgeClass }}">
                                            {{ $roleShort }}
                                        </span>
                                        <span class="min-w-0 truncate font-medium">{{ $staff->name }}</span>
                                    </div>
                                </td>
                                <td class="py-1 px-2 align-middle">
                                    <div class="flex flex-wrap items-center gap-x-0.5 gap-y-0.5">
                                        {!! $renderMeal($lStatus, $lPlanned, $lInTime) !!}
                                    </div>
                                </td>
                                <td class="py-1 px-2 align-middle">
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

