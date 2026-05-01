@php
    $locked = $this->uiState === 'in_flight';
    $clotureModalWindowAttrs = (new \Illuminate\View\ComponentAttributeBag)->class([
        'max-h-[min(92dvh,720px)] max-w-full overflow-hidden',
    ]);
    /** 近似値ボタンは最大3個（ぴったり1 + proche 最大2） */
    $procheMinorForButtons = array_slice($this->procheMinor, 0, 2);
@endphp

<div class="fi-no-print">
    @if ($open)
        @teleport('body')
            <div
                data-pos-cloture-modal="true"
                wire:key="cloture-modal-{{ $this->tableSessionId }}"
                x-data="{
                    submitting: false,
                    inputDt: $wire.entangle('tenderedDtInput'),
                    finalMinor: {{ (int) $this->finalTotalMinor }},
                    changeMinor() {
                        const parsed = Number.parseFloat(this.inputDt || 0)
                        const normalized = Number.isFinite(parsed) ? parsed : 0

                        return Math.round(normalized * 1000) - this.finalMinor
                    },
                    setFromMinor(minor) {
                        this.inputDt = (minor / 1000).toFixed(3)
                    },
                    formattedChange() {
                        return (this.changeMinor() / 1000).toFixed(3) + ' DT'
                    },
                }"
                x-init="
                    $nextTick(() => {
                        if (!inputDt) {
                            inputDt = ''
                        }

                        window.dispatchEvent(
                            new CustomEvent('open-modal', {
                                detail: { id: 'pos-cloture-checkout-modal' },
                                bubbles: true,
                            }),
                        )
                    })
                "
                x-on:close-modal.window="
                    if ($event.detail && $event.detail.id === 'pos-cloture-checkout-modal') {
                        $wire.closeModal()
                    }
                "
            >
                <x-filament::modal
                    id="pos-cloture-checkout-modal"
                    aria-labelledby="pos-cloture-checkout-modal-title"
                    width="sm"
                    alignment="center"
                    display-classes="block"
                    :close-button="true"
                    :close-by-clicking-away="true"
                    :close-by-escaping="true"
                    :sticky-footer="true"
                    :extra-modal-window-attribute-bag="$clotureModalWindowAttrs"
                >
                    <div
                        class="pos-cloture-checkout -mx-6 -mt-2 max-h-[min(58dvh,420px)] min-h-0 overflow-y-auto overscroll-contain px-4 pb-2 pt-1 text-center text-[12px] font-medium leading-snug text-slate-800 antialiased dark:text-slate-200 sm:px-5"
                    >
                        <h2
                            id="pos-cloture-checkout-modal-title"
                            class="sr-only"
                        >
                            {{ __('rad_table.cloture_title') }}
                        </h2>

                        <div class="space-y-2">
                            <div
                                class="rounded-md border border-red-600/80 bg-red-50/70 px-2 py-1.5 dark:border-red-500/80 dark:bg-red-950/40"
                                title="{{ $this->tableLabel }}"
                            >
                                <div class="flex items-center justify-center gap-1.5">
                                    <x-filament::icon
                                        icon="heroicon-o-table-cells"
                                        class="h-4 w-4 shrink-0 text-red-600 dark:text-red-400"
                                        aria-hidden="true"
                                    />
                                    <p
                                        class="min-w-0 flex-1 break-words text-[12px] font-bold uppercase tracking-wide text-red-700 dark:text-red-300"
                                        aria-label="{{ $this->tableLabel }}"
                                    >
                                        {{ $this->tableLabel }}
                                    </p>
                                    <x-filament::icon
                                        icon="heroicon-o-table-cells"
                                        class="h-4 w-4 shrink-0 text-red-600 dark:text-red-400"
                                        aria-hidden="true"
                                    />
                                </div>
                            </div>

                            <div
                                class="rounded-md border border-sky-600/70 bg-sky-50/60 px-2 py-1.5 dark:border-sky-500/70 dark:bg-sky-950/30"
                                aria-label="{{ __('rad_table.cloture_total') }}"
                            >
                                <p class="text-[12px] font-semibold uppercase tracking-wider text-sky-800 dark:text-sky-200">
                                    {{ __('rad_table.cloture_total') }}
                                </p>
                                <p
                                    class="mt-0.5 text-[12px] font-bold tabular-nums tracking-wide text-slate-900 underline decoration-slate-400 dark:text-white dark:decoration-slate-500"
                                    aria-live="polite"
                                >
                                    {{ $this->formatMinor($this->finalTotalMinor) }}
                                </p>
                            </div>

                            @if ($this->discountAppliedMinor > 0 || $this->roundingAdjustmentMinor > 0)
                                <div class="space-y-1 border-y border-amber-500/80 py-1.5 dark:border-amber-400/80">
                                    @if ($this->discountAppliedMinor > 0)
                                        <div class="flex justify-between gap-2 text-[12px] text-slate-700 dark:text-slate-300">
                                            <span>{{ __('rad_table.cloture_discount') }}</span>
                                            <span class="tabular-nums">− {{ $this->formatMinor($this->discountAppliedMinor) }}</span>
                                        </div>
                                    @endif
                                    @if ($this->roundingAdjustmentMinor > 0)
                                        <div class="flex justify-between gap-2 text-[12px] text-slate-600 dark:text-slate-400">
                                            <span>{{ __('rad_table.cloture_rounding') }}</span>
                                            <span class="tabular-nums">− {{ $this->formatMinor($this->roundingAdjustmentMinor) }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            <div class="flex w-full gap-1.5">
                                <x-filament::button
                                    type="button"
                                    size="xs"
                                    outlined
                                    x-on:click="setFromMinor({{ (int) $this->justeMinor }})"
                                    wire:key="cloture-sugg-juste-{{ $this->tableSessionId }}"
                                    :disabled="$locked"
                                    wire:loading.attr="disabled"
                                    wire:target="confirm"
                                    class="min-w-0 flex-1 !h-auto !px-1.5 !py-1.5 !text-[12px] !font-semibold !leading-tight"
                                >
                                    <span class="block text-center leading-tight">
                                        <span class="block text-[12px] font-semibold uppercase tracking-wide">
                                            {{ __('rad_table.cloture_juste') }}
                                        </span>
                                        <span class="mt-0.5 block tabular-nums">
                                            {{ $this->formatMinor($this->justeMinor) }}
                                        </span>
                                    </span>
                                </x-filament::button>
                                @foreach ($procheMinorForButtons as $p)
                                    <x-filament::button
                                        type="button"
                                        size="xs"
                                        outlined
                                        x-on:click="setFromMinor({{ (int) $p }})"
                                        wire:key="cloture-sugg-{{ $this->tableSessionId }}-{{ (int) $p }}"
                                        :disabled="$locked"
                                        wire:loading.attr="disabled"
                                        wire:target="confirm"
                                        class="min-w-0 flex-1 !h-auto !px-1.5 !py-1.5 !text-[12px] !font-semibold tabular-nums !leading-tight"
                                    >
                                        {{ $this->formatMinor((int) $p) }}
                                    </x-filament::button>
                                @endforeach
                            </div>

                            <div class="border-t border-sky-500/70 pt-1.5 dark:border-sky-400/70">
                                <x-filament::input.wrapper
                                    :disabled="$locked"
                                    suffix="DT"
                                    prefix-icon="heroicon-o-banknotes"
                                    prefix-icon-color="success"
                                    class="!ring-1 !ring-emerald-600/80 dark:!ring-emerald-400/80"
                                >
                                    <x-filament::input
                                        id="cloture-tendered-dt-{{ $this->tableSessionId }}"
                                        type="text"
                                        inputmode="decimal"
                                        autocomplete="off"
                                        x-model="inputDt"
                                        :disabled="$locked"
                                        wire:loading.attr="disabled"
                                        wire:target="confirm"
                                        aria-label="{{ __('rad_table.cloture_tendered') }}"
                                        class="!py-2 !text-[12px] !font-semibold !leading-snug tabular-nums text-center !text-blue-950 !placeholder:text-blue-600/40 disabled:!text-blue-950/50 disabled:[-webkit-text-fill-color:theme(colors.blue.950)] dark:!text-blue-100 dark:!placeholder:text-blue-300/40 dark:disabled:!text-blue-100/50 dark:disabled:[-webkit-text-fill-color:theme(colors.blue.100)]"
                                    />
                                </x-filament::input.wrapper>
                            </div>

                            <div
                                class="rounded-md border border-slate-300/90 bg-slate-50/80 px-2 py-1.5 text-center dark:border-slate-600 dark:bg-slate-900/50"
                                aria-live="polite"
                            >
                                <p class="text-[12px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                                    {{ __('rad_table.cloture_change') }}
                                </p>
                                <p class="mt-0.5 text-[12px] font-semibold tabular-nums text-neutral-950 dark:text-neutral-100" x-text="formattedChange()">
                                    0.000 DT
                                </p>
                            </div>
                        </div>
                    </div>

                    <x-slot name="footer">
                        <div class="fi-modal-footer-actions flex flex-wrap items-center justify-center gap-3 pb-2 text-[12px]">
                            {{-- キャンセル（Filament 非依存・Tailwind のみ） --}}
                            <button
                                type="button"
                                wire:click="closeModal"
                                @disabled($locked)
                                wire:loading.attr="disabled"
                                wire:target="closeModal,confirm"
                                class="inline-flex min-h-11 items-center justify-center rounded-xl border-2 border-slate-300 bg-white px-6 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                            >
                                {{ __('rad_table.cloture_cancel') }}
                            </button>

                            {{-- 確定（Filament 非依存） --}}
                            <button
                                type="button"
                                x-on:click="
                                    if (submitting) { return }
                                    if (changeMinor() < 0) { return }
                                    submitting = true
                                    $wire.set('tenderedDtInput', inputDt)
                                    $wire.confirm().finally(() => {
                                        submitting = false
                                    })
                                "
                                x-bind:disabled="submitting || {{ json_encode($locked) }} || changeMinor() < 0"
                                wire:loading.attr="disabled"
                                wire:target="confirm"
                                class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-600 px-8 text-sm font-bold text-white shadow-md transition hover:bg-emerald-500 focus:outline-none disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-emerald-600 dark:bg-emerald-500 dark:hover:bg-emerald-400"
                            >
                                <span wire:loading.remove wire:target="confirm">{{ __('rad_table.cloture_confirm') }}</span>
                                <span wire:loading wire:target="confirm">{{ __('pos.ui_working') }}</span>
                            </button>
                        </div>
                    </x-slot>
                </x-filament::modal>
            </div>
        @endteleport
    @endif
</div>
