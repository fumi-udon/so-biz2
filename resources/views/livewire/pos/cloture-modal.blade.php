@php
    $locked = $this->uiState === 'in_flight';
    $isCash = $this->paymentMethod === 'cash';
    $canConfirm = ! $locked
        && (! $isCash || ((int) ($this->tenderedMinor ?? 0) >= $this->finalTotalMinor));
@endphp

<div class="fi-no-print">
    @if ($open)
        <div
            class="fixed inset-0 z-110 flex items-end justify-center sm:items-center"
            style="isolation: isolate"
            role="dialog"
            aria-modal="true"
            wire:key="cloture-modal"
        >
            <div class="absolute inset-0 bg-slate-950/70" wire:click="closeModal"></div>

            <div
                @click.stop
                class="relative z-1 m-0 flex max-h-[92dvh] w-full max-w-lg flex-col overflow-hidden rounded-t-2xl border-2 border-blue-400 bg-white text-gray-950 shadow-2xl sm:m-4 sm:rounded-2xl dark:border-blue-700 dark:bg-slate-900 dark:text-white"
            >
                <div class="flex shrink-0 items-center justify-between border-b-2 border-blue-300 bg-blue-50 px-3 py-2.5 dark:border-blue-700 dark:bg-blue-950/30">
                    <h3 class="text-sm font-bold">
                        {{ __('rad_table.cloture_title') }} — {{ $this->tableLabel }}
                    </h3>
                    <button
                        type="button"
                        wire:click="closeModal"
                        wire:loading.attr="disabled"
                        wire:target="closeModal,confirm,confirmBypass"
                        class="rounded p-1 text-sm font-bold text-gray-900 hover:bg-blue-100 dark:text-gray-200 dark:hover:bg-blue-900/40"
                    >
                        ×
                    </button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3 space-y-3">
                    {{-- Breakdown --}}
                    <dl class="space-y-1 rounded-md border-2 border-slate-200 bg-white p-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-600 dark:text-gray-300">{{ __('rad_table.cloture_subtotal') }}</dt>
                            <dd class="tabular-nums">{{ $this->formatMinor($this->subtotalMinor) }}</dd>
                        </div>
                        @if ($this->discountAppliedMinor > 0)
                            <div class="flex items-center justify-between text-rose-600 dark:text-rose-400">
                                <dt>{{ __('rad_table.cloture_discount') }}</dt>
                                <dd class="tabular-nums">
                                    − {{ $this->formatMinor($this->discountAppliedMinor) }}
                                </dd>
                            </div>
                        @endif
                        @if ($this->roundingAdjustmentMinor > 0)
                            <div class="flex items-center justify-between text-gray-500 dark:text-gray-400">
                                <dt>{{ __('rad_table.cloture_rounding') }}</dt>
                                <dd class="tabular-nums">− {{ $this->formatMinor($this->roundingAdjustmentMinor) }}</dd>
                            </div>
                        @endif
                        <div class="flex items-center justify-between border-t border-gray-200 pt-2 dark:border-gray-600">
                            <dt class="font-bold">{{ __('rad_table.cloture_total') }}</dt>
                            <dd class="text-2xl font-extrabold tabular-nums text-emerald-600 dark:text-emerald-400">
                                {{ $this->formatMinor($this->finalTotalMinor) }}
                            </dd>
                        </div>
                    </dl>

                    {{-- Payment method --}}
                    <div class="grid grid-cols-3 gap-2" wire:key="pm-row">
                        @foreach (['cash', 'card', 'voucher'] as $pm)
                            <button
                                type="button"
                                wire:click="pickPayment('{{ $pm }}')"
                                @disabled($locked)
                                wire:loading.attr="disabled"
                                wire:target="pickPayment,confirm"
                                class="rounded-md border px-2 py-1.5 text-xs font-bold {{ $this->paymentMethod === $pm ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-gray-300 bg-white text-gray-900 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-100' }}"
                            >
                                {{ __('rad_table.cloture_payment_'.$pm) }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Cash tender --}}
                    @if ($isCash)
                        <div class="space-y-2" wire:key="cash-row">
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    wire:click="setTendered({{ $this->justeMinor }})"
                                    @disabled($locked)
                                    wire:loading.attr="disabled"
                                    wire:target="setTendered,confirm"
                                    class="rounded-md bg-sky-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-sky-700 disabled:opacity-50"
                                >
                                    {{ __('rad_table.cloture_juste') }} · {{ $this->formatMinor($this->justeMinor) }}
                                </button>
                                @foreach ($this->procheMinor as $p)
                                    <button
                                        type="button"
                                        wire:click="setTendered({{ (int) $p }})"
                                        @disabled($locked)
                                        wire:loading.attr="disabled"
                                        wire:target="setTendered,confirm"
                                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-50"
                                    >
                                        {{ $this->formatMinor((int) $p) }}
                                    </button>
                                @endforeach
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-200">
                                    {{ __('rad_table.cloture_tendered') }}
                                </label>
                                <input
                                    type="number"
                                    inputmode="numeric"
                                    min="0"
                                    step="100"
                                    wire:model.live.debounce.300ms="tenderedMinor"
                                    class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 text-right text-lg tabular-nums dark:border-gray-600 dark:bg-gray-800"
                                    @disabled($locked)
                                    wire:loading.attr="disabled"
                                    wire:target="confirm"
                                />
                            </div>
                            <div class="flex items-center justify-between rounded-md bg-amber-50 px-3 py-2 text-sm font-bold text-amber-700 dark:bg-amber-900/30 dark:text-amber-200">
                                <span>{{ __('rad_table.cloture_change') }}</span>
                                <span class="text-xl tabular-nums">{{ $this->formatMinor($this->changeMinor) }}</span>
                            </div>
                        </div>
                    @endif

                    {{-- Bypass section --}}
                    <details class="rounded-md border border-rose-200 bg-rose-50/60 p-3 text-xs dark:border-rose-800 dark:bg-rose-950/30" @if ($this->bypassMode) open @endif>
                        <summary class="cursor-pointer font-bold text-rose-700 dark:text-rose-300">
                            {{ __('rad_table.cloture_bypass_title') }}
                        </summary>
                        <div class="mt-2 space-y-2">
                            <div>
                                <label class="block font-bold text-gray-700 dark:text-gray-200">
                                    Manager
                                </label>
                                <select
                                    wire:model="bypassApproverStaffId"
                                    wire:loading.attr="disabled"
                                    wire:target="confirmBypass"
                                    class="mt-1 w-full rounded-md border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800"
                                >
                                    <option value="">—</option>
                                    @foreach ($this->approverOptions as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-gray-700 dark:text-gray-200">
                                    {{ __('rad_table.cloture_bypass_pin') }}
                                </label>
                                <input
                                    type="password"
                                    inputmode="numeric"
                                    wire:model="bypassApproverPin"
                                    wire:loading.attr="disabled"
                                    wire:target="confirmBypass"
                                    class="mt-1 w-full rounded-md border-gray-300 px-2 py-1.5 text-sm tracking-widest dark:border-gray-600 dark:bg-gray-800"
                                />
                            </div>
                            <div>
                                <label class="block font-bold text-gray-700 dark:text-gray-200">
                                    {{ __('rad_table.cloture_bypass_reason') }}
                                </label>
                                <input
                                    type="text"
                                    wire:model="bypassReason"
                                    wire:loading.attr="disabled"
                                    wire:target="confirmBypass"
                                    class="mt-1 w-full rounded-md border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800"
                                    maxlength="255"
                                />
                            </div>
                            <button
                                type="button"
                                wire:click="confirmBypass"
                                @disabled($locked)
                                wire:loading.attr="disabled"
                                wire:target="confirmBypass"
                                class="w-full rounded-md bg-rose-600 px-3 py-2 text-xs font-bold text-white shadow-sm hover:bg-rose-700 disabled:opacity-50"
                            >
                                {{ __('rad_table.cloture_bypass_submit') }}
                            </button>
                        </div>
                    </details>
                </div>

                <div class="flex shrink-0 items-center justify-between gap-2 border-t-2 border-blue-300 bg-white px-3 py-2.5 dark:border-blue-700 dark:bg-slate-900">
                    <button
                        type="button"
                        wire:click="closeModal"
                        @disabled($locked)
                        wire:loading.attr="disabled"
                        wire:target="closeModal,confirm,confirmBypass"
                        class="rounded-md border-2 border-slate-300 bg-white px-3 py-2 text-xs font-extrabold text-slate-800 hover:bg-slate-100 disabled:opacity-50 dark:bg-slate-800 dark:text-gray-100"
                    >
                        {{ __('rad_table.cloture_cancel') }}
                    </button>
                    <button
                        type="button"
                        wire:click="confirm"
                        @disabled(! $canConfirm)
                        wire:loading.attr="disabled"
                        wire:target="confirm"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-extrabold text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="confirm">{{ __('rad_table.cloture_confirm') }}</span>
                        <span wire:loading wire:target="confirm">{{ __('pos.ui_working') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
