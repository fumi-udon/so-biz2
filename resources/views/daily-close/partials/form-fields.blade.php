@php
    $shift = (string) ($data['shift'] ?? 'dinner');
    $inputClass = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-sm tabular-nums text-gray-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
    $labelClass = 'mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300';
    $errClass = 'mt-1 text-xs font-medium text-rose-600 dark:text-rose-400';
    $fondReadonlyClass = 'w-full max-w-[7.5rem] cursor-not-allowed rounded-md border border-dashed border-gray-200 bg-gray-50/80 px-2 py-1 text-right font-mono text-sm font-semibold tabular-nums text-gray-600 focus:border-gray-200 focus:outline-none focus:ring-0 dark:border-gray-600 dark:bg-gray-800/50 dark:text-gray-300';
@endphp

@if ($shift === 'lunch')
    <section class="mb-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Midi — Saisie caisse</h2>
            <div class="flex flex-col items-stretch sm:items-end sm:text-right">
                <span class="mb-0.5 text-[10px] font-medium text-gray-500 dark:text-gray-400">Fond de caisse (exclusif · lecture seule)</span>
                <input type="text" readonly wire:model="data.lunch_montant_initial" title="Paramètre magasin — non inclus dans la saisie calculée" class="{{ $fondReadonlyClass }}" tabindex="-1" aria-readonly="true">
            </div>
        </div>
        <div class="grid grid-cols-1 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="{{ $labelClass }}">Ventes POS (Midi)*</label>
                <input type="text" inputmode="decimal" wire:model.live.debounce.500ms="data.lunch_recettes" class="{{ $inputClass }}">
                @error('data.lunch_recettes')
                    <p class="{{ $errClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Pourboire déclaré*</label>
                <input type="text" inputmode="decimal" wire:model.live.debounce.500ms="data.lunch_chips" class="{{ $inputClass }}">
                @error('data.lunch_chips')
                    <p class="{{ $errClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Espèces*</label>
                <input type="text" inputmode="decimal" wire:model.live.debounce.500ms="data.lunch_cash" class="{{ $inputClass }}">
                @error('data.lunch_cash')<p class="{{ $errClass }}">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Chèque*</label>
                <input type="text" inputmode="decimal" wire:model.live.debounce.500ms="data.lunch_cheque" class="{{ $inputClass }}">
                @error('data.lunch_cheque')<p class="{{ $errClass }}">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Carte*</label>
                <input type="text" inputmode="decimal" wire:model.live.debounce.500ms="data.lunch_carte" class="{{ $inputClass }}">
                @error('data.lunch_carte')<p class="{{ $errClass }}">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>
@else
    <section class="mb-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Soir — Saisie caisse</h2>
            <div class="flex flex-col items-stretch sm:items-end sm:text-right">
                <span class="mb-0.5 text-[10px] font-medium text-gray-500 dark:text-gray-400">Fond de caisse (exclusif · lecture seule)</span>
                <input type="text" readonly wire:model="data.dinner_montant_initial" title="Paramètre magasin — non inclus dans la saisie calculée" class="{{ $fondReadonlyClass }}" tabindex="-1" aria-readonly="true">
            </div>
        </div>
        <div class="grid grid-cols-1 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="{{ $labelClass }}">Ventes POS (Soir)*</label>
                <input type="text" inputmode="decimal" wire:model.live.debounce.500ms="data.dinner_recettes" class="{{ $inputClass }}">
                @error('data.dinner_recettes')<p class="{{ $errClass }}">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Pourboire déclaré*</label>
                <input type="text" inputmode="decimal" wire:model.live.debounce.500ms="data.dinner_chips" class="{{ $inputClass }}">
                @error('data.dinner_chips')<p class="{{ $errClass }}">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Espèces*</label>
                <input type="text" inputmode="decimal" wire:model.live.debounce.500ms="data.dinner_cash" class="{{ $inputClass }}">
                @error('data.dinner_cash')<p class="{{ $errClass }}">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Chèque*</label>
                <input type="text" inputmode="decimal" wire:model.live.debounce.500ms="data.dinner_cheque" class="{{ $inputClass }}">
                @error('data.dinner_cheque')<p class="{{ $errClass }}">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">Carte*</label>
                <input type="text" inputmode="decimal" wire:model.live.debounce.500ms="data.dinner_carte" class="{{ $inputClass }}">
                @error('data.dinner_carte')<p class="{{ $errClass }}">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>
@endif

<div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    @include('daily-close.partials.health-gauge')

    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
    <button
        type="button"
        wire:click="calculate"
        wire:loading.attr="disabled"
        wire:target="calculate"
        class="flex-1 rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50 dark:bg-indigo-500 dark:hover:bg-indigo-600"
    >
        <span wire:loading.remove wire:target="calculate">Clôturer le service</span>
        <span wire:loading wire:target="calculate">Envoi…</span>
    </button>
    <!-- <button
        type="button"
        wire:click="reopenSessionGate"
        class="rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
    >
        Changer service / responsable
    </button> -->
    </div>
</div>
