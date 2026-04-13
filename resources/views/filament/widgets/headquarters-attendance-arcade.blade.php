@php
    /** @var string $monthLabel */
    /** @var int $maxLate */
    /** @var \Illuminate\Support\Collection<int, array{staff: \App\Models\Staff, late: int, absent_equiv: int, warn: bool}> $rows */
@endphp

<x-filament-widgets::widget>
    <x-filament::section :compact="true">
        <x-slot name="heading">
            <span class="inline-flex items-center gap-1.5 rounded-r-md border-l-4 border-emerald-600 bg-white py-0.5 pl-2 pr-2 text-[12px] font-bold tracking-tight text-gray-950 shadow-sm ring-1 ring-gray-200 dark:border-emerald-500 dark:bg-gray-950 dark:text-white dark:ring-gray-700 sm:text-sm">
                <span class="select-none text-[11px]" aria-hidden="true">🌟</span>
                Synthèse présences (mois en cours)
            </span>
        </x-slot>
        <x-slot name="description">
            <div class="space-y-1">
                <span class="inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50/90 px-1.5 py-0.5 text-[12px] font-semibold text-emerald-950 shadow-sm dark:border-emerald-600/40 dark:bg-emerald-950/40 dark:text-emerald-50">
                    <span class="select-none opacity-80" aria-hidden="true">🪙</span>
                    Période : {{ $monthLabel }} (1<sup>er</sup> → aujourd’hui)
                </span>
                <p class="text-[11px] font-medium leading-snug text-gray-900 dark:text-gray-100 sm:text-[12px]">
                    <span class="font-bold text-gray-950 dark:text-gray-50">Retards (fois)</span> : lignes du mois avec <span class="font-mono">late_minutes &gt; 0</span>.<br>
                    <span class="font-bold text-gray-950 dark:text-gray-50">Abs. équivalent (jours)</span> : jours comptés absents / fermeture. Ligne surlignée si <span class="font-bold text-amber-900 dark:text-amber-100">≥ 3 retards</span> ou <span class="font-bold text-amber-900 dark:text-amber-100">≥ 2 jours</span>.
                </p>
            </div>
        </x-slot>

        @if ($rows->isEmpty())
            <p class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-2 py-2 text-[12px] font-semibold text-gray-800 dark:border-gray-600 dark:bg-gray-900/50 dark:text-gray-100 sm:text-sm">Aucun membre à afficher.</p>
        @else
            <div class="-mx-1 overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-950 md:mx-0">
                <table class="w-full min-w-[20rem] border-collapse text-left text-[12px] leading-tight text-gray-950 sm:min-w-[22rem] sm:text-sm dark:text-gray-100">
                    <thead>
                        <tr class="border-b border-emerald-100 bg-emerald-50 text-emerald-950 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-50">
                            <th class="whitespace-nowrap px-1 py-1 font-bold sm:px-2 sm:py-1.5">Membre</th>
                            <th class="whitespace-nowrap px-1 py-1 text-right font-bold sm:px-2 sm:py-1.5">
                                <span class="block">Retards</span>
                                <span class="block text-[10px] font-semibold normal-case opacity-95 sm:text-[11px]">(fois)</span>
                            </th>
                            <th class="whitespace-nowrap px-1 py-1 text-right font-bold sm:px-2 sm:py-1.5">
                                <span class="block">Abs. équ.</span>
                                <span class="block text-[10px] font-semibold normal-case opacity-95 sm:text-[11px]">(jours)</span>
                            </th>
                            <th class="min-w-[4rem] px-1 py-1 font-bold sm:min-w-[5rem] sm:px-2 sm:py-1.5">Échelle</th>
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
                                <td class="max-w-[8rem] truncate px-1 py-0.5 font-bold text-gray-950 dark:text-gray-50 sm:max-w-[12rem] sm:px-2 sm:py-1" title="{{ $staff->name }}">
                                    {{ $staff->name }}
                                </td>
                                <td class="px-1 py-0.5 text-right sm:px-2 sm:py-1">
                                    <span class="inline-flex min-w-[2.75rem] flex-col items-end rounded-md border px-1.5 py-0.5 font-mono tabular-nums font-bold sm:min-w-[3.25rem] {{ $latePillClass }}">
                                        <span class="text-[12px] leading-none sm:text-sm">{{ $late }}</span>
                                        <span class="text-[10px] font-semibold leading-none opacity-90">fois</span>
                                    </span>
                                </td>
                                <td class="px-1 py-0.5 text-right sm:px-2 sm:py-1">
                                    <span
                                        @class([
                                            'inline-flex min-w-[2.75rem] flex-col items-end rounded-md border px-1.5 py-0.5 font-mono tabular-nums font-bold sm:min-w-[3.25rem]',
                                            'border-amber-200 bg-amber-50 text-amber-950 ring-1 ring-amber-100 dark:border-amber-600/40 dark:bg-amber-950/35 dark:text-amber-50' => $abs > 0,
                                            'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300' => $abs === 0,
                                        ])
                                    >
                                        <span class="text-[12px] leading-none sm:text-sm">{{ $abs }}</span>
                                        <span class="text-[10px] font-semibold leading-none opacity-90">j.</span>
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
                                        <span class="shrink-0 font-mono text-[10px] tabular-nums text-gray-800 dark:text-gray-200 sm:text-[11px]">{{ $pct }}%</span>
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
