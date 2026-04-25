@php
    $locked = $this->uiState === 'in_flight';
    $scopeLabel = match ($this->scope) {
        'item' => __('pos.discount_scope_item'),
        'order' => __('pos.discount_scope_order'),
        'staff' => __('pos.discount_scope_staff'),
        default => '',
    };
@endphp

<div class="fi-no-print">
    @if ($open)
        <div
            class="fixed inset-0 z-115 flex max-w-[100vw] items-end justify-center overflow-x-hidden sm:items-center"
            role="dialog"
            aria-modal="true"
            wire:key="discount-modal"
            x-data="{ optimisticClosing: false }"
            x-show="!optimisticClosing"
        >
            <div class="absolute inset-0 bg-slate-950/70" wire:click="closeModal"></div>

            <div
                @click.stop
                class="relative z-1 m-0 flex max-h-[90dvh] w-full max-w-md flex-col overflow-hidden rounded-t-2xl border-2 border-blue-400 bg-white text-gray-950 shadow-2xl sm:m-4 sm:rounded-2xl dark:border-blue-700 dark:bg-slate-900 dark:text-white"
            >
                <div class="flex shrink-0 items-center justify-between border-b-2 border-blue-300 bg-blue-50 px-3 py-2.5 dark:border-blue-700 dark:bg-blue-950/30">
                    <h3 class="text-sm font-bold">
                        {{ __('pos.discount_title') }} — {{ $scopeLabel }}
                    </h3>
                    <button type="button" wire:click="closeModal"
                        wire:loading.attr="disabled"
                        wire:target="closeModal,submit"
                        class="rounded p-1 text-sm font-bold text-gray-900 hover:bg-blue-100 dark:text-gray-100 dark:hover:bg-blue-900/40">×</button>
                </div>

                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-white px-3 py-3 text-sm dark:bg-slate-900">
                    {{-- Amount / percent --}}
                    @if ($this->scope !== 'staff')
                        <div class="flex gap-2 text-xs">
                            <label class="inline-flex items-center gap-1 text-gray-900 dark:text-gray-100">
                                <input type="radio" wire:model.live="mode" value="flat" wire:loading.attr="disabled" wire:target="mode" /> {{ __('pos.discount_flat') }}
                            </label>
                            <label class="inline-flex items-center gap-1 text-gray-900 dark:text-gray-100">
                                <input type="radio" wire:model.live="mode" value="percent" wire:loading.attr="disabled" wire:target="mode" /> {{ __('pos.discount_percent') }}
                            </label>
                        </div>
                        @if ($this->mode === 'flat')
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-200">
                                    {{ __('pos.discount_flat_label') }}
                                </label>
                                <input type="number" min="0" step="100" wire:model.blur="flatMinor"
                                    wire:loading.attr="disabled" wire:target="submit"
                                    class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 text-right tabular-nums dark:border-gray-600 dark:bg-gray-800" />
                            </div>
                        @else
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-200">
                                    {{ __('pos.discount_percent_label') }}
                                </label>
                                <input type="number" min="0" max="10000" wire:model.blur="percentBasisPoints"
                                    wire:loading.attr="disabled" wire:target="submit"
                                    class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 text-right tabular-nums dark:border-gray-600 dark:bg-gray-800" />
                                <p class="mt-1 text-[10px] text-gray-500">/ 10000 ({{ __('pos.discount_bp_hint') }})</p>
                            </div>
                        @endif
                    @else
                        <p class="rounded-md bg-emerald-50 p-2 text-xs text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">
                            {{ __('pos.discount_staff_fixed_hint') }}
                        </p>
                    @endif

                    {{-- Approver --}}
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-200">
                            {{ __('pos.discount_approver') }}
                        </label>
                        <select wire:model="approverStaffId" wire:loading.attr="disabled" wire:target="submit"
                            class="mt-1 w-full rounded-md border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800">
                            <option value="">—</option>
                            @foreach ($this->approverOptions as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['name'] }} (Lv{{ $opt['level'] }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-200">
                            {{ __('pos.discount_pin') }}
                        </label>
                        <input type="password" inputmode="numeric" wire:model.debounce.500ms="approverPin" wire:loading.attr="disabled" wire:target="submit"
                            class="mt-1 w-full rounded-md border-gray-300 px-2 py-1.5 text-sm tracking-widest dark:border-gray-600 dark:bg-gray-800" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-200">
                            {{ __('pos.discount_reason') }}
                        </label>
                        <input type="text" wire:model.debounce.500ms="reason" maxlength="255" wire:loading.attr="disabled" wire:target="submit"
                            class="mt-1 w-full rounded-md border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800" />
                    </div>
                </div>

                <div class="flex shrink-0 items-center justify-between gap-2 border-t-2 border-blue-300 bg-white px-3 py-2.5 dark:border-blue-700 dark:bg-slate-900">
                    <button type="button" wire:click="closeModal" @disabled($locked)
                        wire:loading.attr="disabled"
                        wire:target="closeModal,submit"
                        class="rounded-md border-2 border-slate-300 bg-white px-3 py-2 text-xs font-extrabold text-slate-800 hover:bg-slate-100 disabled:opacity-50 dark:bg-slate-800 dark:text-gray-100">
                        {{ __('rad_table.cloture_cancel') }}
                    </button>
                    <button type="button" wire:click="submit" @disabled($locked)
                        x-on:click="optimisticClosing = true"
                        wire:loading.attr="disabled"
                        wire:target="submit"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-extrabold text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                        <span wire:loading.remove wire:target="submit">{{ __('pos.discount_apply') }}</span>
                        <span wire:loading wire:target="submit">{{ __('pos.ui_working') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
