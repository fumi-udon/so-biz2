@php
    /** @var string $monthLabel */
    /** @var int $maxLate */
    /** @var \Illuminate\Support\Collection<int, array{staff: \App\Models\Staff, late: int, absent_equiv: int, warn: bool}> $rows */
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        :compact="true"
        class="rounded-none border-2 border-green-500 bg-gray-900 shadow-[4px_4px_0_0_rgba(34,197,94,0.35)] dark:border-green-400 dark:shadow-[4px_4px_0_0_rgba(34,197,94,0.2)]"
    >
        <x-slot name="heading">
            <span class="font-mono text-sm font-black uppercase tracking-[0.2em] text-green-400">ATTENDANCE OPS</span>
            <span class="ml-2 font-mono text-[10px] text-orange-400">{{ $monthLabel }}</span>
        </x-slot>
        <x-slot name="description">
            <span class="font-mono text-[9px] tracking-wide text-green-300/90">遅刻 = late_minutes &gt; 0 / 未打刻のみ = 両打刻なし・遅刻0（参考）</span>
        </x-slot>

        <div class="max-w-full space-y-2 overflow-x-hidden">
            @foreach ($rows as $r)
                @php
                    $staff = $r['staff'];
                    $late = (int) $r['late'];
                    $abs = (int) $r['absent_equiv'];
                    $warn = (bool) $r['warn'];
                    $pct = $maxLate > 0 ? min(100, (int) round(100 * $late / $maxLate)) : 0;
                @endphp
                <div
                    @class([
                        'border border-green-500/40 bg-black/40 px-2 py-1.5 dark:border-green-500/30',
                        'animate-pulse ring-1 ring-red-500/60' => $warn,
                    ])
                >
                    <div class="flex min-w-0 items-center justify-between gap-2">
                        <span
                            @class([
                                'min-w-0 truncate font-mono text-[10px] font-bold tracking-wide text-green-400',
                                'text-red-500' => $warn,
                            ])
                            title="{{ $staff->name }}"
                        >
                            {{ $staff->name }}
                        </span>
                        <div class="flex shrink-0 gap-2 font-mono text-[9px]">
                            <span class="text-orange-400">LATE <span @class(['font-black', 'text-red-500' => $late >= 3])>{{ $late }}</span></span>
                            <span class="text-green-300/80">NOP <span class="font-black text-amber-300">{{ $abs }}</span></span>
                        </div>
                    </div>
                    <div class="mt-1 h-2 w-full border border-green-600/50 bg-gray-950 dark:border-green-500/40">
                        <div
                            class="h-full bg-gradient-to-r from-green-600 to-emerald-400 dark:from-green-500 dark:to-lime-400"
                            style="width: {{ $pct }}%"
                        ></div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
