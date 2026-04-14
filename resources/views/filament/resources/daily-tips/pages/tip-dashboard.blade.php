<x-filament-panels::page class="max-w-full">
    @php
        $w = $this->weekMatrix;
        $m = $this->monthAnalysis;
        $barColors = ['bg-rose-500', 'bg-amber-500', 'bg-emerald-500', 'bg-sky-500', 'bg-violet-500', 'bg-teal-500', 'bg-orange-500', 'bg-cyan-500'];
        /** Affichage : 1 décimale tronquée, zéros inutiles retirés (agrégats inchangés) */
        $tipSmart = static function (float $v): string {
            $d = floor($v * 10) / 10;

            return rtrim(rtrim(number_format($d, 1, ',', ''), '0'), ',') ?: '0';
        };
        /** Totaux jour : clés Y-m-d (snake_case ou camelCase côté Livewire) + rétrocompat liste indexée */
        $dayMealTotalsMap = $w['day_meal_totals'] ?? $w['dayMealTotals'] ?? [];
        if ($dayMealTotalsMap !== [] && array_is_list($dayMealTotalsMap)) {
            $tmp = [];
            foreach (($w['day_keys'] ?? []) as $i => $dk) {
                $cell = $dayMealTotalsMap[$i] ?? null;
                $tmp[$dk] = is_array($cell)
                    ? $cell
                    : ['lunch' => 0.0, 'dinner' => 0.0, 'total' => 0.0];
            }
            $dayMealTotalsMap = $tmp;
        }
        $weekMealTotalsRow = $w['week_meal_totals'] ?? $w['weekMealTotals'] ?? [];
    @endphp

    {{-- Navigation semaine + mois : bloc Mario, 2 colonnes dès sm --}}
    <div
        class="mb-3 rounded-2xl border-2 border-b-4 border-indigo-400 bg-gradient-to-br from-indigo-50 to-white p-3 shadow-sm ring-1 ring-indigo-200/80 dark:border-indigo-600 dark:from-indigo-950/50 dark:to-gray-950 dark:ring-indigo-900/40"
    >
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:items-start">
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    wire:click="previousWeek"
                    class="inline-flex items-center rounded-xl border-2 border-b-4 border-indigo-500 bg-white px-3 py-1.5 text-xs font-black text-indigo-900 shadow-sm hover:bg-indigo-100 active:translate-y-0.5 active:border-b-2 dark:border-indigo-500 dark:bg-gray-900 dark:text-indigo-100 dark:hover:bg-indigo-950"
                >
                    ◀ Semaine préc.
                </button>
                <span class="min-w-0 text-xs font-bold tabular-nums text-gray-950 dark:text-white sm:text-sm">
                    {{ $this->weekRangeLabel }}
                </span>
                <button
                    type="button"
                    wire:click="nextWeek"
                    class="inline-flex items-center rounded-xl border-2 border-b-4 border-indigo-500 bg-white px-3 py-1.5 text-xs font-black text-indigo-900 shadow-sm hover:bg-indigo-100 active:translate-y-0.5 active:border-b-2 dark:border-indigo-500 dark:bg-gray-900 dark:text-indigo-100 dark:hover:bg-indigo-950"
                >
                    Semaine suiv. ▶
                </button>
            </div>
            <div class="flex flex-col gap-2 sm:items-end">
                <div
                    class="flex w-full flex-col gap-2 text-xs font-semibold text-gray-800 dark:text-gray-100 sm:max-w-md sm:items-end sm:justify-end"
                >
                    <span class="whitespace-nowrap sm:text-end">Aller au mois</span>
                    <div class="flex w-full flex-wrap items-center gap-2 sm:justify-end">
                        <x-filament::input.wrapper
                            class="min-w-[7.5rem] max-w-full flex-1 rounded-xl border-2 border-indigo-400 bg-white py-0 shadow-sm dark:border-indigo-600 dark:bg-gray-900 sm:flex-initial"
                        >
                            <x-filament::input.select
                                wire:model.live="month_jump_month"
                                class="!py-1.5 !pe-8 !ps-2 !text-xs !font-semibold text-indigo-950 dark:text-indigo-100"
                            >
                                @foreach ($this->frenchMonthOptions as $num => $label)
                                    <option value="{{ $num }}">{{ $label }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                        <x-filament::input.wrapper
                            class="w-[4.5rem] shrink-0 rounded-xl border-2 border-indigo-400 bg-white py-0 shadow-sm dark:border-indigo-600 dark:bg-gray-900"
                        >
                            <x-filament::input.select
                                wire:model.live="month_jump_year"
                                class="!py-1.5 !pe-6 !ps-2 !text-xs !font-bold tabular-nums text-indigo-950 dark:text-indigo-100"
                            >
                                @foreach ($this->monthYearOptions as $y)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                </div>
                <span class="inline-flex flex-wrap items-center gap-1.5 sm:justify-end">
                    <span class="whitespace-nowrap text-[11px] font-semibold text-gray-700 dark:text-gray-300"
                        >Début de semaine</span
                    >
                    <x-filament::input.wrapper
                        class="w-[5.25rem] shrink-0 rounded-xl border-2 border-indigo-400 bg-white py-0 shadow-sm dark:border-indigo-600 dark:bg-gray-900"
                    >
                        <x-filament::input.select
                            wire:model.live.debounce.500ms="startDayOfWeek"
                            class="!py-1 !pe-6 !ps-2 !text-xs !font-bold text-indigo-950 dark:text-indigo-100"
                        >
                            <option value="0">dim.</option>
                            <option value="1">lun.</option>
                            <option value="2">mar.</option>
                            <option value="3">mer.</option>
                            <option value="4">jeu.</option>
                            <option value="5">ven.</option>
                            <option value="6">sam.</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </span>
            </div>
        </div>
        <p class="mt-2 text-[11px] leading-tight text-gray-700 dark:text-gray-300">
            Indicatif caisse : total par jour (midi + soir). Les notes se saisissent sur chaque ligne de répartition
            (édition).
        </p>
    </div>

    <div class="grid min-h-0 gap-3 lg:grid-cols-2 lg:gap-4">
        {{-- Matrice hebdo --}}
        <div class="flex min-h-0 flex-col lg:min-w-0">
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-[11px] font-black uppercase tracking-wide text-indigo-900 dark:text-indigo-200">
                    Semaine : qui / quand / combien
                </h3>
                <span
                    class="rounded-lg border-2 border-b-4 border-indigo-300 bg-indigo-100 px-2 py-0.5 text-[11px] font-black tabular-nums text-indigo-950 dark:border-indigo-700 dark:bg-indigo-950 dark:text-indigo-100"
                >
                    Total sem. {{ number_format($w['week_total'], 3, ',', ' ') }} DT
                </span>
            </div>
            <div
                class="rounded-2xl border-2 border-b-4 border-indigo-500 bg-white shadow-md dark:border-indigo-600 dark:bg-gray-950"
            >
                {{-- Mobile : cartes empilées, pas de défilement horizontal --}}
                <div
                    class="md:hidden max-h-[min(60vh,32rem)] space-y-2 overflow-y-auto overflow-x-hidden p-2"
                >
                    <details
                        class="group rounded-xl border-2 border-emerald-500 bg-emerald-50/95 shadow-sm ring-1 ring-emerald-200/70 dark:border-emerald-600 dark:bg-emerald-950/55 dark:ring-emerald-900/40"
                    >
                        <summary
                            class="cursor-pointer list-none px-2.5 py-2 text-left text-[11px] font-black text-emerald-950 marker:content-none dark:text-emerald-100 [&::-webkit-details-marker]:hidden"
                        >
                            <span class="inline-flex w-full items-center justify-between gap-2">
                                <span class="inline-flex min-w-0 items-center gap-1">
                                    <span aria-hidden="true">📊</span>
                                    <span class="uppercase tracking-wide"
                                        >{{ __('hq.tip_dashboard_day_total', [], 'fr') }}</span
                                    >
                                </span>
                                <span
                                    class="shrink-0 text-[10px] font-semibold text-emerald-800 opacity-90 dark:text-emerald-200"
                                    >▾</span
                                >
                            </span>
                            <span
                                class="mt-0.5 block text-[9px] font-semibold leading-tight text-emerald-900/90 dark:text-emerald-200/95"
                            >
                                {{ __('hq.tip_dashboard_day_total_hint', [], 'fr') }}
                            </span>
                        </summary>
                        <div
                            class="space-y-2 border-t border-emerald-300/90 p-2 pt-2 dark:border-emerald-700"
                        >
                            @foreach(($w['day_keys'] ?? []) as $idx => $dk)
                                @php
                                    $dayLabel = $w['day_labels'][$idx] ?? $dk;
                                    $dt = $dayMealTotalsMap[$dk] ?? ['lunch' => 0.0, 'dinner' => 0.0, 'total' => 0.0];
                                    $dl = (float) ($dt['lunch'] ?? 0);
                                    $dd = (float) ($dt['dinner'] ?? 0);
                                    $dz = (float) ($dt['total'] ?? 0);
                                @endphp
                                <div
                                    class="flex flex-col gap-1 rounded-lg border border-emerald-300/80 bg-white/95 px-2 py-1.5 dark:border-emerald-700 dark:bg-emerald-950/40"
                                >
                                    <p
                                        class="text-[10px] font-black leading-tight text-gray-950 dark:text-white"
                                    >
                                        {{ $dayLabel }}
                                    </p>
                                    <div
                                        class="flex flex-wrap items-center justify-between gap-x-3 gap-y-0.5 font-mono text-[10px] tabular-nums"
                                    >
                                        <span class="inline-flex items-center gap-0.5">
                                            <span aria-hidden="true">☀️</span>
                                            <span
                                                class="{{ $dl > 0 ? 'font-bold text-amber-700 dark:text-amber-400' : 'font-medium text-gray-500 dark:text-gray-500' }}"
                                            >
                                                {{ $tipSmart($dl) }}
                                            </span>
                                        </span>
                                        <span class="inline-flex items-center gap-0.5">
                                            <span aria-hidden="true">🌙</span>
                                            <span
                                                class="{{ $dd > 0 ? 'font-bold text-indigo-700 dark:text-indigo-400' : 'font-medium text-gray-500 dark:text-gray-500' }}"
                                            >
                                                {{ $tipSmart($dd) }}
                                            </span>
                                        </span>
                                        <span class="inline-flex items-center gap-0.5">
                                            <span class="font-black text-gray-900 dark:text-gray-100">Σ</span>
                                            <span
                                                class="{{ $dz > 0 ? 'font-black text-emerald-800 dark:text-emerald-400' : 'font-medium text-gray-500 dark:text-gray-500' }}"
                                            >
                                                {{ $tipSmart($dz) }}
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                            @php
                                $wl = (float) ($weekMealTotalsRow['lunch'] ?? 0);
                                $wd = (float) ($weekMealTotalsRow['dinner'] ?? 0);
                                $wz = (float) ($weekMealTotalsRow['total'] ?? 0);
                            @endphp
                            <div
                                class="rounded-lg border-2 border-emerald-500 bg-emerald-100/90 px-2 py-1.5 dark:border-emerald-600 dark:bg-emerald-900/50"
                            >
                                <p
                                    class="mb-1 text-[10px] font-black uppercase tracking-wide text-emerald-950 dark:text-emerald-100"
                                >
                                    Σ semaine (caisse)
                                </p>
                                <div
                                    class="flex flex-wrap items-center justify-between gap-x-3 gap-y-0.5 font-mono text-[10px] tabular-nums"
                                >
                                    <span class="inline-flex items-center gap-0.5">
                                        <span aria-hidden="true">☀️</span>
                                        <span
                                            class="{{ $wl > 0 ? 'font-bold text-amber-800 dark:text-amber-300' : 'font-medium text-gray-600 dark:text-gray-400' }}"
                                        >
                                            {{ $tipSmart($wl) }}
                                        </span>
                                    </span>
                                    <span class="inline-flex items-center gap-0.5">
                                        <span aria-hidden="true">🌙</span>
                                        <span
                                            class="{{ $wd > 0 ? 'font-bold text-indigo-800 dark:text-indigo-300' : 'font-medium text-gray-600 dark:text-gray-400' }}"
                                        >
                                            {{ $tipSmart($wd) }}
                                        </span>
                                    </span>
                                    <span class="inline-flex items-center gap-0.5">
                                        <span class="font-black text-gray-950 dark:text-white">Σ</span>
                                        <span
                                            class="{{ $wz > 0 ? 'font-black text-emerald-900 dark:text-emerald-300' : 'font-medium text-gray-600 dark:text-gray-400' }}"
                                        >
                                            {{ $tipSmart($wz) }}
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </details>

                    @forelse($w['rows'] as $ri => $row)
                        <div
                            class="rounded-xl border-2 border-indigo-400 bg-white p-2 shadow-sm dark:border-indigo-600 dark:bg-gray-950"
                        >
                            <div class="mb-2 flex items-start justify-between gap-2 border-b border-indigo-200 pb-1.5 dark:border-indigo-800">
                                <a
                                    href="{{ $this->staffEditUrl($row['staff_id']) }}"
                                    class="min-w-0 flex-1 break-words text-xs font-bold text-primary-600 hover:underline dark:text-primary-400"
                                    wire:navigate
                                >
                                    {{ $row['name'] }}
                                </a>
                                @php $wt = (float) $row['week_total']; @endphp
                                <span
                                    class="shrink-0 rounded-md border border-indigo-300 bg-indigo-50 px-1.5 py-0.5 font-mono text-[11px] font-black tabular-nums text-indigo-950 dark:border-indigo-700 dark:bg-indigo-950 dark:text-indigo-100"
                                >
                                    Σ {{ $tipSmart($wt) }}
                                </span>
                            </div>
                            <div class="space-y-1.5">
                                @foreach($row['days'] as $di => $cell)
                                    @php
                                        $lRaw = (float) $cell['lunch_amount'];
                                        $dRaw = (float) $cell['dinner_amount'];
                                        $tRaw = (float) $cell['amount'];
                                        $hasNote = filled($cell['note_hint']);
                                        $dayLabel = $w['day_labels'][$di] ?? '';
                                    @endphp
                                    <div
                                        class="rounded-lg border px-2 py-1.5 {{ $tRaw > 0 ? 'border-amber-300/90 bg-amber-50/90 dark:border-amber-700/80 dark:bg-amber-950/40' : 'border-gray-200 bg-gray-50/80 dark:border-gray-700 dark:bg-gray-900/60' }}"
                                        @if($hasNote) title="{{ e($cell['note_hint']) }}" @endif
                                    >
                                        <p
                                            class="mb-1 text-[10px] font-bold leading-tight text-gray-950 dark:text-white"
                                        >
                                            {{ $dayLabel }}
                                        </p>
                                        <div
                                            class="flex flex-wrap items-center justify-between gap-x-3 gap-y-0.5 font-mono text-[10px] tabular-nums leading-none"
                                        >
                                            <span class="inline-flex items-center gap-0.5">
                                                <span aria-hidden="true">☀️</span>
                                                <span
                                                    class="{{ $lRaw > 0 ? 'font-bold text-amber-700 dark:text-amber-400' : 'font-medium text-gray-500 dark:text-gray-500' }}"
                                                >
                                                    {{ $tipSmart($lRaw) }}
                                                </span>
                                            </span>
                                            <span class="inline-flex items-center gap-0.5">
                                                <span aria-hidden="true">🌙</span>
                                                <span
                                                    class="{{ $dRaw > 0 ? 'font-bold text-indigo-700 dark:text-indigo-400' : 'font-medium text-gray-500 dark:text-gray-500' }}"
                                                >
                                                    {{ $tipSmart($dRaw) }}
                                                </span>
                                            </span>
                                            <span class="inline-flex items-center gap-0.5">
                                                <span class="font-black text-gray-900 dark:text-gray-100">Σ</span>
                                                <span
                                                    class="{{ $tRaw > 0 ? 'font-black text-emerald-800 dark:text-emerald-400' : 'font-medium text-gray-500 dark:text-gray-500' }}"
                                                >
                                                    {{ $tipSmart($tRaw) }}
                                                </span>
                                                @if($hasNote)
                                                    <span class="text-[9px]" aria-hidden="true">📝</span>
                                                @endif
                                            </span>
                                        </div>
                                        @if($hasNote)
                                            <p
                                                class="mt-1 line-clamp-2 text-[9px] font-medium leading-snug text-gray-700 dark:text-gray-300"
                                            >
                                                {{ $cell['note_hint'] }}
                                            </p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div
                            class="rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-3 py-6 text-center text-xs font-medium text-gray-700 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300"
                        >
                            Aucune répartition pour cette semaine. Utilisez « Calcul des pourboires » ou changez de
                            semaine.
                        </div>
                    @endforelse
                </div>

                {{-- Tableau desktop (md+) : défilement vertical + horizontal si nécessaire (évite la coupe des colonnes) --}}
                <div
                    class="hidden max-h-[min(52vh,30rem)] overflow-auto md:block"
                >
                {{-- sm:text-xs は合計行の text-[10px] を上書きするためテーブル本体に付けない --}}
                <table class="w-full min-w-0 border-collapse text-[10px] md:min-w-[22rem] lg:min-w-[24rem]">
                    <thead class="sticky top-0 z-10 shadow-sm">
                        <tr
                            class="border-b-2 border-indigo-600 bg-indigo-100 dark:border-indigo-500 dark:bg-indigo-950"
                        >
                            <th
                                class="sticky left-0 z-20 max-w-[4.5rem] border-r-2 border-indigo-300 bg-indigo-100 px-1 py-1 text-left text-[9px] font-black leading-tight text-indigo-950 dark:border-indigo-700 dark:bg-indigo-950 dark:text-white sm:max-w-none sm:px-1.5 sm:text-[10px]"
                            >
                                <span class="inline-flex items-center gap-0.5">
                                    <span aria-hidden="true" class="opacity-90">👥</span>
                                    <span>Équipe</span>
                                </span>
                            </th>
                            @foreach($w['day_labels'] as $label)
                                <th
                                    class="border-l border-indigo-200 px-0.5 py-1 text-center text-[9px] font-black leading-tight text-indigo-950 dark:border-indigo-800 dark:text-indigo-100 sm:px-0.5 sm:text-[10px]"
                                >
                                    {{ $label }}
                                </th>
                            @endforeach
                            <th
                                class="border-l-2 border-indigo-400 bg-indigo-200/90 px-0.5 py-1 text-center text-[9px] font-black text-indigo-950 dark:border-indigo-600 dark:bg-indigo-900 dark:text-white sm:px-1 sm:text-[10px]"
                            >
                                Σ sem.
                            </th>
                        </tr>
                        {{-- Totaux journaliers : teinte emerald + séparation nette avant les lignes staff (tbody) --}}
                        <tr
                            class="border-b-[6px] border-b-emerald-600 bg-emerald-50/95 shadow-[inset_0_1px_0_0_rgba(16,185,129,0.2)] dark:border-emerald-500 dark:bg-emerald-950/55 dark:shadow-[inset_0_1px_0_0_rgba(16,185,129,0.12)]"
                        >
                            <th
                                scope="row"
                                class="sticky left-0 z-20 max-w-[5.5rem] border-r-2 border-emerald-300 bg-emerald-100/95 px-1 py-1.5 text-left !text-[10px] font-black leading-tight text-emerald-950 dark:border-emerald-700 dark:bg-emerald-950 dark:text-emerald-50 sm:max-w-none sm:px-1.5"
                            >
                                <span class="flex flex-col gap-0.5">
                                    <span
                                        class="inline-flex w-fit items-center gap-0.5 rounded-md border border-emerald-600/80 bg-white/90 px-1 py-0.5 text-[9px] font-black uppercase tracking-wide text-emerald-950 shadow-sm dark:border-emerald-500 dark:bg-emerald-900/90 dark:text-emerald-100"
                                    >
                                        <span aria-hidden="true" class="text-[10px] leading-none">📊</span>
                                        {{ __('hq.tip_dashboard_day_total', [], 'fr') }}
                                    </span>
                                    <span class="text-[9px] font-semibold leading-tight text-emerald-900/90 dark:text-emerald-200/95">
                                        {{ __('hq.tip_dashboard_day_total_hint', [], 'fr') }}
                                    </span>
                                </span>
                            </th>
                            @foreach(($w['day_keys'] ?? []) as $dk)
                                @php
                                    $dt = $dayMealTotalsMap[$dk] ?? ['lunch' => 0.0, 'dinner' => 0.0, 'total' => 0.0];
                               
                                    $dl = (float) ($dt['lunch'] ?? 0);
                                    $dd = (float) ($dt['dinner'] ?? 0);
                                    $dz = (float) ($dt['total'] ?? 0);
                                @endphp
                                <th
                                    class="border-l border-emerald-200/90 px-0.5 py-1 text-center align-middle font-mono !text-[10px] leading-none tabular-nums text-emerald-950 dark:border-emerald-800 dark:text-emerald-100"
                                >
                                    <div
                                        class="mx-auto flex w-full min-w-[2.6rem] max-w-[3.5rem] flex-col gap-[1px] rounded border border-emerald-300/90 bg-white/95 px-0.5 py-0.5 !text-[10px] leading-none shadow-sm dark:border-emerald-700 dark:bg-emerald-950/40 [&_span]:!text-[10px]"
                                    >
                                        <div class="flex items-center justify-between gap-0.5 !text-[10px] leading-none">
                                            <span class="shrink-0 text-[10px] opacity-80" aria-hidden="true">☀️</span>
                                            <span
                                                class="min-w-0 text-end text-[10px] font-bold leading-none {{ $dl > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-gray-400 dark:text-gray-600' }}"
                                            >
                                                {{ $tipSmart($dl) }}
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between gap-0.5 !text-[10px] leading-none">
                                            <span class="shrink-0 text-[10px] opacity-80" aria-hidden="true">🌙</span>
                                            <span
                                                class="min-w-0 text-end text-[10px] font-bold leading-none {{ $dd > 0 ? 'text-indigo-700 dark:text-indigo-400' : 'text-gray-400 dark:text-gray-600' }}"
                                            >
                                                {{ $tipSmart($dd) }}
                                            </span>
                                        </div>
                                        <div
                                            class="mt-0.5 flex items-center justify-between gap-0.5 border-t border-emerald-300 pt-0.5 dark:border-emerald-700"
                                        >
                                            <span class="shrink-0 text-[10px] font-black leading-none text-gray-900 dark:text-gray-100"
                                                >Σ</span
                                            >
                                            <span
                                                class="min-w-0 text-end text-[10px] font-black leading-none {{ $dz > 0 ? 'text-emerald-800 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-600' }}"
                                            >
                                                {{ $tipSmart($dz) }}
                                            </span>
                                        </div>
                                    </div>
                                </th>
                            @endforeach
                            <th
                                class="border-l-2 border-emerald-500 bg-emerald-100/95 px-0.5 py-1 text-end align-middle !text-[10px] dark:border-emerald-600 dark:bg-emerald-950/80"
                            >
                                @php
                                    $wl = (float) ($weekMealTotalsRow['lunch'] ?? 0);
                                    $wd = (float) ($weekMealTotalsRow['dinner'] ?? 0);
                                    $wz = (float) ($weekMealTotalsRow['total'] ?? 0);
                                @endphp
                                <div
                                    class="ml-auto flex w-full min-w-[2.6rem] max-w-[3.5rem] flex-col gap-[1px] rounded border border-emerald-400/90 bg-white/95 px-0.5 py-0.5 font-mono !text-[10px] leading-none tabular-nums shadow-sm dark:border-emerald-600 dark:bg-emerald-950/40 sm:max-w-none [&_span]:!text-[10px]"
                                >
                                    <div class="flex items-center justify-between gap-0.5 !text-[10px] leading-none">
                                        <span class="shrink-0 text-[10px] opacity-80" aria-hidden="true">☀️</span>
                                        <span
                                            class="text-[10px] font-bold leading-none {{ $wl > 0 ? 'text-amber-700 dark:text-amber-400' : 'font-medium text-gray-400 dark:text-gray-600' }}"
                                        >
                                            {{ $tipSmart($wl) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between gap-0.5 !text-[10px] leading-none">
                                        <span class="shrink-0 text-[10px] opacity-80" aria-hidden="true">🌙</span>
                                        <span
                                            class="text-[10px] font-bold leading-none {{ $wd > 0 ? 'text-indigo-700 dark:text-indigo-400' : 'font-medium text-gray-400 dark:text-gray-600' }}"
                                        >
                                            {{ $tipSmart($wd) }}
                                        </span>
                                    </div>
                                    <div
                                        class="mt-0.5 flex items-center justify-between gap-0.5 border-t border-emerald-400 pt-0.5 dark:border-emerald-600"
                                    >
                                        <span class="shrink-0 text-[10px] font-black leading-none text-emerald-950 dark:text-emerald-100"
                                            >Σ</span
                                        >
                                        <span
                                            class="text-[10px] font-black leading-none {{ $wz > 0 ? 'text-emerald-800 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-600' }}"
                                        >
                                            {{ $tipSmart($wz) }}
                                        </span>
                                    </div>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($w['rows'] as $ri => $row)
                            <tr
                                class="{{ $ri % 2 === 0 ? 'bg-white dark:bg-gray-950' : 'bg-slate-50 dark:bg-gray-900/80' }} border-b border-gray-200 dark:border-gray-700 {{ $ri === 0 ? 'border-t-4 border-t-emerald-500/70 dark:border-t-emerald-500/60' : '' }}"
                            >
                                <td
                                    class="sticky left-0 z-10 max-w-[4.5rem] border-r-2 border-gray-300 bg-inherit px-1 py-0.5 text-[9px] font-semibold leading-tight text-gray-950 dark:border-gray-600 dark:text-white sm:max-w-none sm:px-1.5 sm:py-1 sm:text-[10px]"
                                >
                                    <a
                                        href="{{ $this->staffEditUrl($row['staff_id']) }}"
                                        class="text-primary-600 hover:underline dark:text-primary-400"
                                        wire:navigate
                                    >
                                        {{ $row['name'] }}
                                    </a>
                                </td>
                                @foreach($row['days'] as $cell)
                                    @php
                                        $lRaw = (float) $cell['lunch_amount'];
                                        $dRaw = (float) $cell['dinner_amount'];
                                        $tRaw = (float) $cell['amount'];
                                        $hasNote = filled($cell['note_hint']);
                                    @endphp
                                    <td
                                        class="border-l border-gray-200 px-0.5 py-0.5 text-center align-middle dark:border-gray-700"
                                    >
                                        <div
                                            class="mx-auto flex w-full min-w-[2.6rem] max-w-[3.5rem] flex-col gap-[1px] rounded border px-0.5 py-0.5 font-mono text-[9px] leading-none tabular-nums sm:min-w-[2.85rem] sm:max-w-none sm:text-[10px] {{ $tRaw > 0 ? 'border-amber-300/90 bg-amber-50/80 dark:border-amber-700/80 dark:bg-amber-950/40' : 'border-gray-200/90 bg-gray-50/60 dark:border-gray-700 dark:bg-gray-900/50' }}"
                                            @if($hasNote) title="{{ e($cell['note_hint']) }}" @endif
                                        >
                                            <div class="flex items-center justify-between gap-0.5">
                                                <span class="shrink-0 text-[7px] leading-none opacity-80" aria-hidden="true"
                                                    >☀️</span
                                                >
                                                <span
                                                    class="min-w-0 text-end {{ $lRaw > 0 ? 'font-bold text-amber-700 dark:text-amber-400' : 'font-medium text-gray-400 dark:text-gray-600' }}"
                                                >
                                                    {{ $tipSmart($lRaw) }}
                                                </span>
                                            </div>
                                            <div class="flex items-center justify-between gap-0.5">
                                                <span class="shrink-0 text-[7px] leading-none opacity-80" aria-hidden="true"
                                                    >🌙</span
                                                >
                                                <span
                                                    class="min-w-0 text-end {{ $dRaw > 0 ? 'font-bold text-indigo-700 dark:text-indigo-400' : 'font-medium text-gray-400 dark:text-gray-600' }}"
                                                >
                                                    {{ $tipSmart($dRaw) }}
                                                </span>
                                            </div>
                                            <div
                                                class="mt-0.5 flex items-center justify-between gap-0.5 border-t border-gray-300 pt-0.5 dark:border-gray-600"
                                            >
                                                <span
                                                    class="shrink-0 text-[7px] font-black leading-none text-gray-900 dark:text-gray-100"
                                                    >Σ</span
                                                >
                                                <span
                                                    class="inline-flex min-w-0 items-center justify-end gap-0.5 {{ $tRaw > 0 ? 'font-black text-emerald-800 dark:text-emerald-400' : 'font-medium text-gray-400 dark:text-gray-600' }}"
                                                >
                                                    <span class="text-end tabular-nums">{{ $tipSmart($tRaw) }}</span>
                                                    @if($hasNote)
                                                        <span class="shrink-0 text-[7px] opacity-90" aria-hidden="true"
                                                            >📝</span
                                                        >
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                @endforeach
                                <td
                                    class="border-l-2 border-indigo-300 bg-indigo-50/90 px-0.5 py-0.5 text-end align-middle font-mono text-[9px] font-bold tabular-nums text-indigo-950 dark:border-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-100 sm:px-1 sm:text-[10px]"
                                >
                                    @php $wt = (float) $row['week_total']; @endphp
                                    <span class="{{ $wt > 0 ? '' : 'text-gray-400 dark:text-gray-600' }}">
                                        {{ $tipSmart($wt) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="{{ count($w['day_labels']) + 2 }}"
                                    class="px-3 py-6 text-center text-xs text-gray-600 dark:text-gray-300"
                                >
                                    Aucune répartition pour cette semaine. Utilisez « Calcul des pourboires » ou changez de
                                    semaine.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        {{-- Synthèse mois + barres --}}
        <div class="flex min-h-0 flex-col gap-2">
            <div
                class="rounded-lg border-2 border-b-4 border-emerald-500 bg-gradient-to-br from-emerald-50 to-white p-3 shadow-sm ring-1 ring-emerald-200/80 dark:border-emerald-600 dark:from-emerald-950/50 dark:to-gray-950 dark:ring-emerald-900/40"
            >
                <div
                    class="text-[10px] font-black uppercase tracking-wide text-emerald-900 dark:text-emerald-200"
                >
                    {{ ucfirst($m['month_label']) }} · Cagnotte établissement
                </div>
                <div class="mt-1 text-lg font-black tabular-nums text-emerald-800 dark:text-emerald-400">
                    {{ number_format($m['pool'], 3, ',', ' ') }}
                    <span class="text-sm font-bold text-gray-500 dark:text-white">DT</span>
                </div>
            </div>

            <div
                class="flex min-h-0 flex-1 flex-col rounded-2xl border-2 border-b-4 border-violet-500 bg-white p-3 shadow-md dark:border-violet-600 dark:bg-gray-950"
            >
                <h3 class="mb-2 text-[11px] font-black uppercase tracking-wide text-violet-900 dark:text-violet-200">
                    Par personne (mois en cours)
                </h3>
                <div class="max-h-[min(40vh,20rem)] space-y-1.5 overflow-y-auto pr-0.5">
                    @forelse($m['staff_bars'] as $i => $bar)
                        @php $c = $barColors[$i % count($barColors)]; @endphp
                        <div
                            class="rounded-xl border-2 border-gray-200 bg-slate-50 p-1.5 dark:border-gray-700 dark:bg-gray-900/90"
                        >
                            <div class="mb-0.5 flex items-center justify-between gap-2 text-[10px] font-semibold">
                                <a
                                    href="{{ $this->staffEditUrl($bar['staff_id']) }}"
                                    class="truncate text-violet-800 hover:underline dark:text-violet-300"
                                    wire:navigate
                                    >{{ $bar['name'] }}</a
                                >
                                <span class="shrink-0 font-mono tabular-nums text-gray-950 dark:text-white">
                                    {{ number_format($bar['total'], 3, ',', ' ') }}
                                </span>
                            </div>
                            <div
                                class="h-2.5 w-full overflow-hidden rounded-full border border-gray-300 bg-gray-200 dark:border-gray-600 dark:bg-gray-800"
                            >
                                <div
                                    class="{{ $c }} h-full rounded-full transition-all duration-300"
                                    style="width: {{ $bar['pct'] }}%"
                                ></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-600 dark:text-gray-300">Aucune donnée de répartition ce mois-ci.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
