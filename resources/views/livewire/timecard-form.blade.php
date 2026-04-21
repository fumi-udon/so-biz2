@php
    $timecardNavStaffs = \App\Models\Staff::query()->where('is_active', true)->orderBy('name')->get();
@endphp
<div class="min-h-0" x-data="{ openMyPageModal: false }" wire:poll.60s>
    <header class="border-b-4 border-black bg-gradient-to-r from-slate-900 via-indigo-900 to-slate-900 text-white">
        <div class="mx-auto flex w-full max-w-5xl items-center justify-between px-4 py-2 sm:px-4">
            <a href="{{ route('home') }}" class="text-base font-black tracking-[0.18em] text-amber-300 drop-shadow">{{ config('app.name', 'SOYA BIZ') }}</a>
            <nav class="flex items-center gap-2 text-sm">
                <a href="{{ route('home') }}" class="rounded-md border border-white/30 bg-white/10 px-3 py-1.5 font-bold text-white transition hover:bg-white/20">Accueil</a>
                <a href="{{ route('timecard.index') }}" class="rounded-md border-2 border-black bg-amber-400 px-3 py-1.5 font-black text-black shadow-[0_4px_0_0_rgba(0,0,0,1)]">Pointage</a>
                <button
                    type="button"
                    @click="openMyPageModal = true"
                    class="rounded-md border border-white/30 bg-white/10 px-3 py-1.5 font-bold text-white transition hover:bg-white/20"
                >
                    Mon espace
                </button>
            </nav>
        </div>
    </header>

    <div class="mx-auto w-full max-w-4xl px-4 py-2 sm:px-4 lg:px-8">
        <div class="space-y-2">
    <section class="rounded-2xl border-4 border-black bg-gradient-to-r from-yellow-300 via-amber-400 to-orange-500 p-3 text-center shadow-[0_10px_0_0_rgba(0,0,0,1)]">
        <p class="mb-1 text-sm font-black uppercase tracking-[0.2em] text-black">TIME ATTACK</p>
        <h1 class="mb-1 text-3xl font-black tracking-widest text-black">BATAILLE DE POINTAGE</h1>
        <p class="text-sm text-gray-700 dark:text-gray-800">
            Date de service
            <time class="font-mono font-semibold text-gray-900" datetime="{{ $targetBusinessDate->toDateString() }}">
                {{ $targetBusinessDate->format('Y/m/d') }}
            </time>
        </p>
    </section>

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
                            @if (! $lunchInDone && $punchInOpensAt['lunch'] !== null)
                                {{-- 案内帯: 打刻ウィンドウがまだ開いていない --}}
                                <div class="flex min-h-[3.75rem] w-full items-center justify-center gap-2 rounded-xl border-2 border-slate-600 bg-slate-800 px-3 py-3 text-center text-sm font-semibold leading-snug text-slate-200" role="status" aria-live="polite">
                                    <!-- ⏰ Prochain pointage possible à <span class="font-mono font-black text-amber-300">{{ $punchInOpensAt['lunch'] }}</span> -->
                                    No entry data available
                                </div>
                            @else
                                <button
                                    type="button"
                                    wire:click="punch('lunch_in')"
                                    wire:loading.attr="disabled"
                                    @disabled($this->isPunchDisabled('lunch_in'))
                                    class="inline-flex w-full items-center justify-center rounded-xl border-2 border-black px-4 py-4 text-lg font-black tracking-widest transition focus:outline-none focus:ring-2 {{ $this->isPunchDisabled('lunch_in') ? 'cursor-not-allowed border-gray-400 bg-gray-300 px-2 py-1.5 text-xs font-semibold tracking-normal text-gray-600 opacity-90' : 'bg-yellow-300 text-black shadow-[0_8px_0_0_rgba(0,0,0,1)] hover:bg-yellow-200 focus:ring-yellow-300/60 active:translate-y-2 active:shadow-none' }}"
                                >
                                    {{ $lunchInDone ? '✅ Entree dejeuner deja pointee' : 'Entree dejeuner' }}
                                </button>
                            @endif
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
                            @if (! $dinnerInDone && $punchInOpensAt['dinner'] !== null)
                                {{-- 案内帯: 打刻ウィンドウがまだ開いていない --}}
                                <div class="flex min-h-[3.75rem] w-full items-center justify-center gap-2 rounded-xl border-2 border-slate-600 bg-slate-800 px-3 py-3 text-center text-sm font-semibold leading-snug text-slate-200" role="status" aria-live="polite">
                                    <!-- ⏰ Prochain pointage possible à <span class="font-mono font-black text-amber-300">{{ $punchInOpensAt['dinner'] }}</span> -->
                                    No entry data available
                                </div>
                            @else
                                <button
                                    type="button"
                                    wire:click="punch('dinner_in')"
                                    wire:loading.attr="disabled"
                                    @disabled($this->isPunchDisabled('dinner_in'))
                                    class="inline-flex w-full items-center justify-center rounded-xl border-2 border-black px-4 py-4 text-lg font-black tracking-widest transition focus:outline-none focus:ring-2 {{ $this->isPunchDisabled('dinner_in') ? 'cursor-not-allowed border-gray-400 bg-gray-300 px-2 py-1.5 text-xs font-semibold tracking-normal text-gray-600 opacity-90' : 'bg-cyan-300 text-black shadow-[0_8px_0_0_rgba(0,0,0,1)] hover:bg-cyan-200 focus:ring-cyan-300/60 active:translate-y-2 active:shadow-none' }}"
                                >
                                    {{ $dinnerInDone ? '✅ Entree diner deja pointee' : 'Entree diner' }}
                                </button>
                            @endif
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
            <div x-show="showExtra" x-cloak x-transition class="mt-2">
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

    @if ($showTipAwardModal)
        <div
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/65 p-4 dark:bg-black/80"
            wire:key="tip-award-overlay"
            role="dialog"
            aria-modal="true"
            aria-labelledby="tip-award-title"
        >
            <div
                class="w-full max-w-md rounded-2xl border-2 border-emerald-500/70 bg-slate-900 p-5 text-center shadow-2xl ring-1 ring-emerald-400/25 dark:border-emerald-400/50 dark:bg-slate-950 dark:ring-emerald-500/30"
                wire:click.stop
            >
                <p id="tip-award-title" class="mb-1 text-xs font-black uppercase tracking-[0.28em] text-emerald-300">
                    Pourboire
                </p>
                <p class="mb-3 text-lg font-extrabold leading-snug text-white sm:text-xl">
                    Le pourboire a été attribué.
                </p>
                <p class="mb-4 text-sm font-medium text-slate-300">
                    Redirection automatique vers Mon espace…
                </p>
                <button
                    type="button"
                    wire:click="dismissTipAwardToMypage"
                    class="w-full rounded-xl border-2 border-emerald-400/80 bg-emerald-500 px-4 py-3 text-sm font-black text-slate-950 shadow-[0_4px_0_0_rgba(6,78,59,0.85)] transition hover:bg-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-300/60 active:translate-y-0.5 active:shadow-none"
                >
                    Continuer vers Mon espace
                </button>
            </div>
        </div>
    @endif

    @if ($showLateClockInModal)
        <div
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/65 p-4 dark:bg-black/80"
            wire:click.self="dismissLateModalToMypage"
            wire:key="late-clock-in-overlay"
            role="dialog"
            aria-modal="true"
            aria-labelledby="late-clock-in-title"
        >
            <div
                class="w-full max-w-md rounded-2xl border-2 border-rose-600/50 bg-gradient-to-br from-rose-950 via-slate-900 to-slate-950 p-5 text-center shadow-2xl ring-1 ring-rose-500/20 dark:border-rose-500/40 dark:from-rose-950 dark:via-slate-950 dark:to-black"
                wire:click.stop
            >
                <p id="late-clock-in-title" class="mb-2 text-lg font-black text-white">
                    Retard enregistré
                </p>
                <p class="mb-1 text-sm font-semibold text-rose-100/95">
                    @if (($lateClockInMinutes ?? 0) > 0)
                        +{{ $lateClockInMinutes }} min par rapport au créneau prévu.
                    @else
                        Votre pointage d’entrée a été enregistré avec un retard.
                    @endif
                </p>
                <p class="mb-4 text-xs font-medium text-slate-300">
                    Le pourboire automatique pour ce service ne s’applique pas.
                </p>
                <button
                    type="button"
                    wire:click="dismissLateModalToMypage"
                    class="w-full rounded-xl border-2 border-slate-500/80 bg-slate-700 px-4 py-3 text-sm font-extrabold text-white shadow-[0_4px_0_0_rgba(15,23,42,0.9)] transition hover:bg-slate-600 focus:outline-none focus:ring-2 focus:ring-rose-300/50 active:translate-y-0.5 active:shadow-none"
                >
                    Mon espace (retour)
                </button>
            </div>
        </div>
    @endif

    @if ($showPunchCompleteModal)
        <div
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4 dark:bg-black/80"
            wire:key="punch-complete-overlay"
        >
            <div class="w-full max-w-md" wire:click.stop>
                <div class="rounded-xl border-4 border-black bg-gradient-to-r from-emerald-400 via-cyan-300 to-sky-400 p-4 text-black shadow-[0_8px_0_0_rgba(0,0,0,1)]">
                    <p class="mb-1 text-center text-sm font-black tracking-[0.2em] text-slate-800">TIMECARD COMPLETE</p>
                    <p class="mb-1 text-center text-xl font-black tracking-widest">🎉 Pointage termine Bravo merci 🎉</p>
                    <p class="mb-2 text-center text-sm font-bold">{{ $punchCompleteLabel ?? 'SHIFT OUT' }}</p>
                    <p class="mb-3 text-center text-sm font-semibold">Merci pour votre travail aujourd'hui !</p>
                    <button
                        type="button"
                        wire:click="closePunchCompleteModal"
                        class="w-full rounded-lg border-2 border-black bg-white px-3 py-2 text-sm font-black text-gray-900 shadow-[0_4px_0_0_rgba(0,0,0,1)] active:translate-y-1 active:shadow-none dark:bg-white dark:text-gray-900"
                    >
                        OK
                    </button>
                </div>
            </div>
        </div>
    @endif
        </div>
    </div>

    <footer class="py-2 text-center text-xs text-gray-500 dark:text-gray-400">
        {{ config('app.name') }}
    </footer>

    <div
        x-show="openMyPageModal"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-[110] flex items-center justify-center bg-black/70 p-4"
        @click.self="openMyPageModal = false"
    >
        <x-staff-pin-auth-card
            title="Connexion Mon espace"
            subtitle="Selectionnez le personnel et saisissez le PIN."
            note="本人確認"
        >
            <form method="POST" action="{{ route('mypage.open') }}" class="space-y-2">
                @csrf
                <div>
                    <label for="mypage_staff_id_tc" class="mb-1 block text-sm font-black tracking-wide text-gray-800 dark:text-gray-200">Personnel</label>
                    <div class="relative">
                        <select
                            id="mypage_staff_id_tc"
                            name="staff_id"
                            required
                            class="block w-full appearance-none rounded-lg border-2 border-black bg-white px-3 py-2.5 pr-10 text-sm font-semibold text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:bg-gray-900 dark:text-gray-100"
                        >
                            <option value="">Veuillez selectionner</option>
                            @foreach ($timecardNavStaffs as $staff)
                                <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-700 dark:text-gray-300">▾</span>
                    </div>
                </div>
                <div>
                    <label for="mypage_pin_tc" class="mb-1 block text-sm font-black tracking-wide text-gray-800 dark:text-gray-200">Code PIN</label>
                    <input
                        id="mypage_pin_tc"
                        type="password"
                        name="pin_code"
                        required
                        maxlength="4"
                        pattern="[0-9]*"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        class="block w-full rounded-lg border-2 border-black bg-white px-3 py-2.5 text-center font-mono text-lg font-bold tracking-[0.3em] text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:bg-gray-900 dark:text-gray-100"
                        placeholder="••••"
                    >
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" @click="openMyPageModal = false" class="rounded-lg border-2 border-gray-300 bg-white px-3 py-2 text-sm font-bold text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                        Fermer
                    </button>
                    <button type="submit" class="rounded-lg border-2 border-black bg-emerald-400 px-3 py-2 text-sm font-black text-black shadow-[0_4px_0_0_rgba(0,0,0,1)] active:translate-y-1 active:shadow-none">
                        Entrer
                    </button>
                </div>
            </form>
        </x-staff-pin-auth-card>
    </div>
</div>
