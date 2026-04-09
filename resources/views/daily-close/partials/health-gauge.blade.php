@php
    $pct = $this->healthGaugePercent();
    $tone = $this->healthGaugeTone();
    $barClass = match ($tone) {
        'green' => 'bg-emerald-500 dark:bg-emerald-500',
        'yellow' => 'bg-amber-500 dark:bg-amber-500',
        default => 'bg-rose-500 dark:bg-rose-500',
    };
    $toneLabel = match ($tone) {
        'green' => 'PERFECT',
        'yellow' => 'CAUTION',
        default => 'DANGER',
    };
    $tonePill = match ($tone) {
        'green' => 'border-emerald-400/80 bg-emerald-100 text-emerald-900 dark:border-emerald-600/50 dark:bg-emerald-950/50 dark:text-emerald-100',
        'yellow' => 'border-amber-400/80 bg-amber-100 text-amber-950 dark:border-amber-600/50 dark:bg-amber-950/50 dark:text-amber-100',
        default => 'border-rose-400/80 bg-rose-100 text-rose-950 dark:border-rose-600/50 dark:bg-rose-950/50 dark:text-rose-100',
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
        <span class="inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 font-['Press_Start_2P'] text-[7px] font-semibold sm:text-[8px] {{ $tonePill }}">{{ $toneLabel }}</span>
    </p>
    <div class="mt-2">
        <div class="h-3 w-full overflow-hidden rounded-full border border-gray-200 bg-gray-200 dark:border-gray-600 dark:bg-gray-700">
            <div
                class="h-full rounded-full {{ $barClass }} transition-all duration-300 ease-out"
                style="width: {{ $pct }}%"
                wire:key="gauge-{{ $pct }}-{{ $tone }}"
            ></div>
        </div>
    </div>
</div>
