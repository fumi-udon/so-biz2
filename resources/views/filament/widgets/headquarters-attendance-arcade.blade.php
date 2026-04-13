@php
    /** @var string $monthLabel */
    /** @var int $maxLate */
    /** @var \Illuminate\Support\Collection<int, array{staff: \App\Models\Staff, late: int, absent_equiv: int, warn: bool}> $rows */
@endphp

<x-filament-widgets::widget>
    <x-filament::section :compact="true">
        <x-slot name="heading">
            <span class="inline-flex max-w-full items-center gap-1 rounded-r-md border-l-4 border-emerald-600 bg-white py-0.5 pl-1.5 pr-1.5 text-[11px] font-bold tracking-tight text-gray-950 shadow-sm ring-1 ring-gray-200 dark:border-emerald-500 dark:bg-gray-950 dark:text-white dark:ring-gray-700 sm:text-xs" title="Synthèse présences (mois en cours)">
                <span class="select-none" aria-hidden="true">🌟</span>
                <span class="truncate">Prés.·mois</span>
            </span>
        </x-slot>
        <x-slot name="description">
            <div class="space-y-0.5">
                <span class="inline-flex max-w-full items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50/90 px-1 py-0.5 text-[10px] font-semibold text-emerald-950 dark:border-emerald-600/40 dark:bg-emerald-950/40 dark:text-emerald-50 sm:text-[11px]">
                    <span class="select-none opacity-80" aria-hidden="true">🪙</span>
                    <span class="truncate">{{ $monthLabel }}</span>
                </span>
                <p class="text-[9px] font-medium leading-snug text-gray-900 dark:text-gray-100 sm:text-[10px]">
                    Ret = <span class="font-mono">late&gt;0</span> · Abs = j. absents · <span class="text-amber-900 dark:text-amber-100">⚠ 3+ ret. / 2+j</span>
                </p>
            </div>
        </x-slot>

        @if ($rows->isEmpty())
            <p class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-2 py-2 text-[11px] font-semibold text-gray-800 dark:border-gray-600 dark:bg-gray-900/50 dark:text-gray-100">Aucun membre.</p>
        @else
            <div class="w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-950">
                <table class="w-full table-fixed border-collapse text-[10px] leading-tight text-gray-950 sm:text-[11px] dark:text-gray-100">
                    <thead>
                        <tr class="border-b border-emerald-100 bg-emerald-50 text-emerald-950 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-50">
                            <th class="w-[36%] px-0.5 py-1 text-left font-bold" title="Membre">N.</th>
                            <th class="w-[18%] px-0.5 py-1 text-right font-bold" title="Retards (fois)">ret</th>
                            <th class="w-[18%] px-0.5 py-1 text-right font-bold" title="Absence équivalente (jours)">abs</th>
                            <th class="w-[28%] px-0.5 py-1 font-bold" title="Échelle relative">~</th>
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
                                    $late >= 3 => 'border-red-200 bg-red-50 text-red-950 ring-1 ring-red-100 dark:border-red-700/50 dark:bg-red-950/40 dark:text-red-50',
                                    $late > 0 => 'border-yellow-200 bg-yellow-50 text-yellow-950 ring-1 ring-yellow-100 dark:border-yellow-500/40 dark:bg-yellow-950/40 dark:text-yellow-50',
                                    default => 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300',
                                };
                            @endphp
                            <tr
                                @class([
                                    'odd:bg-white even:bg-gray-50/80 dark:odd:bg-gray-950 dark:even:bg-gray-900/40',
                                    'bg-amber-50/90 ring-1 ring-amber-300/80 dark:bg-amber-950/25 dark:ring-amber-600/40' => $warn,
                                ])
                            >
                                <td class="max-w-0 truncate px-0.5 py-0.5 font-bold text-gray-950 dark:text-gray-50" title="{{ $staff->name }}">
                                    {{ $staff->name }}
                                </td>
                                <td class="px-0.5 py-0.5 text-right">
                                    <span class="inline-flex min-w-0 flex-col items-end rounded border px-1 py-0.5 font-mono tabular-nums font-bold {{ $latePillClass }}">
                                        <span class="text-[10px] leading-none sm:text-[11px]">{{ $late }}</span>
                                    </span>
                                </td>
                                <td class="px-0.5 py-0.5 text-right">
                                    <span
                                        @class([
                                            'inline-flex min-w-0 flex-col items-end rounded border px-1 py-0.5 font-mono tabular-nums font-bold',
                                            'border-amber-200 bg-amber-50 text-amber-950 ring-1 ring-amber-100 dark:border-amber-600/40 dark:bg-amber-950/35 dark:text-amber-50' => $abs > 0,
                                            'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300' => $abs === 0,
                                        ])
                                    >
                                        <span class="text-[10px] leading-none sm:text-[11px]">{{ $abs }}</span>
                                    </span>
                                </td>
                                <td class="px-0.5 py-0.5 align-middle">
                                    <div class="flex items-center gap-1">
                                        <div class="h-1.5 min-w-0 flex-1 overflow-hidden rounded border border-emerald-200 bg-gray-100 dark:border-emerald-800/50 dark:bg-gray-900">
                                            <div
                                                class="h-full rounded-sm bg-gradient-to-r from-emerald-500 to-sky-400 dark:from-emerald-600 dark:to-sky-500"
                                                style="width: {{ $pct }}%"
                                            ></div>
                                        </div>
                                        <span class="shrink-0 font-mono text-[9px] tabular-nums text-gray-800 dark:text-gray-200 sm:text-[10px]">{{ $pct }}%</span>
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
