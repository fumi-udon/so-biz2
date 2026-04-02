@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Staff> $staff */
    /** @var float $totalWage */
    /** @var string $totalHourlyLabel */
@endphp

<x-filament-widgets::widget>
    <x-filament::section :compact="true">
        <x-slot name="heading">
            <span class="text-sm font-bold text-gray-950 dark:text-white">メンバー・給与</span>
        </x-slot>
        <x-slot name="description">
            <span class="text-[10px] text-gray-600 dark:text-gray-300">{{ $totalHourlyLabel }}</span>
        </x-slot>

        <div class="-mx-1 overflow-x-auto md:mx-0">
            <table class="w-full min-w-[18rem] border-collapse text-left text-[10px] text-gray-950 dark:text-gray-100">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th class="whitespace-nowrap py-1 pr-2 font-semibold">名前</th>
                        <th class="whitespace-nowrap py-1 pr-2 font-semibold">Job level</th>
                        <th class="whitespace-nowrap py-1 pr-2 font-semibold">給与</th>
                        <th class="whitespace-nowrap py-1 font-semibold">時給</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($staff as $s)
                        <tr>
                            <td class="max-w-[8rem] truncate py-1 pr-2 font-medium" title="{{ $s->name }}">{{ $s->name }}</td>
                            <td class="py-1 pr-2">{{ $s->jobLevel?->name ?? '—' }}</td>
                            <td class="py-1 pr-2 font-mono tabular-nums">{{ $s->wage !== null ? number_format((float) $s->wage, 0, '.', ' ').' DT' : '—' }}</td>
                            <td class="py-1 font-mono tabular-nums">{{ $s->hourly_wage !== null ? number_format((int) $s->hourly_wage).' DT/h' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-300 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                        <td colspan="2" class="py-1.5 pr-2 text-[10px] font-semibold text-gray-700 dark:text-gray-300">固定給合計（wage）</td>
                        <td colspan="2" class="py-1.5 font-mono text-xs font-black tabular-nums text-gray-950 dark:text-white">
                            {{ number_format($totalWage, 0, '.', ' ') }} DT
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
