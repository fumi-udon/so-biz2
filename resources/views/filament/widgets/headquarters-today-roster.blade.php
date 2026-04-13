@php
    /** @var string $businessDate */
    /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $rows */
@endphp

<x-filament-widgets::widget>
    <div wire:poll.30s class="w-full max-w-full overflow-hidden">
        <x-filament::section :compact="true" class="!fi-section-content-ctn">
            <x-slot name="heading">
                <span class="text-sm font-bold text-gray-950 dark:text-white">Présences · live</span>
                <span class="ml-1.5 text-[11px] font-normal text-gray-800 dark:text-gray-100">{{ $businessDate }}</span>
            </x-slot>
            <x-slot name="description">
                <span class="text-[10px] font-medium text-gray-800 dark:text-gray-200">MAJ 30s · vert = en service</span>
            </x-slot>

            @if ($rows->isEmpty())
                <p class="text-[11px] font-medium text-gray-800 dark:text-gray-100">Aucun membre.</p>
            @else
                <div class="w-full overflow-hidden rounded-md border border-gray-300 dark:border-gray-700">
                    <table class="w-full table-fixed border-collapse text-left text-[11px]">
                        <colgroup>
                            <col class="w-[38%] sm:w-[40%]" />
                            <col class="w-[31%] sm:w-[30%]" />
                            <col class="w-[31%] sm:w-[30%]" />
                        </colgroup>
                        <thead class="border-b-2 border-gray-400 bg-gray-50 dark:border-gray-600 dark:bg-gray-900">
                            <tr>
                                <th class="px-1 py-1 font-bold text-[10px] uppercase tracking-wide text-gray-950 dark:text-gray-50 sm:px-1.5 sm:text-[11px]">Eq.</th>
                                <th class="bg-amber-50/50 px-0.5 py-1 text-center dark:bg-amber-950/30" title="Déjeuner">
                                    <div class="flex flex-col items-center gap-0 leading-none">
                                        <x-filament::icon icon="heroicon-m-sun" class="mx-auto h-4 w-4 text-amber-700 dark:text-amber-400" />
                                        <span class="text-[9px] font-black text-amber-950 dark:text-amber-50 sm:text-[10px]">AM</span>
                                    </div>
                                </th>
                                <th class="bg-indigo-50/50 px-0.5 py-1 text-center dark:bg-indigo-950/30" title="Dîner">
                                    <div class="flex flex-col items-center gap-0 leading-none">
                                        <x-filament::icon icon="heroicon-m-moon" class="mx-auto h-4 w-4 text-indigo-700 dark:text-indigo-400" />
                                        <span class="text-[9px] font-black text-indigo-950 dark:text-indigo-50 sm:text-[10px]">PM</span>
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
                                        'px-1 py-1 align-middle sm:px-1.5',
                                        'bg-emerald-50/50 dark:bg-emerald-950/20' => $rowPulse,
                                    ])>
                                        <div class="flex min-w-0 items-center gap-1">
                                            <span class="inline-flex shrink-0" title="{{ $row['role_label'] ?? '' }}">
                                                <x-filament::icon
                                                    :icon="$roleIcon"
                                                    class="h-3.5 w-3.5 sm:h-4 sm:w-4 {{ $iconTone }}"
                                                />
                                            </span>
                                            <div @class([
                                                'flex min-w-0 flex-1 items-center gap-0.5 rounded-sm py-0.5',
                                                'animate-pulse ring-1 ring-emerald-500 dark:ring-emerald-400' => $rowPulse,
                                            ])>
                                                <span class="min-w-0 flex-1 truncate text-[10px] font-semibold text-gray-950 dark:text-gray-50 sm:text-[11px]" title="{{ $staff->name }}">
                                                    {{ $staff->name }}
                                                </span>

                                                @if ($rowPulse)
                                                    <span class="shrink-0 rounded-[2px] bg-emerald-600 px-0.5 py-px text-[9px] font-black text-white dark:bg-emerald-500 sm:text-[10px]">IN</span>
                                                @elseif ($status === 'finished')
                                                    <span class="shrink-0 rounded-[2px] bg-gray-300 px-0.5 py-px text-[9px] font-bold text-gray-900 dark:bg-gray-100 sm:text-[10px]">OUT</span>
                                                @elseif ($status === 'no_show' || $status === 'idle')
                                                    <span class="shrink-0 rounded-[2px] bg-amber-200 px-0.5 py-px text-[9px] font-bold text-amber-950 dark:text-amber-100 sm:text-[10px]">!</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    <td class="bg-amber-50/15 px-0.5 py-1 text-center align-middle dark:bg-amber-950/10">
                                        <div class="inline-flex max-w-full flex-wrap items-center justify-center gap-0.5 font-mono text-[10px] tabular-nums leading-tight sm:text-[11px]">
                                            @if ($lStatus === 'none')
                                                <span class="text-gray-700 dark:text-gray-200">—</span>
                                            @else
                                                <span class="text-gray-900 dark:text-gray-100">{{ $lPlanned ?? '—' }}</span>
                                                @if ($lIn)
                                                    <span class="opacity-60">▶</span>
                                                    <span class="font-bold {{ $actualTimeColor($lStatus) }}">{{ $lIn }}</span>
                                                @endif
                                            @endif
                                        </div>
                                    </td>

                                    <td class="bg-indigo-50/15 px-0.5 py-1 text-center align-middle dark:bg-indigo-950/10">
                                        <div class="inline-flex max-w-full flex-wrap items-center justify-center gap-0.5 font-mono text-[10px] tabular-nums leading-tight sm:text-[11px]">
                                            @if ($dStatus === 'none')
                                                <span class="text-gray-700 dark:text-gray-200">—</span>
                                            @else
                                                <span class="text-gray-900 dark:text-gray-100">{{ $dPlanned ?? '—' }}</span>
                                                @if ($dIn)
                                                    <span class="opacity-60">▶</span>
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
