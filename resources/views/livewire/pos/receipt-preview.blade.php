{{-- Single root required: nested Livewire must not have sibling <link> roots or wire:click binds to parent TableActionHost. --}}
<div
    wire:key="pos-receipt-preview-shell"
    class="fixed inset-0 z-[340] flex h-[100dvh] max-h-[100dvh] max-w-full flex-col overflow-x-hidden overflow-y-hidden bg-white text-gray-950 dark:bg-slate-950 dark:text-white"
    style="isolation: isolate;"
    x-data="{ isPrinting: false }"
    x-on:pos-print-lifecycle.window="if ($event.detail && $event.detail.phase === 'start') { isPrinting = true } else if ($event.detail && $event.detail.phase === 'end') { isPrinting = false }"
>
    <header class="flex shrink-0 items-center gap-2 border-b border-gray-200 bg-white px-2 py-2 dark:border-slate-700 dark:bg-slate-900">
        <button
            type="button"
            wire:click="closePreview"
            wire:loading.attr="disabled"
            wire:target="closePreview"
            class="touch-manipulation inline-flex min-h-12 shrink-0 items-center justify-center rounded-lg border-2 border-slate-600 bg-white px-3 py-2.5 text-sm font-extrabold uppercase tracking-wide text-gray-950 shadow-sm hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-500 dark:bg-slate-800 dark:text-white dark:hover:bg-slate-700 dark:focus-visible:ring-slate-500"
        >
            <span wire:loading.remove wire:target="closePreview" class="max-w-[9rem] truncate sm:max-w-[11rem]">← {{ __('pos.receipt_preview_back_to_pos') }}</span>
            <span wire:loading wire:target="closePreview" class="text-xs">{{ __('pos.ui_working') }}</span>
        </button>

        <div class="min-w-0 flex-1 text-center">
            <p class="truncate text-xs font-black uppercase tracking-wide text-blue-800 dark:text-blue-300">
                {{ $this->viewData['doc_banner'] }}
            </p>
            <p class="truncate text-[10px] text-gray-500 dark:text-slate-400">{{ $this->viewData['table_label'] }}</p>
        </div>

        <button
            type="button"
            x-bind:disabled="isPrinting"
            wire:click="printFromPreview"
            wire:loading.attr="disabled"
            wire:target="printFromPreview"
            class="touch-manipulation inline-flex min-h-12 shrink-0 items-center justify-center rounded-lg border-2 border-emerald-800 bg-emerald-600 px-3 py-2.5 text-sm font-extrabold uppercase tracking-wide text-white shadow-sm hover:bg-emerald-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 disabled:cursor-not-allowed disabled:opacity-50 dark:border-emerald-700 dark:bg-emerald-600 dark:text-white dark:hover:bg-emerald-500"
        >
            <span wire:loading.remove wire:target="printFromPreview">{{ __('pos.receipt_preview_print') }}</span>
            <span wire:loading wire:target="printFromPreview">{{ __('pos.ui_working') }}</span>
        </button>
    </header>

    <main class="min-h-0 flex-1 overflow-y-auto overscroll-none px-2 pb-2 pt-1">
        @php
            $staffReceipt = (bool) ($this->viewData['is_staff_meal_table'] ?? false);
        @endphp
        <div class="mx-auto w-full max-w-md rounded border border-gray-200 bg-gray-50/80 p-2 dark:border-slate-700 dark:bg-slate-900/80">
            @if (($this->viewData['intent'] ?? '') === 'copy')
                <p class="mb-1 text-center text-[10px] font-black uppercase tracking-wider text-amber-800 dark:text-amber-300">DUPLICATA</p>
            @endif

            @if (! empty($this->viewData['original_settled_at']))
                <p class="mb-1 text-center text-[10px] text-gray-600 dark:text-slate-400">
                    {{ __('pos.duplicata_original_settled_line', ['at' => $this->viewData['original_settled_at']]) }}
                </p>
            @endif
            @if (($this->viewData['intent'] ?? '') === 'copy')
                <p class="mb-1 text-center text-[10px] text-gray-500 dark:text-slate-500">
                    {{ __('pos.duplicata_generated_label') }}: {{ $this->viewData['printed_at'] }}
                </p>
            @endif

            <div
                @class([
                    'space-y-0.5 border-y border-dashed border-gray-300 py-1.5 dark:border-slate-600',
                    'text-[12px] sm:landscape:text-[13px]' => $staffReceipt,
                    'text-[11px]' => ! $staffReceipt,
                ])
            >
                @foreach ($this->viewData['line_vat_details'] as $row)
                    <div class="flex items-baseline justify-between gap-2 leading-tight">
                        @if (($row['kind'] ?? 'parent') === 'extra')
                            <span class="w-6 shrink-0 font-bold text-gray-400 dark:text-slate-500"></span>
                        @else
                            <span class="shrink-0 font-bold text-gray-900 dark:text-slate-100">{{ (int) ($row['qty'] ?? 0) }}×</span>
                        @endif
                        <span class="min-w-0 flex-1 truncate text-gray-900 dark:text-slate-100">{{ $row['name'] }}</span>
                        <span class="shrink-0 tabular-nums font-semibold text-gray-900 dark:text-slate-100">{{ $this->formatMinor((int) $row['amount_minor']) }}</span>
                    </div>
                @endforeach
            </div>

            @if ($staffReceipt && ! empty($this->viewData['line_vat_details']))
                <div class="mt-1.5 space-y-1.5 text-gray-800 dark:text-slate-200">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-2 gap-y-0.5">
                        <span class="shrink-0 font-black uppercase tracking-wide text-gray-900 dark:text-white">{{ __('pos.staff_meal_sous_total_ht_screen') }}:</span>
                        <span class="tabular-nums font-bold text-gray-950 dark:text-white">{{ $this->formatMinor((int) $this->viewData['subtotal_ht_minor']) }}</span>
                    </div>
                    <div class="flex flex-wrap items-baseline justify-between gap-x-2 gap-y-0.5">
                        <span class="shrink-0 font-black uppercase tracking-wide text-gray-900 dark:text-white">{{ __('pos.staff_meal_tva_label', ['rate' => $this->viewData['vat_rate_display']]) }}:</span>
                        <span class="tabular-nums font-bold text-gray-950 dark:text-white">{{ $this->formatMinor((int) $this->viewData['total_vat_minor']) }}</span>
                    </div>
                    @if ((int) ($this->viewData['order_discount_minor'] ?? 0) > 0)
                        <div class="flex flex-wrap items-center justify-end gap-2 pt-0.5">
                            <span class="text-base font-bold tabular-nums text-slate-500 line-through decoration-slate-400 sm:landscape:text-lg dark:text-slate-500 dark:decoration-slate-500">{{ $this->formatMinor((int) $this->viewData['staff_meal_gross_minor']) }}</span>
                            <span class="rounded bg-red-600 px-2 py-0.5 text-xs font-black uppercase tracking-widest text-white shadow-[0_0_10px_rgba(220,38,38,0.5)]">{{ __('pos.staff_meal_off_badge') }}</span>
                        </div>
                    @endif
                    @if ((int) ($this->viewData['rounding_adjustment_minor'] ?? 0) !== 0)
                        <div class="flex justify-between">
                            <span class="text-gray-700 dark:text-slate-300">{{ __('pos.receipt_rounding') }}</span>
                            <span @class([
                                'tabular-nums font-semibold',
                                'text-rose-700 dark:text-rose-300' => (int) ($this->viewData['rounding_adjustment_minor'] ?? 0) > 0,
                                'text-emerald-700 dark:text-emerald-300' => (int) ($this->viewData['rounding_adjustment_minor'] ?? 0) < 0,
                            ])>
                                {{ $this->formatMinor(-1 * (int) ($this->viewData['rounding_adjustment_minor'] ?? 0)) }}
                            </span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between border-t border-gray-300 pt-1.5 dark:border-slate-600">
                        <span class="text-sm font-black uppercase tracking-wide text-gray-900 dark:text-white">{{ __('pos.receipt_grand_total') }}</span>
                        <span class="text-2xl font-black uppercase tracking-widest tabular-nums text-amber-500 dark:text-amber-400">{{ $this->formatMinor((int) $this->viewData['total_minor']) }}</span>
                    </div>
                    @if (! empty($this->viewData['show_payment_block']))
                        <div class="mt-1 space-y-0.5 border-t border-gray-200 pt-1 text-[10px] text-gray-600 dark:border-slate-600 dark:text-slate-400">
                            @if (($this->viewData['payment_label'] ?? '') !== '')
                                <p class="text-center text-[9px] font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-500">
                                    {{ $this->viewData['payment_label'] }}
                                </p>
                            @endif
                            <div class="flex justify-between gap-2 tabular-nums">
                                <span class="text-gray-500 dark:text-slate-500">{{ __('rad_table.cloture_tendered') }}</span>
                                <span>{{ $this->formatMinor((int) ($this->viewData['tendered_minor'] ?? 0)) }}</span>
                            </div>
                            @if ((int) ($this->viewData['change_minor'] ?? 0) !== 0)
                                <div class="flex justify-between gap-2 tabular-nums">
                                    <span class="text-gray-500 dark:text-slate-500">{{ __('rad_table.cloture_change') }}</span>
                                    <span>{{ $this->formatMinor((int) ($this->viewData['change_minor'] ?? 0)) }}</span>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @else
                <div class="mt-1.5 space-y-0.5 text-[11px]">
                    <div class="flex justify-between text-gray-700 dark:text-slate-300">
                        <span>SOUS-TOTAL (HT)</span>
                        <span class="tabular-nums font-semibold">{{ $this->formatMinor((int) $this->viewData['subtotal_ht_minor']) }}</span>
                    </div>
                    <div class="flex justify-between text-gray-700 dark:text-slate-300">
                        <span>TVA</span>
                        <span class="tabular-nums font-semibold">{{ $this->formatMinor((int) $this->viewData['total_vat_minor']) }}</span>
                    </div>
                    @if ((int) ($this->viewData['order_discount_minor'] ?? 0) > 0)
                        <div class="flex justify-between text-rose-700 dark:text-rose-300">
                            <span>{{ __('pos.receipt_order_discount') }}</span>
                            <span class="tabular-nums font-semibold">−{{ $this->formatMinor((int) $this->viewData['order_discount_minor']) }}</span>
                        </div>
                    @endif
                    @if ((int) ($this->viewData['rounding_adjustment_minor'] ?? 0) !== 0)
                        <div class="flex justify-between">
                            <span class="text-gray-700 dark:text-slate-300">{{ __('pos.receipt_rounding') }}</span>
                            <span @class([
                                'tabular-nums font-semibold',
                                'text-rose-700 dark:text-rose-300' => (int) ($this->viewData['rounding_adjustment_minor'] ?? 0) > 0,
                                'text-emerald-700 dark:text-emerald-300' => (int) ($this->viewData['rounding_adjustment_minor'] ?? 0) < 0,
                            ])>
                                {{ $this->formatMinor(-1 * (int) ($this->viewData['rounding_adjustment_minor'] ?? 0)) }}
                            </span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between border-t border-gray-300 pt-1 text-sm font-bold text-gray-950 dark:border-slate-600 dark:text-white">
                        <span>{{ __('pos.receipt_grand_total') }}</span>
                        <span class="tabular-nums">{{ $this->formatMinor((int) $this->viewData['total_minor']) }}</span>
                    </div>
                    @if (! empty($this->viewData['show_payment_block']))
                        <div class="mt-1 space-y-0.5 border-t border-gray-200 pt-1 text-[10px] text-gray-600 dark:border-slate-600 dark:text-slate-400">
                            @if (($this->viewData['payment_label'] ?? '') !== '')
                                <p class="text-center text-[9px] font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-500">
                                    {{ $this->viewData['payment_label'] }}
                                </p>
                            @endif
                            <div class="flex justify-between gap-2 tabular-nums">
                                <span class="text-gray-500 dark:text-slate-500">{{ __('rad_table.cloture_tendered') }}</span>
                                <span>{{ $this->formatMinor((int) ($this->viewData['tendered_minor'] ?? 0)) }}</span>
                            </div>
                            @if ((int) ($this->viewData['change_minor'] ?? 0) !== 0)
                                <div class="flex justify-between gap-2 tabular-nums">
                                    <span class="text-gray-500 dark:text-slate-500">{{ __('rad_table.cloture_change') }}</span>
                                    <span>{{ $this->formatMinor((int) ($this->viewData['change_minor'] ?? 0)) }}</span>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endif

            @if (! empty($this->viewData['vat_buckets']))
                <div class="mt-1.5 border-t border-dashed border-gray-300 pt-1.5 dark:border-slate-600">
                    <p class="mb-0.5 text-center text-[9px] font-bold uppercase tracking-wide text-gray-500 dark:text-slate-400">TVA (détail)</p>
                    <div class="grid grid-cols-3 gap-0.5 text-[10px] font-semibold text-gray-600 dark:text-slate-400">
                        <span class="text-center">%</span>
                        <span class="text-center">HT</span>
                        <span class="text-center">TVA</span>
                    </div>
                    @foreach ($this->viewData['vat_buckets'] as $bucket)
                        <div class="grid grid-cols-3 gap-0.5 py-0.5 text-[11px] tabular-nums text-gray-900 dark:text-slate-200">
                            <span class="text-center">{{ number_format((float) $bucket['rate'], 2, '.', '') }}</span>
                            <span class="text-center">{{ $this->formatMinor((int) $bucket['ht_minor']) }}</span>
                            <span class="text-center">{{ $this->formatMinor((int) $bucket['vat_minor']) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </main>
</div>
