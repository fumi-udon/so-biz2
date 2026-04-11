@php
    /** @var string $businessDate */
    /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $rows */
@endphp

<x-filament-widgets::widget>
    <div
        class="relative overflow-hidden rounded-2xl border-2 border-sky-300/70 bg-gradient-to-b from-sky-400 via-sky-200 to-sky-100 p-3 shadow-xl dark:border-sky-800/60 dark:from-slate-900 dark:via-sky-950 dark:to-slate-950 sm:p-4"
    >
        <div
            class="pointer-events-none absolute -right-4 top-8 h-12 w-28 rounded-full bg-white/50 blur-sm dark:bg-white/10"
            aria-hidden="true"
        ></div>
        <div
            class="pointer-events-none absolute left-6 top-3 h-8 w-16 rounded-full bg-white/40 blur-sm dark:bg-white/10"
            aria-hidden="true"
        ></div>

        <x-filament::section
            :compact="true"
            class="relative rounded-2xl border-2 border-b-4 border-white/95 bg-white/95 shadow-xl dark:border-slate-600/90 dark:bg-slate-900/95"
        >
            <x-slot name="heading">
                <div
                    class="inline-flex max-w-full flex-col gap-2 text-slate-900 dark:text-slate-100 sm:flex-row sm:items-center"
                >
                    <span class="inline-flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1 rounded-xl border-2 border-b-4 border-red-400 bg-red-100 px-2 py-1 text-[11px] font-black uppercase tracking-wide text-red-950 shadow-md dark:border-red-600 dark:bg-red-950/80 dark:text-red-50 sm:text-xs"
                        >
                            <span class="select-none text-[10px]" aria-hidden="true">🍄</span>
                            {{ __('hq.roster_heading', [], 'fr') }}
                        </span>
                        <span
                            class="rounded-xl border-2 border-b-4 border-sky-400 bg-sky-100 px-2 py-1 font-mono text-[11px] font-black tabular-nums uppercase tracking-wide text-slate-900 shadow-md dark:border-sky-600 dark:bg-sky-900/90 dark:text-slate-100"
                        >
                            {{ $businessDate }}
                        </span>
                    </span>
                </div>
            </x-slot>

            @if ($rows->isEmpty())
                <p
                    class="rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-3 py-2 text-[11px] font-semibold text-slate-700 dark:border-slate-600 dark:bg-slate-800/50 dark:text-slate-200 sm:text-xs"
                >
                    {{ __('hq.roster_empty', [], 'fr') }}
                </p>
            @else
                <div
                    class="-mx-1 overflow-x-auto rounded-2xl border-2 border-b-4 border-slate-200 bg-white shadow-xl dark:border-slate-600 dark:bg-slate-950"
                >
                    <table
                        class="w-full min-w-[18rem] border-collapse text-left text-[11px] font-semibold leading-snug text-gray-950 sm:text-xs dark:text-gray-100"
                    >
                        <thead>
                            <tr
                                class="border-b-2 border-sky-400 bg-gradient-to-r from-sky-200 to-cyan-100 text-sky-950 dark:border-sky-700 dark:from-slate-800 dark:to-slate-900 dark:text-sky-100"
                            >
                                <th
                                    class="whitespace-nowrap px-2 py-2 font-black uppercase tracking-wide sm:px-3"
                                >
                                    {{ __('hq.roster_col_staff', [], 'fr') }}
                                </th>
                                <th
                                    class="whitespace-nowrap border-l-4 border-amber-500 bg-amber-100/80 px-2 py-2 font-black uppercase tracking-wide text-amber-950 dark:border-amber-400 dark:bg-amber-950/40 dark:text-amber-100 sm:px-3"
                                >
                                    ☀ {{ __('hq.roster_col_lunch', [], 'fr') }}
                                </th>
                                <th
                                    class="whitespace-nowrap border-l-4 border-emerald-600 bg-emerald-100/70 px-2 py-2 font-black uppercase tracking-wide text-emerald-950 dark:border-emerald-500 dark:bg-emerald-950/35 dark:text-emerald-100 sm:px-3"
                                >
                                    🌙 {{ __('hq.roster_col_dinner', [], 'fr') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                @php
                                    /** @var \App\Models\Staff $staff */
                                    $staff = $row['staff'];
                                    $roleCategory = (string) ($row['role_category'] ?? 'other');
                                    $roleColor = (string) ($row['role_color'] ?? 'gray');

                                    $roleShort = match ($roleCategory) {
                                        'kitchen' => 'Cui',
                                        'hall' => 'Sal',
                                        default => 'Aut',
                                    };

                                    $badgeClass = match ($roleColor) {
                                        'red' => 'border-2 border-b-4 border-red-700 bg-red-100 text-red-950 ring-1 ring-red-200 dark:border-red-800 dark:bg-red-950/50 dark:text-red-100 dark:ring-red-900/40',
                                        'green' => 'border-2 border-b-4 border-emerald-700 bg-emerald-100 text-emerald-950 ring-1 ring-emerald-200 dark:border-emerald-800 dark:bg-emerald-950/45 dark:text-emerald-100 dark:ring-emerald-900/40',
                                        default => 'border-2 border-b-4 border-slate-300 bg-slate-100 text-slate-900 ring-1 ring-slate-200 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:ring-slate-700',
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

                                        $left =
                                            '<span class="font-mono tabular-nums text-[11px] font-bold text-gray-700 dark:text-gray-300">'.$plannedDisp.'</span>';
                                        $arrow =
                                            '<span class="mx-0.5 text-[11px] text-amber-600 dark:text-amber-400">▶</span>';

                                        return match ($status) {
                                            'clocked' => $left.$arrow.'<span class="text-[11px] select-none">🟢</span>'.
                                                '<span class="font-mono tabular-nums text-[11px] font-black text-gray-950 dark:text-white">'.$actualDisp.'</span>',
                                            'extra' => $left.$arrow.'<span class="text-[11px] select-none">🆘</span>'.
                                                '<span class="font-mono tabular-nums text-[11px] font-black text-gray-950 dark:text-white">'.$actualDisp.'</span>',
                                            'late' => $left.$arrow.'<span class="text-[11px] select-none">🔴</span>'.
                                                '<span class="text-[11px] font-black uppercase tracking-wide text-red-700 dark:text-red-400">'.e(__('hq.roster_meal_absent', [], 'fr')).'</span>',
                                            'future' => $left.$arrow.'<span class="text-[11px] select-none text-sky-500 dark:text-sky-400">⚪</span>',
                                            default => $left.$arrow.'<span class="text-[11px] select-none text-gray-400">—</span>',
                                        };
                                    };
                                @endphp

                                <tr
                                    class="odd:bg-white even:bg-sky-50/50 hover:bg-amber-50/30 dark:odd:bg-gray-950 dark:even:bg-gray-900/40 dark:hover:bg-sky-950/30"
                                >
                                    <td class="px-2 py-1 align-middle sm:px-3 sm:py-1.5">
                                        <div class="flex min-w-0 flex-wrap items-center gap-x-1 gap-y-0.5">
                                            <span
                                                class="inline-flex shrink-0 items-center rounded-lg px-1.5 py-0.5 text-[10px] font-black uppercase tracking-wide shadow-sm {{ $badgeClass }}"
                                            >
                                                {{ $roleShort }}
                                            </span>
                                            <span
                                                class="min-w-0 truncate font-black text-gray-950 dark:text-white"
                                            >{{ $staff->name }}</span>
                                        </div>
                                    </td>
                                    <td
                                        class="border-l-4 border-amber-400/80 bg-amber-50/40 px-2 py-1 align-middle dark:border-amber-600/50 dark:bg-amber-950/20 sm:px-3 sm:py-1.5"
                                    >
                                        <div class="flex flex-wrap items-center gap-x-0.5 gap-y-0.5">
                                            {!! $renderMeal($lStatus, $lPlanned, $lInTime) !!}
                                        </div>
                                    </td>
                                    <td
                                        class="border-l-4 border-emerald-500/70 bg-emerald-50/30 px-2 py-1 align-middle dark:border-emerald-600/45 dark:bg-emerald-950/20 sm:px-3 sm:py-1.5"
                                    >
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
    </div>
</x-filament-widgets::widget>
