@php
    $pct = $this->healthGaugePercent();
    $tone = $this->healthGaugeTone();
    $barClass = match ($tone) {
        'green' => 'bg-emerald-500 dark:bg-emerald-500',
        'yellow' => 'bg-amber-500 dark:bg-amber-500',
        default => 'bg-rose-500 dark:bg-rose-500',
    };
    $numPill = 'inline-flex rounded-md bg-sky-600 px-2 py-0.5 font-mono text-xs font-semibold tabular-nums text-white dark:bg-sky-700';
@endphp

<div class="mb-3" aria-label="Référence et mesure">
    <p class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs leading-relaxed text-gray-600 dark:text-gray-400">
        <span class="font-medium text-gray-500 dark:text-gray-500">Référence et mesure</span>
        <span class="text-gray-300 dark:text-gray-600" aria-hidden="true">·</span>
        <span>Réf.</span>
        <span class="{{ $numPill }}">{{ number_format($this->runningTotalRefAmount(), 3, '.', ',') }}</span>
        <span class="text-gray-300 dark:text-gray-600" aria-hidden="true">·</span>
        <span>Mes.</span>
        <span class="{{ $numPill }}">{{ number_format($this->runningTotalMeasAmount(), 3, '.', ',') }}</span>
        <span class="text-gray-300 dark:text-gray-600" aria-hidden="true">·</span>
        <!-- <span>Écart</span>
        <span class="{{ $numPill }}">{{ number_format(abs($this->runningTotalRefAmount() - $this->runningTotalMeasAmount()), 3, '.', ',') }}</span>
        <span class="text-gray-400 dark:text-gray-500">DT</span> -->
    </p>
    <div class="mt-2">
        <!-- <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
            <div
                class="h-full rounded-full {{ $barClass }} transition-all duration-300 ease-out"
                style="width: {{ $pct }}%"
                wire:key="gauge-{{ $pct }}-{{ $tone }}"
            ></div>
        </div> -->
    </div>
</div>
