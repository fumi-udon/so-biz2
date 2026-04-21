<div
    class="fixed inset-0 z-[340] h-[100dvh] max-h-[100dvh] overflow-hidden overscroll-none bg-white text-gray-950 dark:bg-slate-950 dark:text-white"
    x-data="{ isPrinting: false }"
>
    <div class="flex h-full min-h-0 flex-col">
        <header class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
            <button
                type="button"
                wire:click="closePreview"
                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-500 hover:bg-gray-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
            >
                ← {{ __('pos.add_back') }}
            </button>

            <div class="text-center">
                <h3 class="text-sm font-extrabold tracking-wide text-gray-950 dark:text-white">{{ $this->viewData['title'] }}</h3>
                <p class="text-[11px] text-gray-600 dark:text-slate-300">{{ $this->viewData['table_label'] }}</p>
            </div>

            <button
                type="button"
                x-bind:disabled="isPrinting"
                x-on:click="isPrinting = true; setTimeout(() => { isPrinting = false }, 3000)"
                wire:click="printFromPreview"
                wire:loading.attr="disabled"
                wire:target="printFromPreview"
                class="inline-flex min-h-10 items-center rounded-md border border-emerald-800 bg-emerald-600 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="printFromPreview">{{ $this->viewData['title'] }}</span>
                <span wire:loading wire:target="printFromPreview">{{ __('pos.ui_working') }}</span>
            </button>
        </header>

        <main class="min-h-0 flex-1 overflow-y-auto overscroll-none p-3">
            <div class="mx-auto w-full max-w-md rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="mb-2 text-center">
                    <p class="text-sm font-black text-gray-950 dark:text-white">{{ $this->viewData['shop_name'] }}</p>
                    <p class="text-[11px] text-gray-600 dark:text-slate-300">{{ $this->viewData['printed_at'] }}</p>
                </div>

                <div class="space-y-1 border-y border-dashed border-gray-300 py-2 dark:border-slate-600">
                    @foreach ($this->viewData['lines'] as $line)
                        <div class="grid grid-cols-[auto,1fr,auto] items-start gap-2 text-xs">
                            <span class="font-semibold text-gray-900 dark:text-slate-200">{{ $line['qty'] }}x</span>
                            <span class="truncate text-gray-900 dark:text-slate-100">{{ $line['name'] }}</span>
                            <span class="tabular-nums font-semibold text-gray-900 dark:text-slate-100">{{ $this->formatMinor((int) $line['amount_minor']) }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-2 space-y-1 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-700 dark:text-slate-300">{{ __('pos.subtotal') }}</span>
                        <span class="tabular-nums font-semibold text-gray-900 dark:text-slate-100">{{ $this->formatMinor((int) $this->viewData['subtotal_minor']) }}</span>
                    </div>
                    <div class="flex items-center justify-between border-t border-gray-200 pt-1 text-sm dark:border-slate-700">
                        <span class="font-bold text-gray-950 dark:text-white">{{ __('pos.subtotal') }}</span>
                        <span class="tabular-nums font-black text-gray-950 dark:text-white">{{ $this->formatMinor((int) $this->viewData['total_minor']) }}</span>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
