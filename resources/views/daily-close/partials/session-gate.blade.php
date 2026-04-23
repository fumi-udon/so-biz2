<div
    class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-950/50 p-3 backdrop-blur-sm dark:bg-black/60"
    role="dialog"
    aria-modal="true"
    aria-labelledby="dc-gate-title"
>
    <div class="w-full max-w-2xl rounded-2xl border border-gray-200 bg-white p-4 shadow-2xl dark:border-gray-600 dark:bg-gray-900 sm:p-6">
        <p id="dc-gate-title" class="mb-1 text-center font-['Press_Start_2P'] text-[10px] leading-snug text-gray-950 dark:text-white sm:text-xs">IDENTIFICATION</p>
        <p class="mb-4 text-center text-sm text-gray-700 dark:text-gray-300">Choisissez le service, le responsable et le code PIN (4 chiffres).</p>

        <div class="grid gap-y-3">
            <div>
                <p class="mb-2 text-center font-['Press_Start_2P'] text-[8px] uppercase tracking-wide text-gray-500 dark:text-gray-400 sm:text-[9px]">Service</p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label
                        class="group relative flex cursor-pointer flex-col items-center gap-2 rounded-2xl border-2 border-amber-300/90 bg-gradient-to-br from-amber-100 via-orange-50 to-yellow-50 p-4 text-center shadow-md transition-shadow has-[:checked]:ring-4 has-[:checked]:ring-amber-400 dark:border-amber-600/70 dark:from-amber-950/50 dark:via-orange-950/40 dark:to-yellow-950/35 dark:has-[:checked]:ring-amber-400"
                    >
                        <input type="radio" wire:model.live="gateShift" value="lunch" class="sr-only">
                        <span class="font-['Press_Start_2P'] text-[8px] leading-tight text-amber-900 dark:text-amber-100 sm:text-[9px]">DEJEUNER</span>
                        <span class="text-4xl leading-none" aria-hidden="true">🌞</span>
                        <span class="text-sm font-semibold text-gray-950 dark:text-white">Midi</span>
                    </label>
                    <label
                        class="group relative flex cursor-pointer flex-col items-center gap-2 rounded-2xl border-2 border-indigo-400/80 bg-gradient-to-br from-sky-100 via-indigo-100 to-violet-200 p-4 text-center shadow-md transition-shadow has-[:checked]:ring-4 has-[:checked]:ring-indigo-400 dark:border-indigo-500/60 dark:from-sky-950/45 dark:via-indigo-950/45 dark:to-violet-950/50 dark:has-[:checked]:ring-indigo-400"
                    >
                        <input type="radio" wire:model.live="gateShift" value="dinner" class="sr-only">
                        <span class="font-['Press_Start_2P'] text-[8px] leading-tight text-indigo-950 dark:text-indigo-100 sm:text-[9px]">DINER</span>
                        <span class="text-4xl leading-none" aria-hidden="true">🌃</span>
                        <span class="text-sm font-semibold text-gray-950 dark:text-white">Soir</span>
                    </label>
                </div>
                @error('gateShift')
                    <p class="mt-2 text-center text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="dc-gate-staff" class="mb-1 block text-center font-['Press_Start_2P'] text-[8px] uppercase tracking-wide text-gray-500 dark:text-gray-400 sm:text-[9px]">Responsable</label>
                <div class="relative">
                    <select
                        id="dc-gate-staff"
                        wire:model.live="gateStaffId"
                        class="block w-full appearance-none rounded-xl border border-gray-300 bg-white px-3 py-2.5 pr-10 text-sm font-medium text-gray-950 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
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
                    wire:model.defer="gatePinInput"
                    maxlength="4"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    placeholder="••••"
                    class="block w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-center font-mono text-xl font-semibold tracking-[0.35em] text-gray-950 tabular-nums placeholder:text-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500"
                >
                @error('gatePinInput')
                    <p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-1 grid grid-cols-1 gap-2 sm:grid-cols-2">
                <button
                    type="button"
                    wire:click="closeGateAndGoHome"
                    class="rounded-xl border-2 border-gray-500 bg-gradient-to-b from-gray-100 to-gray-300 px-3 py-3 text-sm font-bold text-gray-950 shadow-[0_4px_0_0_#6b7280] transition hover:brightness-105 active:translate-y-1 active:shadow-[0_2px_0_0_#6b7280] dark:border-gray-500 dark:from-gray-700 dark:to-gray-800 dark:text-gray-100 dark:shadow-[0_4px_0_0_#374151] dark:active:shadow-[0_2px_0_0_#374151]"
                >
                    Retour
                </button>
                <button
                    type="button"
                    wire:click="confirmCloseSessionGate"
                    wire:loading.attr="disabled"
                    wire:target="confirmCloseSessionGate"
                    class="rounded-xl bg-gradient-to-b from-indigo-500 to-indigo-700 px-3 py-3 text-sm font-bold text-white shadow-[0_4px_0_0_#312e81] transition hover:brightness-110 disabled:opacity-60 active:translate-y-1 active:shadow-[0_2px_0_0_#312e81] dark:from-indigo-600 dark:to-indigo-800 dark:shadow-[0_4px_0_0_#1e1b4b] dark:active:shadow-[0_2px_0_0_#1e1b4b]"
                >
                    <span wire:loading.remove wire:target="confirmCloseSessionGate">Valider</span>
                    <span wire:loading wire:target="confirmCloseSessionGate">…</span>
                </button>
            </div>
        </div>
    </div>
</div>
