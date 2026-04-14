@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Staff> $staff */
    /** @var float $totalWage */
    /** @var string $totalHourlyLabel */
    /** @var int $payrollYear */
    /** @var int $payrollMonth */
    /** @var array<int, float|null> $equivPaye */
@endphp

<x-filament-widgets::widget>
    <x-filament::section :compact="true">
        <x-slot name="heading">
            <span class="text-sm font-bold text-gray-950 dark:text-white" title="Équipe & salaires">Éq.·paye</span>
        </x-slot>
        <x-slot name="description">
            <span class="text-[10px] font-medium text-gray-900 dark:text-gray-100 sm:text-[11px]">
                {{ $totalHourlyLabel }} · {{ $payrollYear }}/{{ str_pad((string) $payrollMonth, 2, '0', STR_PAD_LEFT) }}
            </span>
        </x-slot>

        <div class="w-full overflow-hidden">
            <table class="w-full table-fixed border-collapse text-left text-[10px] text-gray-950 sm:text-[11px] dark:text-gray-100">
                <thead>
                    <tr class="border-b border-gray-300 dark:border-white/15">
                        <th class="w-[22%] py-1 pr-1 font-bold text-gray-950 dark:text-white" title="Nom">Nm</th>
                        <th class="w-[16%] py-1 pr-1 font-bold text-gray-950 dark:text-white" title="Niveau">Nv</th>
                        <th class="w-[18%] py-1 pr-1 font-bold text-gray-950 dark:text-white" title="Salaire fixe">fix</th>
                        <th class="w-[22%] py-1 pr-1 font-bold text-gray-950 dark:text-white" title="Taux horaire">h</th>
                        <th class="w-[22%] py-1 font-bold text-gray-950 dark:text-white" title="Équivalent paye du mois (h×taux)">Éq. paye</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($staff as $s)
                        @php
                            $eqPaye = $equivPaye[$s->id] ?? null;
                        @endphp
                        <tr>
                            <td class="max-w-0 truncate py-1 pr-1 font-semibold text-gray-950 dark:text-gray-50" title="{{ $s->name }}">{{ $s->name }}</td>
                            <td class="truncate py-1 pr-1 font-medium text-gray-900 dark:text-gray-100">{{ $s->jobLevel?->name ?? '—' }}</td>
                            <td class="truncate py-1 pr-1 font-mono tabular-nums font-medium text-gray-950 dark:text-gray-50">
                                {{ $s->wage !== null ? number_format((float) $s->wage, 0, '.', ' ').' DT' : '—' }}
                            </td>
                            <td class="truncate py-1 pr-1 font-mono tabular-nums font-medium text-gray-950 dark:text-gray-50">
                                {{ $s->hourly_wage !== null ? number_format((float) $s->hourly_wage, 3, '.', ' ').' DT/h' : '—' }}
                            </td>
                            <td class="truncate py-1 font-mono tabular-nums font-medium text-gray-950 dark:text-gray-50">
                                {{ $eqPaye !== null ? number_format($eqPaye, 3, '.', ' ').' DT' : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-400 bg-gray-100 dark:border-white/15 dark:bg-white/10">
                        <td colspan="2" class="py-1.5 pr-1 text-[10px] font-bold text-gray-900 dark:text-gray-100 sm:text-[11px]" title="Total salaires fixes">Σ fix</td>
                        <td colspan="3" class="py-1.5 font-mono text-[11px] font-black tabular-nums text-gray-950 dark:text-white sm:text-xs">
                            {{ number_format($totalWage, 0, '.', ' ') }} DT
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
