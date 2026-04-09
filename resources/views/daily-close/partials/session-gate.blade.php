<div
    class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/40 p-3 backdrop-blur-sm dark:bg-black/50"
    role="dialog"
    aria-modal="true"
    aria-labelledby="dc-gate-title"
>
    <div class="w-full max-w-lg rounded-xl border border-gray-200 bg-white p-4 shadow-lg dark:border-gray-700 dark:bg-gray-800 sm:p-5">
        <p id="dc-gate-title" class="mb-1 text-center text-base font-semibold text-gray-900 dark:text-gray-100">Identification responsable</p>
        <p class="mb-4 text-center text-sm text-gray-600 dark:text-gray-300">Choisissez le service, le responsable et le code PIN (4 chiffres).</p>

        <div class="grid gap-y-3">
            <div>
                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Service</p>
                <div class="flex flex-wrap gap-2">
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 has-[:checked]:ring-1 has-[:checked]:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 dark:has-[:checked]:border-indigo-400 dark:has-[:checked]:bg-indigo-950/40">
                        <input type="radio" wire:model.live="gateShift" value="lunch" class="size-4 border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-500 dark:bg-gray-800">
                        <span class="font-medium">Midi</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 has-[:checked]:ring-1 has-[:checked]:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 dark:has-[:checked]:border-indigo-400 dark:has-[:checked]:bg-indigo-950/40">
                        <input type="radio" wire:model.live="gateShift" value="dinner" class="size-4 border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-500 dark:bg-gray-800">
                        <span class="font-medium">Soir</span>
                    </label>
                </div>
                @error('gateShift')
                    <p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="dc-gate-staff" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Responsable</label>
                <div class="relative">
                    <select
                        id="dc-gate-staff"
                        wire:model.live="gateStaffId"
                        class="block w-full appearance-none rounded-lg border border-gray-300 bg-white px-3 py-2.5 pr-10 text-sm font-medium text-gray-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                    >
                        <option value="">— Sélectionner —</option>
                        @foreach ($this->staffOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-500 dark:text-gray-400">▾</span>
                </div>
                @error('gateStaffId')
                    <p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="dc-gate-pin" class="sr-only">Code PIN</label>
                <input
                    id="dc-gate-pin"
                    type="password"
                    wire:model.live.debounce.300ms="gatePinInput"
                    maxlength="4"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    placeholder="••••"
                    class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-3 text-center font-mono text-xl font-semibold tracking-[0.35em] text-gray-900 tabular-nums placeholder:text-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500"
                >
                @error('gatePinInput')
                    <p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-1 grid grid-cols-1 gap-2 sm:grid-cols-2">
                <button
                    type="button"
                    wire:click="closeGateAndGoHome"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    Retour
                </button>
                <button
                    type="button"
                    wire:click="confirmCloseSessionGate"
                    wire:loading.attr="disabled"
                    wire:target="confirmCloseSessionGate"
                    class="rounded-lg bg-indigo-600 px-3 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-60 dark:bg-indigo-500 dark:hover:bg-indigo-600"
                >
                    <span wire:loading.remove wire:target="confirmCloseSessionGate">Valider</span>
                    <span wire:loading wire:target="confirmCloseSessionGate">…</span>
                </button>
            </div>
        </div>
    </div>
</div>
