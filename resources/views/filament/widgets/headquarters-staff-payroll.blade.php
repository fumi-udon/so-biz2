@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Staff> $staff */
    /** @var float $totalWage */
    /** @var string $totalHourlyLabel */
@endphp

<x-filament-widgets::widget>
    <x-filament::section :compact="true">
        <x-slot name="heading">
            <span class="text-base font-bold text-gray-950 dark:text-white">Équipe & salaires</span>
        </x-slot>
        <x-slot name="description">
            <span class="text-[11px] font-medium text-gray-900 dark:text-gray-100 sm:text-[12px]">{{ $totalHourlyLabel }}</span>
        </x-slot>

        <div class="-mx-1 overflow-x-auto md:mx-0">
            <table class="w-full min-w-[18rem] border-collapse text-left text-[11px] text-gray-950 sm:text-[12px] dark:text-gray-100">
                <thead>
                    <tr class="border-b border-gray-300 dark:border-white/15">
                        <th class="whitespace-nowrap py-1 pr-2 font-bold text-gray-950 dark:text-white">Nom</th>
                        <th class="whitespace-nowrap py-1 pr-2 font-bold text-gray-950 dark:text-white">Niveau</th>
                        <th class="whitespace-nowrap py-1 pr-2 font-bold text-gray-950 dark:text-white">Salaire fixe</th>
                        <th class="whitespace-nowrap py-1 font-bold text-gray-950 dark:text-white">Taux horaire</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($staff as $s)
                        <tr>
                            <td class="max-w-[8rem] truncate py-1 pr-2 font-semibold text-gray-950 dark:text-gray-50" title="{{ $s->name }}">{{ $s->name }}</td>
                            <td class="py-1 pr-2 font-medium text-gray-900 dark:text-gray-100">{{ $s->jobLevel?->name ?? '—' }}</td>
                            <td class="py-1 pr-2 font-mono tabular-nums font-medium text-gray-950 dark:text-gray-50">{{ $s->wage !== null ? number_format((float) $s->wage, 0, '.', ' ').' DT' : '—' }}</td>
                            <td class="py-1 font-mono tabular-nums font-medium text-gray-950 dark:text-gray-50">{{ $s->hourly_wage !== null ? number_format((int) $s->hourly_wage).' DT/h' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-400 bg-gray-100 dark:border-white/15 dark:bg-white/10">
                        <td colspan="2" class="py-1.5 pr-2 text-[11px] font-bold text-gray-900 dark:text-gray-100 sm:text-[12px]">Total salaires fixes</td>
                        <td colspan="2" class="py-1.5 font-mono text-sm font-black tabular-nums text-gray-950 dark:text-white">
                            {{ number_format($totalWage, 0, '.', ' ') }} DT
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
