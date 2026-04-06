<div class="space-y-3">
    <header class="rounded-2xl border-4 border-black bg-gradient-to-r from-yellow-300 via-amber-400 to-orange-500 p-4 text-center shadow-[0_10px_0_0_rgba(0,0,0,1)]">
        <p class="mb-1 text-sm font-black uppercase tracking-[0.2em] text-black">TIME ATTACK</p>
        <h1 class="mb-1 text-3xl font-black tracking-widest text-black">BATAILLE DE POINTAGE</h1>
        <p class="text-sm text-gray-700">
            Date de service
            <time class="font-mono font-semibold text-gray-900" datetime="{{ $targetBusinessDate->toDateString() }}">
                {{ $targetBusinessDate->format('Y/m/d') }}
            </time>
        </p>
    </header>

    @if ($bannerSuccess)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-sm font-semibold text-emerald-700" role="status">
            {{ $bannerSuccess }}
        </div>
    @endif

    @if ($bannerError)
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700" role="alert">
            {{ $bannerError }}
        </div>
    @endif

    @if ($staffOptions === [])
        <div class="rounded-xl border-2 border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
            Aucun personnel actif n'est enregistre.
        </div>
    @elseif ($step === 1)
        <div class="rounded-2xl border-4 border-black bg-white p-5 shadow-[0_8px_0_0_rgba(0,0,0,1)]">
            <p class="mb-3 text-sm font-black uppercase tracking-widest text-indigo-700">Etape 1 - Verification d'identite</p>
            <div class="mb-3">
                <label for="tc_staff" class="mb-1 block text-sm font-medium text-gray-700">Personnel</label>
                <div class="relative">
                    <select
                        id="tc_staff"
                        wire:model.live="selectedStaffId"
                        class="block w-full !appearance-none rounded-lg border border-gray-300 bg-white !bg-none px-3 py-3 pr-10 text-base text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                    >
                        <option value="">Veuillez selectionner</option>
                        @foreach ($staffOptions as $opt)
                            <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                        @endforeach
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-500">▾</span>
                </div>
                @error('selectedStaffId')
                    <div class="mt-1 text-sm text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-4">
                <label for="tc_pin" class="mb-1 block text-sm font-medium text-gray-700">PIN (4 chiffres)</label>
                <input
                    id="tc_pin"
                    type="password"
                    wire:model.live.debounce.500ms="pinCode"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="4"
                    class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-3 text-center font-mono text-lg text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                    placeholder="••••"
                />
                @error('pinCode')
                    <div class="mt-1 text-sm text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <button
                type="button"
                wire:click="authenticate"
                wire:loading.attr="disabled"
                class="inline-flex w-full items-center justify-center rounded-xl border-2 border-black bg-indigo-500 px-4 py-4 text-lg font-black tracking-widest text-white shadow-[0_8px_0_0_rgba(0,0,0,1)] transition hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 active:translate-y-2 active:shadow-none disabled:cursor-not-allowed disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="authenticate">Continuer</span>
                <span wire:loading wire:target="authenticate">Verification...</span>
            </button>
        </div>
    @else
        <div class="rounded-2xl border-4 border-black bg-gradient-to-r from-cyan-200 to-blue-200 px-4 py-4 text-center shadow-[0_8px_0_0_rgba(0,0,0,1)]">
            @if ($hasShiftToday)
                <p class="text-xl font-black text-sky-950">
                    Bon courage, {{ $authenticatedStaffName }}
                </p>
            @else
                <p class="text-xl font-black text-sky-950">
                    Bonjour, {{ $authenticatedStaffName }}
                </p>
            @endif
            <button type="button" wire:click="backToAuth" class="mt-2 text-sm font-medium text-sky-700 underline underline-offset-2">
                Changer de personnel
            </button>
        </div>

        {{-- hasShiftToday = poste prevu ce jour (fixed_shifts / pointage en cours) ; distinct de la ligne DB `attendance` qui peut etre creee au 1er pointage --}}
        @if ($hasShiftToday)
        @php
            $s = $shiftState;
            $showLunchBlock = $s['lunch_scheduled'] || $s['lunch_in'];
            $showDinnerBlock = $s['dinner_scheduled'] || $s['dinner_in'];
            $canExtraLunch = ! $s['lunch_scheduled'] && ! $s['lunch_in'];
            $canExtraDinner = ! $s['dinner_scheduled'] && ! $s['dinner_in'];
            $extraAvailable = $canExtraLunch || $canExtraDinner;
            $lunchInDone = (bool) ($s['lunch_in'] ?? false);
            $lunchOutDone = (bool) ($s['lunch_out'] ?? false);
            $dinnerInDone = (bool) ($s['dinner_in'] ?? false);
            $dinnerOutDone = (bool) ($s['dinner_out'] ?? false);
        @endphp

        <div class="mx-auto grid w-full max-w-5xl grid-cols-1 gap-3 lg:grid-cols-2">
            @if ($showLunchBlock)
                <section class="rounded-2xl border-4 border-black bg-gradient-to-br from-orange-400 via-red-500 to-rose-600 p-4 text-white shadow-[0_10px_0_0_rgba(0,0,0,1)]" aria-label="Pointage dejeuner">
                    <p class="mb-1 text-sm font-black uppercase tracking-[0.2em] text-orange-100">ROUND 1</p>
                    <p class="mb-3 text-center text-lg font-black tracking-widest text-white">LUNCH SIDE</p>
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                        <div>
                            <button
                                type="button"
                                wire:click="punch('lunch_in')"
                                wire:loading.attr="disabled"
                                @disabled($this->isPunchDisabled('lunch_in'))
                                class="inline-flex w-full items-center justify-center rounded-xl border-2 border-black px-4 py-4 text-lg font-black tracking-widest transition focus:outline-none focus:ring-2 {{ $this->isPunchDisabled('lunch_in') ? 'cursor-not-allowed border-gray-400 bg-gray-300 px-2 py-1.5 text-xs font-semibold tracking-normal text-gray-600 opacity-90' : 'bg-yellow-300 text-black shadow-[0_8px_0_0_rgba(0,0,0,1)] hover:bg-yellow-200 focus:ring-yellow-300/60 active:translate-y-2 active:shadow-none' }}"
                            >
                                {{ $lunchInDone ? '✅ Entree dejeuner deja pointee' : 'Entree dejeuner' }}
                            </button>
                        </div>
                        <div>
                            <button
                                type="button"
                                wire:click="punch('lunch_out')"
                                wire:loading.attr="disabled"
                                @disabled($this->isPunchDisabled('lunch_out'))
                                class="inline-flex w-full items-center justify-center rounded-xl border-2 border-black px-4 py-4 text-lg font-black tracking-widest transition focus:outline-none focus:ring-2 {{ $this->isPunchDisabled('lunch_out') ? 'cursor-not-allowed border-gray-400 bg-gray-300 px-2 py-1.5 text-xs font-semibold tracking-normal text-gray-600 opacity-90' : 'bg-white text-red-700 shadow-[0_8px_0_0_rgba(0,0,0,1)] hover:bg-red-50 focus:ring-red-200/70 active:translate-y-2 active:shadow-none' }}"
                            >
                                {{ $lunchOutDone ? '✅ Sortie dejeuner deja pointee' : 'Sortie dejeuner' }}
                            </button>
                        </div>
                    </div>
                </section>
            @endif

            @if ($showDinnerBlock)
                <section class="rounded-2xl border-4 border-black bg-gradient-to-br from-indigo-700 via-blue-900 to-purple-900 p-4 text-white shadow-[0_10px_0_0_rgba(0,0,0,1)]" aria-label="Pointage diner">
                    <p class="mb-1 text-sm font-black uppercase tracking-[0.2em] text-indigo-100">ROUND 2</p>
                    <p class="mb-3 text-center text-lg font-black tracking-widest text-white">DINNER SIDE</p>
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                        <div>
                            <button
                                type="button"
                                wire:click="punch('dinner_in')"
                                wire:loading.attr="disabled"
                                @disabled($this->isPunchDisabled('dinner_in'))
                                class="inline-flex w-full items-center justify-center rounded-xl border-2 border-black px-4 py-4 text-lg font-black tracking-widest transition focus:outline-none focus:ring-2 {{ $this->isPunchDisabled('dinner_in') ? 'cursor-not-allowed border-gray-400 bg-gray-300 px-2 py-1.5 text-xs font-semibold tracking-normal text-gray-600 opacity-90' : 'bg-cyan-300 text-black shadow-[0_8px_0_0_rgba(0,0,0,1)] hover:bg-cyan-200 focus:ring-cyan-300/60 active:translate-y-2 active:shadow-none' }}"
                            >
                                {{ $dinnerInDone ? '✅ Entree diner deja pointee' : 'Entree diner' }}
                            </button>
                        </div>
                        <div>
                            <button
                                type="button"
                                wire:click="punch('dinner_out')"
                                wire:loading.attr="disabled"
                                @disabled($this->isPunchDisabled('dinner_out'))
                                class="inline-flex w-full items-center justify-center rounded-xl border-2 border-black px-4 py-4 text-lg font-black tracking-widest transition focus:outline-none focus:ring-2 {{ $this->isPunchDisabled('dinner_out') ? 'cursor-not-allowed border-gray-400 bg-gray-300 px-2 py-1.5 text-xs font-semibold tracking-normal text-gray-600 opacity-90' : 'bg-white text-indigo-800 shadow-[0_8px_0_0_rgba(0,0,0,1)] hover:bg-indigo-50 focus:ring-indigo-200/70 active:translate-y-2 active:shadow-none' }}"
                            >
                                {{ $dinnerOutDone ? '✅ Sortie diner deja pointee' : 'Sortie diner' }}
                            </button>
                        </div>
                    </div>
                </section>
            @endif
        </div>

        <div x-data="{ showExtra: false }" class="rounded-2xl border-2 border-amber-700 bg-amber-100 p-4">
            <button
                type="button"
                class="text-sm font-semibold text-amber-900 underline underline-offset-2"
                @click="showExtra = !showExtra"
            >
                ➕ Declarer une entree exceptionnelle (aide)
            </button>
            <div x-show="showExtra" x-collapse class="mt-3">
                        <p class="mb-2 text-sm font-bold text-amber-900">Entree exceptionnelle (aide)</p>
                @if ($extraAvailable)
                    @if ($this->allMainPunchesDisabled())
                        <p class="mb-3 text-sm font-semibold text-amber-800">
                            ⚠️ Aucun shift prevu aujourd'hui. Utilisez la demande d'aide ci-dessous.
                        </p>
                    @else
                        <p class="mb-3 text-sm font-semibold text-amber-800">
                            ⚠️ Les plages non planifiees ne peuvent pas etre pointees avec le bouton normal. Utilisez cette demande d'aide.
                        </p>
                    @endif
                    <div class="mb-3">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Shift exceptionnel</label>
                        <div class="relative">
                            <select wire:model.live="extraMeal" class="block w-full !appearance-none rounded-lg border border-gray-300 bg-white !bg-none px-3 py-3 pr-10 text-base text-gray-900 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/30">
                                @if ($canExtraLunch)
                                    <option value="lunch">Dejeuner (L)</option>
                                @endif
                                @if ($canExtraDinner)
                                    <option value="dinner">Diner (D)</option>
                                @endif
                            </select>
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-500">▾</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Motif (optionnel)</label>
                        <textarea wire:model.live.debounce.500ms="extraReason" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-base text-gray-900 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/30" rows="2" maxlength="500" placeholder="Ex.: remplacement d'absence"></textarea>
                    </div>
                    <button
                        type="button"
                        wire:click="submitExtraShift"
                        wire:loading.attr="disabled"
                        class="inline-flex w-full items-center justify-center rounded-lg border border-amber-600 bg-amber-500 px-4 py-4 text-lg font-semibold text-amber-950 transition hover:bg-amber-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="submitExtraShift">Pointer comme entree exceptionnelle</span>
                        <span wire:loading wire:target="submitExtraShift">Traitement...</span>
                    </button>
                    @error('extraMeal')
                        <div class="mt-2 text-sm text-rose-600">{{ $message }}</div>
                    @enderror
                @else
                    <p class="text-sm text-gray-600">
                        Aucune demande exceptionnelle n'est necessaire pour le moment.
                    </p>
                @endif
            </div>
        </div>
        @else
            <div class="space-y-3">
                <div
                    class="rounded border border-gray-300 bg-white px-3 py-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900"
                    role="alert"
                >
                    <p class="text-base font-bold uppercase tracking-wider text-gray-950 dark:text-white">NO SHIFT TODAY</p>
                    <p class="mt-1 text-xs font-medium uppercase tracking-wide text-gray-700 dark:text-gray-300">(AUCUN POSTE PRÉVU AUJOURD'HUI)</p>
                </div>

                <section
                    class="rounded-md border-2 border-blue-400/90 bg-gradient-to-br from-sky-50 via-blue-100 to-indigo-200 p-3 shadow-[0_2px_16px_rgba(0,127,255,0.12)] dark:border-blue-500/70 dark:bg-gradient-to-br dark:from-blue-950 dark:via-blue-900 dark:to-indigo-950"
                    aria-label="Weekly mission"
                >
                    <div class="mb-2 text-center">
                        <p class="text-sm font-extrabold uppercase tracking-[0.33em] text-blue-800 drop-shadow dark:text-yellow-200/90">WEEKLY MISSION</p>
                        <p class="mt-1 text-[11px] font-semibold uppercase tracking-wider text-blue-600/90 dark:text-cyan-300/80">(PLANNING DE LA SEMAINE · LUN–DIM)</p>
                    </div>
                    <div class="flex max-w-full flex-col gap-1.5">
                        @foreach ($weeklyMissionRows as $row)
                            <div
                                @class([
                                    'flex min-w-0 flex-col gap-1 border px-2 py-2 sm:flex-row sm:items-center sm:justify-between sm:gap-2',
                                    'border-blue-300 bg-sky-100/70 dark:border-blue-900/80 dark:bg-blue-950/70' => ! $row['is_today'],
                                    'border-yellow-400 bg-yellow-50 ring-2 ring-blue-300/30 dark:border-yellow-300 dark:bg-yellow-900/30 dark:ring-yellow-500/50' => $row['is_today'],
                                ])
                            >
                                <div class="min-w-0 shrink-0">
                                    <p class="font-mono text-[11px] font-black uppercase leading-tight text-orange-400 dark:text-orange-300">
                                        {{ $row['label_fr'] }}
                                        <span class="text-emerald-700 dark:text-emerald-200 font-extrabold">· {{ $row['date_label'] }}</span>
                                        @if ($row['is_today'])
                                            <span class="ml-1 inline-block rounded-sm border border-amber-500/80 bg-amber-500/60 px-1 py-0.5 text-[9px] font-black uppercase text-gray-950 dark:border-amber-400 dark:bg-amber-400/80 dark:text-gray-900">TODAY</span>
                                        @endif
                                    </p>
                                </div>
                                <div class="flex min-w-0 flex-1 flex-col gap-0.5 text-left sm:max-w-[60%] sm:text-right">
                                    <p class="break-words font-mono text-[10px]">
                                        <span class="text-orange-600 font-bold dark:text-orange-200">L</span>
                                        <span class="text-gray-950 font-extrabold dark:text-white tracking-tight">{{ $row['lunch'] }}</span>
                                    </p>
                                    <p class="break-words font-mono text-[10px]">
                                        <span class="text-orange-600 font-bold dark:text-orange-200">D</span>
                                        <span class="text-gray-950 font-extrabold dark:text-white tracking-tight">{{ $row['dinner'] }}</span>
                                    </p>
                                    <p class="break-words font-mono text-[9px] text-orange-700/90 font-semibold dark:text-orange-200/90">
                                        SCH <span class="text-gray-900 font-extrabold dark:text-white tracking-tight">{{ $row['scheduled_in'] }}</span>
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
        @endif
    @endif

    <x-filament::modal
        id="tip-result-modal"
        :close-by-clicking-away="true"
        :close-by-escaping="true"
        width="md"
        x-on:modal-closed.window="if ($event.detail.id === 'tip-result-modal') { $wire.declineTipAndRedirect() }"
    >
        @if ($tipModalState === 'WIN')
            <div class="rounded-xl bg-gradient-to-r from-amber-400 to-yellow-500 p-4 text-gray-950">
                <p class="mb-2 text-lg font-black">🎉 Bonjour 🎉</p>
                <p class="mb-4 text-sm font-bold">Arrivee a l'heure. Droit de demande de tip obtenu !</p>
                <button
                    type="button"
                    wire:click="applyForTip"
                    class="w-full rounded-lg bg-black/85 px-3 py-2 text-sm font-extrabold text-yellow-200"
                >
                    🪙 Get Your Tip!
                </button>
            </div>
        @elseif ($tipModalState === 'LOSE')
            <div class="rounded-xl bg-gradient-to-r from-red-800 to-gray-900 p-4 text-gray-100">
                <p class="mb-2 text-lg font-black">⚠️ Retard enregistré.</p>
                <button
                    type="button"
                    wire:click="declineTipAndRedirect"
                    class="w-full rounded-lg bg-gray-600 px-3 py-2 text-sm font-extrabold text-gray-100"
                >
                    My page (retour)
                </button>
            </div>
        @elseif ($tipModalState === 'SKIP')
            <div class="rounded-xl bg-gradient-to-r from-slate-600 to-slate-700 p-4 text-gray-100">
                <p class="mb-2 text-lg font-black">✅ Pointage enregistre !</p>
                <p class="mb-4 text-sm font-bold">Merci pour votre ponctualite !</p>
                <button
                    type="button"
                    wire:click="declineTipAndRedirect"
                    class="w-full rounded-lg bg-slate-500 px-3 py-2 text-sm font-extrabold text-gray-100 hover:bg-slate-400 active:scale-95 transition-all"
                >
                    ▶ Mon espace
                </button>
            </div>
        @endif
    </x-filament::modal>

    <x-filament::modal
        id="punch-complete-modal"
        :close-by-clicking-away="false"
        :close-by-escaping="true"
        width="md"
        x-on:modal-closed.window="if ($event.detail.id === 'punch-complete-modal') { $wire.closePunchCompleteModal() }"
    >
        <div class="rounded-xl border-4 border-black bg-gradient-to-r from-emerald-400 via-cyan-300 to-sky-400 p-4 text-black shadow-[0_8px_0_0_rgba(0,0,0,1)]">
            <p class="mb-1 text-center text-sm font-black tracking-[0.2em] text-slate-800">TIMECARD COMPLETE</p>
            <p class="mb-1 text-center text-xl font-black tracking-widest">🎉 Pointage termine Bravo merci 🎉</p>
            <p class="mb-2 text-center text-sm font-bold">{{ $punchCompleteLabel ?? 'SHIFT OUT' }}</p>
            <p class="mb-3 text-center text-sm font-semibold">Merci pour votre travail aujourd'hui !</p>
            <button
                type="button"
                x-on:click="$dispatch('close-modal', { id: 'punch-complete-modal' })"
                class="w-full rounded-lg border-2 border-black bg-white px-3 py-2 text-sm font-black text-slate-900 shadow-[0_4px_0_0_rgba(0,0,0,1)] active:translate-y-1 active:shadow-none"
            >
                OK
            </button>
        </div>
    </x-filament::modal>
</div>
