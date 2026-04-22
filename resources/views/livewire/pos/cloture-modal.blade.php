@php
    $locked = $this->uiState === 'in_flight';
    $canConfirm = ! $locked && ((int) ($this->tenderedMinor ?? 0) >= $this->finalTotalMinor);
@endphp

<div class="fi-no-print">
    @if ($open)
        @teleport('body')
            <div
                data-pos-cloture-modal="true"
                wire:key="cloture-modal-{{ $this->tableSessionId }}"
                x-data
                x-init="
                    $nextTick(() => {
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
                    width="md"
                    display-classes="block"
                    :close-button="true"
                    :close-by-clicking-away="true"
                    :close-by-escaping="true"
                >
                    <x-slot name="header">
                        <h2
                            id="pos-cloture-checkout-modal-title"
                            class="sr-only"
                        >
                            {{ __('rad_table.cloture_title') }}
                        </h2>
                    </x-slot>

                    <div class="space-y-4">
                        <div
                            class="w-full rounded-lg border-2 border-red-600 bg-red-50/60 px-2 py-4 shadow-sm dark:border-red-500 dark:bg-red-950/35 sm:px-4 sm:py-5"
                            title="{{ $this->tableLabel }}"
                        >
                            <div class="flex w-full items-center justify-center gap-2 sm:gap-4">
                                <x-filament::icon
                                    icon="heroicon-o-table-cells"
                                    class="h-9 w-9 shrink-0 text-red-600 sm:h-11 sm:w-11 dark:text-red-400"
                                    aria-hidden="true"
                                />
                                <p
                                    class="animate-pos-cloture-table-blink min-w-0 flex-1 break-words text-center text-2xl font-black leading-tight tracking-tight text-red-600 sm:text-3xl md:text-4xl dark:text-red-400"
                                    aria-label="{{ $this->tableLabel }}"
                                >
                                    {{ $this->tableLabel }}
                                </p>
                                <x-filament::icon
                                    icon="heroicon-o-table-cells"
                                    class="h-9 w-9 shrink-0 text-red-600 sm:h-11 sm:w-11 dark:text-red-400"
                                    aria-hidden="true"
                                />
                            </div>
                        </div>
                        <x-filament::section
                            :compact="true"
                            aria-label="{{ __('rad_table.cloture_total') }}"
                            class="!ring-2 !ring-sky-500 dark:!ring-sky-400"
                        >
                            <p
                                class="text-xl font-semibold tabular-nums text-gray-950 underline dark:text-white sm:text-2xl"
                                aria-live="polite"
                            >
                                {{ $this->formatMinor($this->finalTotalMinor) }}
                            </p>
                        </x-filament::section>
                        <div class="grid grid-cols-1 gap-2 rounded-lg border-2 border-slate-300 bg-slate-50/70 px-3 py-2 dark:border-slate-600 dark:bg-slate-900/40 sm:grid-cols-2 sm:gap-3 sm:px-4">
                            <div class="text-center">
                                <p class="text-[11px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-300">
                                    TENDERED / RECU
                                </p>
                                <p class="mt-0.5 text-xl font-black uppercase tracking-widest tabular-nums text-blue-700 dark:text-blue-300 sm:text-2xl">
                                    {{ $this->formatMinor($this->tenderedDisplayMinor) }}
                                </p>
                            </div>
                            <div class="text-center">
                                <p class="text-[11px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-300">
                                    CHANGE / RESTANT
                                </p>
                                <p @class([
                                    'mt-0.5 text-xl font-black uppercase tracking-widest tabular-nums sm:text-2xl',
                                    'text-amber-500 dark:text-amber-400' => $this->changeTone === 'positive',
                                    'text-red-600 dark:text-red-400' => $this->changeTone === 'short',
                                    'text-slate-800 dark:text-slate-200' => $this->changeTone === 'neutral',
                                ])>
                                    {{ $this->formatSignedMinor($this->changeMinor) }}
                                </p>
                            </div>
                        </div>

                        @if ($this->discountAppliedMinor > 0 || $this->roundingAdjustmentMinor > 0)
                            <div class="space-y-1 border-y-2 border-amber-500 py-2.5 dark:border-amber-400">
                                @if ($this->discountAppliedMinor > 0)
                                    <div class="flex justify-between text-sm text-gray-700 dark:text-gray-300">
                                        <span>{{ __('rad_table.cloture_discount') }}</span>
                                        <span class="tabular-nums">− {{ $this->formatMinor($this->discountAppliedMinor) }}</span>
                                    </div>
                                @endif
                                @if ($this->roundingAdjustmentMinor > 0)
                                    <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400">
                                        <span>{{ __('rad_table.cloture_rounding') }}</span>
                                        <span class="tabular-nums">− {{ $this->formatMinor($this->roundingAdjustmentMinor) }}</span>
                                    </div>
                                @endif
                            </div>
                        @endif

                        <div class="flex flex-wrap gap-2">
                            <x-filament::button
                                type="button"
                                size="sm"
                                outlined
                                wire:click="setTendered({{ $this->justeMinor }})"
                                :disabled="$locked"
                                wire:loading.attr="disabled"
                                wire:target="setTendered,confirm"
                            >
                                <span class="block text-center">
                                    <span class="block text-xs font-semibold uppercase tracking-wide">
                                        {{ __('rad_table.cloture_juste') }}
                                    </span>
                                    <span class="mt-0.5 block tabular-nums">
                                        {{ $this->formatMinor($this->justeMinor) }}
                                    </span>
                                </span>
                            </x-filament::button>
                            @foreach ($this->procheMinor as $p)
                                <x-filament::button
                                    type="button"
                                    size="sm"
                                    outlined
                                    wire:click="setTendered({{ (int) $p }})"
                                    :disabled="$locked"
                                    wire:loading.attr="disabled"
                                    wire:target="setTendered,confirm"
                                >
                                    {{ $this->formatMinor((int) $p) }}
                                </x-filament::button>
                            @endforeach
                        </div>

                        <div class="border-t-2 border-sky-500 pt-3 dark:border-sky-400">
                            <x-filament::input.wrapper
                                :disabled="$locked"
                                suffix="DT"
                                prefix-icon="heroicon-o-banknotes"
                                prefix-icon-color="success"
                                class="!ring-2 !ring-emerald-500 !shadow-md dark:!ring-emerald-400"
                            >
                                <x-filament::input
                                    id="cloture-tendered-dt-{{ $this->tableSessionId }}"
                                    type="text"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    wire:model.live.debounce.500ms="tenderedDtInput"
                                    :disabled="$locked"
                                    wire:loading.attr="disabled"
                                    wire:target="confirm"
                                    aria-label="{{ __('rad_table.cloture_tendered') }}"
                                    class="!py-3 !text-[33px] !font-semibold !leading-none tabular-nums text-center !text-blue-700 !placeholder:text-blue-400/70 disabled:!text-blue-500/60 disabled:[-webkit-text-fill-color:theme(colors.blue.500)] dark:!text-blue-300 dark:!placeholder:text-blue-400/50 dark:disabled:!text-blue-400/50 dark:disabled:[-webkit-text-fill-color:theme(colors.blue.400)] sm:!text-[35px]"
                                />
                            </x-filament::input.wrapper>
                        </div>

                        <div class="flex items-center justify-between gap-3 border-t-2 border-emerald-500 pt-3 dark:border-emerald-400">
                            <span class="text-sm font-medium text-gray-950 dark:text-white">
                                {{ __('rad_table.cloture_change') }}
                            </span>
                            <span class="text-base font-semibold tabular-nums text-gray-950 dark:text-white sm:text-lg">
                                {{ $this->formatMinor($this->changeMinor) }}
                            </span>
                        </div>
                    </div>

                    <x-slot name="footer">
                        <div class="fi-modal-footer-actions flex flex-wrap items-center gap-3">
                            <x-filament::button
                                type="button"
                                color="gray"
                                outlined
                                wire:click="closeModal"
                                :disabled="$locked"
                                wire:loading.attr="disabled"
                                wire:target="closeModal,confirm"
                            >
                                {{ __('rad_table.cloture_cancel') }}
                            </x-filament::button>
                            <x-filament::button
                                type="button"
                                color="danger"
                                wire:click="confirm"
                                x-data="{ flash: false, t: null }"
                                x-on:click="
                                    flash = true;
                                    if (t) clearTimeout(t);
                                    t = setTimeout(() => { flash = false; t = null }, 450);
                                "
                                x-bind:class="{ 'pos-tile-select-flash': flash }"
                                :disabled="! $canConfirm"
                                wire:loading.attr="disabled"
                                wire:target="confirm"
                                :loading-indicator="false"
                            >
                                <span wire:loading.remove wire:target="confirm">{{ __('rad_table.cloture_confirm') }}</span>
                                <span wire:loading wire:target="confirm">{{ __('pos.ui_working') }}</span>
                            </x-filament::button>
                        </div>
                    </x-slot>
                </x-filament::modal>
            </div>
        @endteleport
    @endif
</div>
