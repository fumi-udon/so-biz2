@php
    /** @var string $businessDate */
    /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $rows */
@endphp

<x-filament-widgets::widget>
    <div wire:poll.30s class="max-w-full">
        <x-filament::section :compact="true" class="!fi-section-content-ctn">
            <x-slot name="heading">
                <span class="text-sm font-bold text-gray-950 dark:text-white">本日の出勤簿（リアルタイム）</span>
                <span class="ml-2 text-[10px] font-normal text-gray-500 dark:text-gray-400">{{ $businessDate }}</span>
            </x-slot>
            <x-slot name="description">
                <span class="text-[10px] text-gray-600 dark:text-gray-300">30秒ごとに更新 · 勤務中は緑パルス</span>
            </x-slot>

            @if ($rows->isEmpty())
                <p class="text-[10px] text-gray-500 dark:text-gray-400">表示するスタッフがありません。</p>
            @else
                <div class="w-full overflow-x-auto rounded-md border border-gray-300 dark:border-gray-700">
                    <table class="w-full text-left text-[10px] whitespace-nowrap">
                        <thead class="border-b-2 border-gray-400 bg-gray-50 dark:border-gray-600 dark:bg-gray-900">
                            <tr>
                                <th class="px-2 py-1.5 font-bold text-[10px] text-gray-900 dark:text-gray-100">スタッフ</th>
                                <th class="bg-amber-50/50 px-2 py-1.5 text-center text-[10px] dark:bg-amber-950/30">
                                    <div class="inline-flex items-center justify-center gap-1">
                                        <x-filament::icon icon="heroicon-m-sun" class="h-3.5 w-3.5 text-amber-500 dark:text-amber-400" />
                                        <span class="font-bold text-amber-900 dark:text-amber-200">ランチ (AM)</span>
                                    </div>
                                </th>
                                <th class="bg-indigo-50/50 px-2 py-1.5 text-center text-[10px] dark:bg-indigo-950/30">
                                    <div class="inline-flex items-center justify-center gap-1">
                                        <x-filament::icon icon="heroicon-m-moon" class="h-3.5 w-3.5 text-indigo-500 dark:text-indigo-400" />
                                        <span class="font-bold text-indigo-900 dark:text-indigo-200">ディナー (PM)</span>
                                    </div>
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-950">
                            @foreach ($rows as $row)
                                @php
                                    $staff = $row['staff'];
                                    $status = (string) ($row['status'] ?? 'idle');
                                    $rowPulse = $status === 'working';
                                    
                                    $lStatus = (string) ($row['lunch_status'] ?? 'none');
                                    $dStatus = (string) ($row['dinner_status'] ?? 'none');
                                    
                                    $lIn = $row['lunch_in_time'] ?? null;
                                    $dIn = $row['dinner_in_time'] ?? null;
                                    
                                    $lPlanned = $row['lunch_scheduled_start'] ?? null;
                                    $dPlanned = $row['dinner_scheduled_start'] ?? null;

                                    // 実際の打刻時間の色を判定する関数（遅刻なら赤！）
                                    $actualTimeColor = function (string $st) {
                                        if ($st === 'late') return 'text-red-600 dark:text-red-400';
                                        if ($st === 'clocked' || $st === 'extra') return 'text-emerald-600 dark:text-emerald-400';
                                        return 'text-gray-900 dark:text-gray-100';
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/50">
                                    <td @class([
                                        'px-2 py-1.5 align-middle',
                                        'bg-emerald-50/50 dark:bg-emerald-950/20' => $rowPulse,
                                    ])>
                                        <div class="flex items-center gap-1">
                                            <div @class([
                                                'flex items-center gap-1 rounded-sm px-1 py-0.5',
                                                'animate-pulse ring-1 ring-emerald-500 dark:ring-emerald-400' => $rowPulse,
                                            ])>
                                                {{-- スタッフ名の文字サイズを text-[9px] に小さく調整（ここ！） --}}
                                                <span class="max-w-[80px] truncate text-[9px] text-gray-900 dark:text-gray-100 sm:max-w-[120px]" title="{{ $staff->name }}">
                                                    {{ $staff->name }}
                                                </span>
                                                
                                                @if ($rowPulse)
                                                    <span class="rounded-[2px] bg-emerald-600 px-1 py-[1px] text-[10px] font-black uppercase tracking-wider text-white dark:bg-emerald-500">IN</span>
                                                @elseif ($status === 'finished')
                                                    <span class="rounded-[2px] bg-gray-200 px-1 py-[1px] text-[10px] font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-400">OUT</span>
                                                @elseif ($status === 'no_show' || $status === 'idle')
                                                    <span class="rounded-[2px] bg-amber-100 px-1 py-[1px] text-[10px] font-bold text-amber-700 dark:bg-amber-900/50 dark:text-amber-400">!</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    <td class="bg-amber-50/20 px-2 py-1.5 text-center align-middle dark:bg-amber-950/10">
                                        <div class="inline-flex items-center gap-1 font-mono text-[10px] tabular-nums">
                                            @if ($lStatus === 'none')
                                                <span class="text-gray-400 dark:text-gray-500">—</span>
                                            @else
                                                <span class="text-gray-500 dark:text-gray-400">{{ $lPlanned ?? '—' }}</span>
                                                @if ($lIn)
                                                    <span class="opacity-50 text-gray-400 dark:text-gray-500">▶</span>
                                                    <span class="font-bold {{ $actualTimeColor($lStatus) }}">{{ $lIn }}</span>
                                                @endif
                                            @endif
                                        </div>
                                    </td>

                                    <td class="bg-indigo-50/20 px-2 py-1.5 text-center align-middle dark:bg-indigo-950/10">
                                        <div class="inline-flex items-center gap-1 font-mono text-[10px] tabular-nums">
                                            @if ($dStatus === 'none')
                                                <span class="text-gray-400 dark:text-gray-500">—</span>
                                            @else
                                                <span class="text-gray-500 dark:text-gray-400">{{ $dPlanned ?? '—' }}</span>
                                                @if ($dIn)
                                                    <span class="opacity-50 text-gray-400 dark:text-gray-500">▶</span>
                                                    <span class="font-bold {{ $actualTimeColor($dStatus) }}">{{ $dIn }}</span>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-widgets::widget>