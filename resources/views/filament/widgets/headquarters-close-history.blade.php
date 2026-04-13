@php
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Finance> $financeRows */
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Finance> $auditRows */
    /** @var string $range2Label */
    /** @var string $range3Label */
@endphp

<x-filament-widgets::widget>
    <div class="space-y-3">
        <x-filament::section :compact="true">
            <x-slot name="heading">
                <span class="inline-flex max-w-full items-center gap-1 rounded-r-md border-l-4 border-red-500 bg-white py-0.5 pl-1.5 pr-1.5 text-[11px] font-bold tracking-tight text-gray-950 shadow-sm ring-1 ring-gray-200 dark:border-red-400 dark:bg-gray-950 dark:text-white dark:ring-gray-700 sm:text-xs" title="Clôture caisse (7 derniers jours)">
                    <span class="select-none" aria-hidden="true">🍄</span>
                    <span class="truncate">Caisse·7j</span>
                </span>
            </x-slot>
            <x-slot name="description">
                <span class="inline-flex max-w-full items-center gap-1 rounded-md border border-yellow-200 bg-yellow-50/90 px-1 py-0.5 text-[10px] font-semibold text-yellow-950 dark:border-yellow-500/30 dark:bg-yellow-950/40 dark:text-yellow-50 sm:text-[11px]">
                    <span class="select-none opacity-80" aria-hidden="true">🪙</span>
                    <span class="truncate">{{ $range2Label }}</span>
                </span>
            </x-slot>

            @if ($financeRows->isEmpty())
                <p class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-2 py-2 text-[11px] font-semibold text-gray-800 dark:border-gray-600 dark:bg-gray-900/50 dark:text-gray-100">Aucune donnée.</p>
            @else
                <div class="w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-950">
                    <table class="w-full table-fixed border-collapse text-[10px] leading-tight text-gray-950 sm:text-[11px] dark:text-gray-100" title="vnts=ventes, chips=pourboires">
                        <thead>
                            <tr class="border-b border-sky-100 bg-sky-50 text-sky-950 dark:border-sky-900/50 dark:bg-sky-950/40 dark:text-sky-50">
                                <th class="w-[11%] px-0.5 py-1 text-left font-bold" title="Date">Dt</th>
                                <th class="w-[14%] px-0.5 py-1 font-bold" title="Service">sh</th>
                                <th class="w-[19%] px-0.5 py-1 text-right font-bold" title="Ventes">vnts</th>
                                <th class="w-[19%] px-0.5 py-1 text-right font-bold" title="Pourboires">chips</th>
                                <th class="w-[17%] px-0.5 py-1 text-right font-bold" title="Écart">Δ</th>
                                <th class="w-[20%] px-0.5 py-1 font-bold" title="Statut">st</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($financeRows as $f)
                                @php
                                    $verdict = (string) ($f->verdict ?? '');
                                    $verdictUi = match ($verdict) {
                                        'bravo' => ['label' => 'OK', 'class' => 'border border-emerald-200 bg-emerald-50 text-emerald-950 ring-1 ring-emerald-100 dark:border-emerald-700/50 dark:bg-emerald-950/50 dark:text-emerald-50 dark:ring-emerald-800/40'],
                                        'plus_error' => ['label' => '+', 'class' => 'border border-yellow-200 bg-yellow-50 text-yellow-950 ring-1 ring-yellow-100 dark:border-yellow-600/40 dark:bg-yellow-950/40 dark:text-yellow-50 dark:ring-yellow-800/30'],
                                        'minus_error' => ['label' => '−', 'class' => 'border border-orange-200 bg-orange-50 text-orange-950 ring-1 ring-orange-100 dark:border-orange-700/50 dark:bg-orange-950/40 dark:text-orange-50 dark:ring-orange-900/30'],
                                        'failed' => ['label' => '×', 'class' => 'border border-red-200 bg-red-50 text-red-950 ring-1 ring-red-100 dark:border-red-700/50 dark:bg-red-950/40 dark:text-red-50 dark:ring-red-900/30'],
                                        default => ['label' => $verdict !== '' ? $verdict : '—', 'class' => 'border border-gray-200 bg-gray-50 text-gray-900 ring-1 ring-gray-100 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-800'],
                                    };
                                    $shift = (string) ($f->shift ?? '');
                                    $shiftClass = $shift === 'lunch'
                                        ? 'rounded border border-yellow-200 bg-yellow-50/90 px-0.5 py-px text-[9px] font-bold uppercase text-yellow-950 ring-1 ring-yellow-100 dark:border-yellow-500/40 dark:bg-yellow-950/35 dark:text-yellow-50 sm:text-[10px]'
                                        : ($shift === 'dinner'
                                            ? 'rounded border border-emerald-200 bg-emerald-50/80 px-0.5 py-px text-[9px] font-bold uppercase text-emerald-950 ring-1 ring-emerald-100 dark:border-emerald-600/40 dark:bg-emerald-950/35 dark:text-emerald-50 sm:text-[10px]'
                                            : 'text-gray-700 dark:text-gray-300');
                                @endphp
                                <tr class="odd:bg-white even:bg-gray-50/80 dark:odd:bg-gray-950 dark:even:bg-gray-900/40">
                                    <td class="px-0.5 py-0.5 font-mono tabular-nums font-semibold text-gray-950 dark:text-gray-50">{{ $f->business_date?->format('n/j') ?? '—' }}</td>
                                    <td class="px-0.5 py-0.5">
                                        <span class="{{ $shiftClass }}">{{ $shift !== '' ? $shift : '—' }}</span>
                                    </td>
                                    <td class="truncate px-0.5 py-0.5 text-right font-mono tabular-nums text-gray-950 dark:text-gray-50" title="{{ number_format((float) ($f->recettes ?? 0), 0, '.', ' ') }}">{{ number_format((float) ($f->recettes ?? 0), 0, '.', ' ') }}</td>
                                    <td class="truncate px-0.5 py-0.5 text-right font-mono tabular-nums text-gray-950 dark:text-gray-50">{{ number_format((float) ($f->final_tip_amount ?? 0), 0, '.', ' ') }}</td>
                                    <td class="truncate px-0.5 py-0.5 text-right font-mono tabular-nums font-semibold text-gray-950 dark:text-gray-50">{{ number_format((float) ($f->final_difference ?? 0), 0, '.', ' ') }}</td>
                                    <td class="px-0.5 py-0.5">
                                        <span class="inline-flex min-w-0 items-center justify-center rounded px-0.5 py-px text-[9px] font-extrabold sm:text-[10px] {{ $verdictUi['class'] }}">{{ $verdictUi['label'] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section :compact="true">
            <x-slot name="heading">
                <span class="inline-flex max-w-full items-center gap-1 rounded-r-md border-l-4 border-emerald-600 bg-white py-0.5 pl-1.5 pr-1.5 text-[11px] font-bold tracking-tight text-gray-950 shadow-sm ring-1 ring-gray-200 dark:border-emerald-500 dark:bg-gray-950 dark:text-white dark:ring-gray-700 sm:text-xs" title="Clôture journée — responsables (3 jours)">
                    <span class="select-none" aria-hidden="true">🌟</span>
                    <span class="truncate">Close·3j</span>
                </span>
            </x-slot>
            <x-slot name="description">
                <span class="inline-flex max-w-full items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50/90 px-1 py-0.5 text-[10px] font-semibold text-emerald-950 dark:border-emerald-600/40 dark:bg-emerald-950/40 dark:text-emerald-50 sm:text-[11px]">
                    <span class="select-none opacity-80" aria-hidden="true">🪙</span>
                    <span class="truncate">{{ $range3Label }}</span>
                </span>
            </x-slot>

            @if ($auditRows->isEmpty())
                <p class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-2 py-2 text-[11px] font-semibold text-gray-800 dark:border-gray-600 dark:bg-gray-900/50 dark:text-gray-100">Aucune donnée.</p>
            @else
                <div class="w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-950">
                    <table class="w-full table-fixed border-collapse text-[10px] leading-tight text-gray-950 sm:text-[11px] dark:text-gray-100">
                        <thead>
                            <tr class="border-b border-emerald-100 bg-emerald-50 text-emerald-950 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-50">
                                <th class="w-[13%] px-0.5 py-1 font-bold" title="Date">Dt</th>
                                <th class="w-[14%] px-0.5 py-1 font-bold" title="Service">sh</th>
                                <th class="w-[22%] px-0.5 py-1 font-bold" title="Responsable">R</th>
                                <th class="w-[22%] px-0.5 py-1 font-bold" title="Panneau">P</th>
                                <th class="w-[29%] px-0.5 py-1 font-bold" title="Mise à jour">T</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($auditRows as $f)
                                @php
                                    $shift = (string) ($f->shift ?? '');
                                    $shiftClass = $shift === 'lunch'
                                        ? 'rounded border border-yellow-200 bg-yellow-50/90 px-0.5 py-px text-[9px] font-bold uppercase text-yellow-950 ring-1 ring-yellow-100 dark:border-yellow-500/40 dark:bg-yellow-950/35 dark:text-yellow-50 sm:text-[10px]'
                                        : ($shift === 'dinner'
                                            ? 'rounded border border-emerald-200 bg-emerald-50/80 px-0.5 py-px text-[9px] font-bold uppercase text-emerald-950 ring-1 ring-emerald-100 dark:border-emerald-600/40 dark:bg-emerald-950/35 dark:text-emerald-50 sm:text-[10px]'
                                            : 'text-gray-700 dark:text-gray-300');
                                @endphp
                                <tr class="odd:bg-white even:bg-gray-50/80 dark:odd:bg-gray-950 dark:even:bg-gray-900/40">
                                    <td class="px-0.5 py-0.5 font-mono tabular-nums font-semibold text-gray-950 dark:text-gray-50">{{ $f->business_date?->format('n/j') ?? '—' }}</td>
                                    <td class="px-0.5 py-0.5">
                                        <span class="{{ $shiftClass }}">{{ $shift !== '' ? $shift : '—' }}</span>
                                    </td>
                                    <td class="max-w-0 truncate px-0.5 py-0.5 font-semibold text-gray-950 dark:text-gray-50" title="{{ $f->responsibleStaff?->name ?? '' }}">{{ $f->responsibleStaff?->name ?? '—' }}</td>
                                    <td class="max-w-0 truncate px-0.5 py-0.5 text-gray-900 dark:text-gray-100" title="{{ $f->panelOperator?->name ?? '' }}">{{ $f->panelOperator?->name ?? '—' }}</td>
                                    <td class="truncate px-0.5 py-0.5 font-mono tabular-nums text-[9px] text-gray-800 dark:text-gray-200 sm:text-[10px]">{{ $f->updated_at?->format('n/j H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
