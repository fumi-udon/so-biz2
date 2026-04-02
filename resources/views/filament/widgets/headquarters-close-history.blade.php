@php
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Finance> $financeRows */
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Finance> $auditRows */
    /** @var string $range2Label */
    /** @var string $range3Label */
@endphp

<x-filament-widgets::widget>
    <div class="space-y-4">
        <x-filament::section :compact="true">
            <x-slot name="heading">
                <span class="text-sm font-bold text-gray-950 dark:text-white">レジ締め（直近2日）</span>
            </x-slot>
            <x-slot name="description">
                <span class="text-[10px] text-gray-600 dark:text-gray-300">{{ $range2Label }}</span>
            </x-slot>

            @if ($financeRows->isEmpty())
                <p class="text-xs text-gray-500 dark:text-gray-400">データがありません。</p>
            @else
                <div class="-mx-1 overflow-x-auto md:mx-0">
                    <table class="w-full min-w-[20rem] border-collapse text-left text-[10px] text-gray-950 dark:text-gray-100">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-white/10">
                                <th class="whitespace-nowrap py-1 pr-2 font-semibold">日付</th>
                                <th class="whitespace-nowrap py-1 pr-2 font-semibold">Shift</th>
                                <th class="whitespace-nowrap py-1 pr-2 font-semibold">売上</th>
                                <th class="whitespace-nowrap py-1 pr-2 font-semibold">差額</th>
                                <th class="whitespace-nowrap py-1 font-semibold">判定</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($financeRows as $f)
                                <tr>
                                    <td class="py-1 pr-2 font-mono tabular-nums">{{ $f->business_date?->format('m/d') ?? '—' }}</td>
                                    <td class="py-1 pr-2">{{ $f->shift ?? '—' }}</td>
                                    <td class="py-1 pr-2 font-mono tabular-nums">{{ number_format((float) ($f->recettes ?? 0), 0, '.', ' ') }}</td>
                                    <td class="py-1 pr-2 font-mono tabular-nums">{{ number_format((float) ($f->final_difference ?? 0), 0, '.', ' ') }}</td>
                                    <td class="py-1 font-medium">{{ $f->verdict ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section :compact="true">
            <x-slot name="heading">
                <span class="text-sm font-bold text-gray-950 dark:text-white">クローズチェック担当・完了（直近3日）</span>
            </x-slot>
            <x-slot name="description">
                <span class="text-[10px] text-gray-600 dark:text-gray-300">{{ $range3Label }}</span>
            </x-slot>

            @if ($auditRows->isEmpty())
                <p class="text-xs text-gray-500 dark:text-gray-400">データがありません。</p>
            @else
                <div class="-mx-1 overflow-x-auto md:mx-0">
                    <table class="w-full min-w-[20rem] border-collapse text-left text-[10px] text-gray-950 dark:text-gray-100">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-white/10">
                                <th class="whitespace-nowrap py-1 pr-2 font-semibold">日付</th>
                                <th class="whitespace-nowrap py-1 pr-2 font-semibold">Shift</th>
                                <th class="whitespace-nowrap py-1 pr-2 font-semibold">担当</th>
                                <th class="whitespace-nowrap py-1 pr-2 font-semibold">パネル</th>
                                <th class="whitespace-nowrap py-1 font-semibold">更新</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($auditRows as $f)
                                <tr>
                                    <td class="py-1 pr-2 font-mono tabular-nums">{{ $f->business_date?->format('m/d') ?? '—' }}</td>
                                    <td class="py-1 pr-2">{{ $f->shift ?? '—' }}</td>
                                    <td class="max-w-[6rem] truncate py-1 pr-2" title="{{ $f->responsibleStaff?->name ?? '' }}">{{ $f->responsibleStaff?->name ?? '—' }}</td>
                                    <td class="max-w-[6rem] truncate py-1 pr-2" title="{{ $f->panelOperator?->name ?? '' }}">{{ $f->panelOperator?->name ?? '—' }}</td>
                                    <td class="py-1 font-mono tabular-nums text-[9px]">{{ $f->updated_at?->format('m/d H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
