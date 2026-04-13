@php
    /** @var string $businessDate */
    /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $rows */
@endphp

<x-filament-widgets::widget>
    <div wire:poll.30s class="max-w-full">
        <x-filament::section :compact="true" class="!fi-section-content-ctn">
            <x-slot name="heading">
                <span class="text-base font-bold text-gray-950 dark:text-white">Présences du jour (temps réel)</span>
                <span class="ml-2 text-[12px] font-normal text-gray-800 dark:text-gray-100">{{ $businessDate }}</span>
            </x-slot>
            <x-slot name="description">
                <span class="text-[12px] font-medium text-gray-900 dark:text-gray-100">Mise à jour toutes les 30 s · vert si en service</span>
            </x-slot>

            @if ($rows->isEmpty())
                <p class="text-[12px] font-medium text-gray-800 dark:text-gray-100">Aucun membre à afficher.</p>
            @else
                <div class="w-full overflow-x-auto rounded-md border border-gray-300 dark:border-gray-700">
                    <table class="w-full text-left text-[12px] whitespace-nowrap">
                        <thead class="border-b-2 border-gray-400 bg-gray-50 dark:border-gray-600 dark:bg-gray-900">
                            <tr>
                                <th class="px-2 py-1.5 font-bold text-[12px] text-gray-950 dark:text-gray-50">Équipe</th>
                                <th class="bg-amber-50/50 px-2 py-1.5 text-center text-[12px] dark:bg-amber-950/30">
                                    <div class="inline-flex items-center justify-center gap-1">
                                        <x-filament::icon icon="heroicon-m-sun" class="h-5 w-5 shrink-0 text-amber-700 dark:text-amber-400" />
                                        <span class="font-bold text-amber-950 dark:text-amber-50">Déjeuner (AM)</span>
                                    </div>
                                </th>
                                <th class="bg-indigo-50/50 px-2 py-1.5 text-center text-[12px] dark:bg-indigo-950/30">
                                    <div class="inline-flex items-center justify-center gap-1">
                                        <x-filament::icon icon="heroicon-m-moon" class="h-5 w-5 shrink-0 text-indigo-700 dark:text-indigo-400" />
                                        <span class="font-bold text-indigo-950 dark:text-indigo-50">Dîner (PM)</span>
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
                                    $roleCategory = (string) ($row['role_category'] ?? 'other');
                                    $roleIcon = (string) ($row['role_icon'] ?? 'heroicon-m-squares-2x2');
                                    $iconTone = match ($roleCategory) {
                                        'kitchen' => 'text-red-800 dark:text-red-200',
                                        'hall' => 'text-emerald-900 dark:text-emerald-200',
                                        default => 'text-gray-900 dark:text-gray-100',
                                    };

                                    $lStatus = (string) ($row['lunch_status'] ?? 'none');
                                    $dStatus = (string) ($row['dinner_status'] ?? 'none');

                                    $lIn = $row['lunch_in_time'] ?? null;
                                    $dIn = $row['dinner_in_time'] ?? null;

                                    $lPlanned = $row['lunch_scheduled_start'] ?? null;
                                    $dPlanned = $row['dinner_scheduled_start'] ?? null;

                                    $actualTimeColor = function (string $st) {
                                        if ($st === 'late') {
                                            return 'text-red-800 dark:text-red-200';
                                        }
                                        if ($st === 'clocked' || $st === 'extra') {
                                            return 'text-emerald-800 dark:text-emerald-200';
                                        }

                                        return 'text-gray-950 dark:text-gray-50';
                                    };
                                @endphp
                                <tr wire:key="roster-{{ $staff->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-900/50">
                                    <td @class([
                                        'px-2 py-1.5 align-middle',
                                        'bg-emerald-50/50 dark:bg-emerald-950/20' => $rowPulse,
                                    ])>
                                        <div class="flex items-center gap-1.5">
                                            <span class="inline-flex shrink-0" title="{{ $row['role_label'] ?? '' }}">
                                                <x-filament::icon
                                                    :icon="$roleIcon"
                                                    class="h-5 w-5 {{ $iconTone }}"
                                                />
                                            </span>
                                            <div @class([
                                                'flex min-w-0 flex-1 items-center gap-1 rounded-sm px-1 py-0.5',
                                                'animate-pulse ring-1 ring-emerald-500 dark:ring-emerald-400' => $rowPulse,
                                            ])>
                                                <span class="max-w-[88px] truncate text-[12px] font-semibold text-gray-950 dark:text-gray-50 sm:max-w-[140px]" title="{{ $staff->name }}">
                                                    {{ $staff->name }}
                                                </span>

                                                @if ($rowPulse)
                                                    <span class="rounded-[2px] bg-emerald-600 px-1 py-[1px] text-[12px] font-black uppercase tracking-wider text-white dark:bg-emerald-500">IN</span>
                                                @elseif ($status === 'finished')
                                                    <span class="rounded-[2px] bg-gray-300 px-1 py-[1px] text-[12px] font-bold text-gray-900 dark:bg-gray-100">OUT</span>
                                                @elseif ($status === 'no_show' || $status === 'idle')
                                                    <span class="rounded-[2px] bg-amber-200 px-1 py-[1px] text-[12px] font-bold text-amber-950 dark:bg-amber-100">!</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    <td class="bg-amber-50/20 px-2 py-1.5 text-center align-middle dark:bg-amber-950/10">
                                        <div class="inline-flex items-center gap-1 font-mono text-[12px] tabular-nums">
                                            @if ($lStatus === 'none')
                                                <span class="text-gray-700 dark:text-gray-200">—</span>
                                            @else
                                                <span class="text-gray-900 dark:text-gray-100">{{ $lPlanned ?? '—' }}</span>
                                                @if ($lIn)
                                                    <span class="opacity-70 text-gray-600 dark:text-gray-300">▶</span>
                                                    <span class="font-bold {{ $actualTimeColor($lStatus) }}">{{ $lIn }}</span>
                                                @endif
                                            @endif
                                        </div>
                                    </td>

                                    <td class="bg-indigo-50/20 px-2 py-1.5 text-center align-middle dark:bg-indigo-950/10">
                                        <div class="inline-flex items-center gap-1 font-mono text-[12px] tabular-nums">
                                            @if ($dStatus === 'none')
                                                <span class="text-gray-700 dark:text-gray-200">—</span>
                                            @else
                                                <span class="text-gray-900 dark:text-gray-100">{{ $dPlanned ?? '—' }}</span>
                                                @if ($dIn)
                                                    <span class="opacity-70 text-gray-600 dark:text-gray-300">▶</span>
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
