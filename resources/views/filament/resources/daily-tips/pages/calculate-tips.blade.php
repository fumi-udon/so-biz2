@php
    $d = $this->data ?? [];
    $shift = $d['shift'] ?? 'lunch';
    $target = $this->normalizedTotalAmount();
    $match = abs($distributed_total - $target) < 0.0005;
    $delta = $target - $distributed_total;
    $deltaAbs = abs($delta);
    /** @var list<int> */
    $tipWeightOptions = range(0, 100, 10);
@endphp

<x-filament-panels::page
    class="-mt-2 max-w-full overflow-x-hidden [&_section.flex.flex-col]:gap-y-2 [&_section.flex.flex-col]:py-3"
>
    <div class="space-y-2 pb-28 sm:pb-24">
        {{ $this->form }}

        @if ($this->needsManagerPin())
            <x-filament::section
                :compact="true"
                :heading="null"
                class="rounded-2xl border-2 border-b-4 border-danger-400 bg-danger-50/90 shadow-sm ring-1 ring-danger-200/80 dark:border-danger-600 dark:bg-danger-950/40 dark:ring-danger-900/50"
            >
                <p class="text-xs font-semibold text-danger-900 dark:text-danger-100">
                    Attention : le total dépasse 200&nbsp;DT. La validation par un responsable (code PIN) est
                    obligatoire pour enregistrer.
                </p>
            </x-filament::section>
        @endif

        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <x-filament::section
                :compact="true"
                :heading="null"
                class="rounded-2xl border-2 border-b-4 border-amber-400 bg-amber-50/90 shadow-sm ring-1 ring-amber-200/80 dark:border-amber-600 dark:bg-amber-950/40 dark:ring-amber-900/50"
            >
                <div class="flex flex-col gap-0.5">
                    <span class="text-[11px] font-bold text-amber-950 dark:text-amber-100">🎯 Objectif</span>
                    <span class="font-mono text-lg font-black tabular-nums text-gray-950 dark:text-white">
                        {{ number_format($target, 3, ',', ' ') }}
                        <span class="text-xs font-semibold text-amber-900 dark:text-amber-200">DT</span>
                    </span>
                </div>
            </x-filament::section>
            <x-filament::section
                :compact="true"
                :heading="null"
                class="rounded-2xl border-2 border-b-4 border-emerald-500 bg-emerald-50/90 shadow-sm ring-1 ring-emerald-200/80 dark:border-emerald-600 dark:bg-emerald-950/40 dark:ring-emerald-900/50"
            >
                <div class="flex flex-col gap-0.5">
                    <span class="text-[11px] font-bold text-emerald-950 dark:text-emerald-100">✅ Réparti</span>
                    <span
                        @class([
                            'font-mono text-lg font-black tabular-nums',
                            'text-emerald-800 dark:text-emerald-300' => $match,
                            'text-amber-900 dark:text-amber-200' => ! $match,
                        ])
                    >
                        {{ number_format($distributed_total, 3, ',', ' ') }}
                        <span
                            @class([
                                'text-xs font-semibold',
                                'text-emerald-900 dark:text-emerald-200' => $match,
                                'text-amber-900 dark:text-amber-200' => ! $match,
                            ])
                        >DT</span>
                    </span>
                </div>
            </x-filament::section>
            <x-filament::section
                :compact="true"
                :heading="null"
                class="rounded-2xl border-2 border-b-4 border-violet-400 bg-violet-50/90 shadow-sm ring-1 ring-violet-200/80 dark:border-violet-600 dark:bg-violet-950/40 dark:ring-violet-900/50"
            >
                <div class="flex flex-col gap-0.5">
                    @if ($match)
                        <span class="text-[11px] font-bold text-violet-950 dark:text-violet-100">🎉 Parfait !</span>
                        <span class="text-sm font-black text-emerald-800 dark:text-emerald-300">Équilibré</span>
                    @else
                        <span class="text-[11px] font-bold text-violet-950 dark:text-violet-100">📐 Écart</span>
                        <span class="font-mono text-lg font-black tabular-nums text-gray-950 dark:text-white">
                            {{ $delta >= 0 ? '+' : '−' }}{{ number_format($deltaAbs, 3, ',', ' ') }}
                            <span class="text-xs font-semibold text-violet-900 dark:text-violet-200">DT</span>
                        </span>
                    @endif
                </div>
            </x-filament::section>
        </div>

        {{-- Cartes denses (pas de tableau Filament : évite débordement / padding imposés sur mobile) --}}
        <div class="min-w-0 max-w-full space-y-1.5">
            <div
                class="flex flex-wrap items-center justify-between gap-1 border-b border-emerald-700/20 pb-1 text-[10px] font-black uppercase tracking-wide text-emerald-900 dark:border-emerald-500/30 dark:text-emerald-200"
            >
                <span>👤 Répartition</span>
                <span class="tabular-nums text-emerald-700 dark:text-emerald-400">{{ count($rows) }} pers.</span>
            </div>

            @if(count($rows) === 0)
                <div
                    class="rounded-xl border-2 border-dashed border-emerald-400/80 bg-emerald-50/50 px-2 py-5 text-center text-[11px] font-medium leading-snug text-gray-800 dark:border-emerald-700/60 dark:bg-emerald-950/30 dark:text-gray-200"
                >
                    Aucun membre pour ce service. Enregistrez d’abord les présences dans l’écran Pointages.
                </div>
            @else
                <div class="grid min-w-0 grid-cols-1 gap-1.5 sm:grid-cols-2">
                    @foreach($rows as $index => $row)
                        <div
                            class="min-w-0 overflow-hidden rounded-xl border-2 border-emerald-800/80 bg-white shadow-[0_4px_0_0_rgba(6,95,70,0.35)] dark:border-emerald-600 dark:bg-gray-950 dark:shadow-[0_4px_0_0_rgba(16,185,129,0.25)]"
                        >
                            <div class="flex items-start gap-1.5 border-b border-emerald-200/90 bg-emerald-50/80 px-1.5 py-1 dark:border-emerald-800/80 dark:bg-emerald-950/40">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-black leading-tight text-gray-950 dark:text-white">
                                        {{ $row['name'] }}
                                    </p>
                                    <p class="truncate text-[9px] font-medium leading-tight text-gray-600 dark:text-gray-400">
                                        {{ $row['job_level'] }}
                                    </p>
                                    <div class="mt-0.5 flex flex-wrap gap-0.5">
                                        <x-filament::badge color="gray" size="xs" class="!px-1 !py-0 !text-[9px]">
                                            {{ $shift === 'lunch' ? 'Midi' : 'Soir' }}
                                        </x-filament::badge>
                                        @if(! empty($row['is_tardy_deprived']))
                                            <x-filament::badge
                                                color="danger"
                                                size="xs"
                                                class="!px-1 !py-0 !text-[9px] ring-1 ring-rose-600/50 !bg-rose-50 !text-rose-800 dark:!bg-rose-950/50 dark:!text-rose-200"
                                            >
                                                Retard
                                            </x-filament::badge>
                                        @endif
                                    </div>
                                </div>
                                <x-filament::icon-button
                                    icon="heroicon-o-x-mark"
                                    wire:click="removeStaff({{ (int) $row['staff_id'] }})"
                                    color="danger"
                                    size="xs"
                                    label="Retirer"
                                    class="shrink-0"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-x-2 gap-y-1 p-1.5">
                                <div class="min-w-0">
                                    <span
                                        class="mb-0.5 block text-[9px] font-bold uppercase tracking-wide text-sky-900 dark:text-sky-200"
                                    >
                                        ⚖️ Parts %
                                    </span>
                                    <x-filament::input.wrapper
                                        class="w-full min-w-0 rounded-lg border-2 border-sky-400 bg-white py-0 shadow-inner dark:border-sky-600 dark:bg-gray-900"
                                    >
                                        <x-filament::input.select
                                            wire:model.live.debounce.500ms="rows.{{ $index }}.weight"
                                            class="!w-full !min-w-0 !py-1 !pe-8 !ps-1.5 !text-center !text-[11px] !font-bold !tabular-nums text-sky-950 dark:text-sky-100"
                                        >
                                            @foreach($tipWeightOptions as $w)
                                                <option value="{{ $w }}">{{ $w }}</option>
                                            @endforeach
                                        </x-filament::input.select>
                                    </x-filament::input.wrapper>
                                </div>
                                <div class="min-w-0 text-end">
                                    <span
                                        class="mb-0.5 block text-[9px] font-bold uppercase tracking-wide text-emerald-900 dark:text-emerald-200"
                                    >
                                        💰 Montant
                                    </span>
                                    <div
                                        class="font-mono text-sm font-black tabular-nums leading-tight text-emerald-800 dark:text-emerald-300 sm:text-base"
                                    >
                                        {{ number_format((float) $row['amount'], 3, ',', ' ') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <x-filament::section
            :compact="true"
            :heading="null"
            class="border-2 border-emerald-600/25 bg-emerald-50/40 shadow-sm ring-1 ring-emerald-600/20 dark:border-emerald-700/50 dark:bg-emerald-950/15 dark:ring-emerald-900/40"
        >
            <div
                class="flex flex-wrap items-baseline gap-x-3 gap-y-0 text-xs text-emerald-950 dark:text-emerald-200"
            >
                <span class="font-semibold">Semaine</span>
                <span class="font-mono font-medium tabular-nums text-emerald-800 dark:text-emerald-300">
                    {{ number_format($weekly_total, 3, ',', ' ') }} DT
                </span>
                <span class="text-emerald-300 dark:text-emerald-800">|</span>
                <span class="font-semibold">Mois</span>
                <span class="font-mono font-medium tabular-nums text-emerald-800 dark:text-emerald-300">
                    {{ number_format($monthly_total, 3, ',', ' ') }} DT
                </span>
            </div>
        </x-filament::section>
    </div>

    <div
        @class([
            'sticky bottom-0 z-30 border-t-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-2 backdrop-blur-md',
            'border-emerald-600 bg-gradient-to-t from-emerald-50/95 to-white/95 dark:from-emerald-950/90 dark:to-gray-950/95' => ! $this->existingTipRecord,
            'border-violet-600 bg-gradient-to-t from-violet-50/95 to-white/95 dark:from-violet-950/90 dark:to-gray-950/95' => $this->existingTipRecord,
        ])
    >
        @if ($this->existingTipRecord)
            <x-filament::button
                color="gray"
                type="button"
                wire:click="confirm"
                wire:loading.attr="disabled"
                class="w-full min-h-[52px] justify-center rounded-2xl border-2 border-b-[6px] border-yellow-500 bg-yellow-500 px-4 py-3.5 text-base font-black text-gray-950 dark:text-white shadow-lg shadow-yellow-900/30 active:translate-y-0.5 active:border-b-2 hover:bg-yellow-600 focus:ring-4 focus:ring-yellow-400/60 dark:border-yellow-400 dark:bg-yellow-600 dark:text-gray-900 dark:hover:bg-yellow-700"
            >
                <span class="inline-flex items-center rounded-xl bg-yellow-700 px-2 py-1 font-black text-red-500 dark:bg-yellow-400 dark:text-gray-950">
                    🔄 <span class="ml-1">Remplacer l’enregistrement</span>
                </span>
           
            </x-filament::button>
        @else
            <x-filament::button
                color="success"
                type="button"
           
                wire:click="confirm"
                wire:loading.attr="disabled"
                class="w-full min-h-[52px] justify-center rounded-2xl border-2 border-b-[6px] border-emerald-800 bg-gradient-to-b from-emerald-500 to-emerald-600 px-4 py-3.5 text-base font-black text-white shadow-lg shadow-emerald-900/25 active:translate-y-0.5 active:border-b-2 hover:from-emerald-400 hover:to-emerald-500 focus:ring-4 focus:ring-emerald-400/50 dark:border-emerald-500 dark:from-emerald-600 dark:to-emerald-700 dark:text-white dark:hover:from-emerald-500 dark:hover:to-emerald-600"
            >
                ✅ Valider et enregistrer
            </x-filament::button>
        @endif
    </div>

    <x-filament::section
        :compact="true"
        :heading="null"
        class="border-2 border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
    >
        <details>
            <summary class="cursor-pointer text-xs font-semibold text-gray-900 dark:text-gray-100">
                Historique caisse (3 jours)
            </summary>
            <div class="mt-2 space-y-1.5">
                @foreach($this->recentFinanceHistory() as $h)
                    <div @class([
                        'flex items-center justify-between rounded-md border px-2 py-1 text-[11px]',
                        'border-danger-300 bg-danger-50 text-danger-900 dark:border-danger-700 dark:bg-danger-950/30 dark:text-danger-100' => $h->close_status !== 'success',
                        'border-gray-200 bg-gray-50 text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100' => $h->close_status === 'success',
                    ])>
                        <span class="font-mono">{{ $h->business_date?->format('d/m') }} · {{ $h->shift === 'lunch' ? 'Midi' : 'Soir' }}</span>
                        <span class="inline-flex items-center gap-1">
                            @if($h->close_status === 'success')
                                <x-filament::icon icon="heroicon-o-check-circle" class="h-4 w-4 text-success-600 dark:text-success-400" />
                            @else
                                <x-filament::icon icon="heroicon-o-x-circle" class="h-4 w-4 text-danger-600 dark:text-danger-400" />
                            @endif
                            <span class="font-mono">{{ number_format((float) ($h->final_tip_amount ?? $h->chips ?? 0), 3, ',', ' ') }} DT</span>
                        </span>
                    </div>
                @endforeach
            </div>
        </details>
    </x-filament::section>

    <div
        wire:loading.delay
        wire:loading.class.remove="hidden"
        wire:loading.class="flex"
        class="hidden fixed inset-0 z-40 items-start justify-center bg-gray-950/35 pt-24 backdrop-blur-sm dark:bg-gray-950/55"
    >
        <x-filament::loading-indicator class="h-10 w-10 text-primary-600" />
    </div>
</x-filament-panels::page>
