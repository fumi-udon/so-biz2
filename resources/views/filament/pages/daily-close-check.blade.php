<x-filament-panels::page>
    <div class="space-y-3">
        <x-filament::modal
            id="daily-close-session-gate"
            :close-button="false"
            :close-by-clicking-away="false"
            :close-by-escaping="false"
            :autofocus="false"
            alignment="center"
            width="3xl"
            icon="heroicon-o-lock-closed"
            icon-color="warning"
            heading="Validation lock"
            description="Choisis shift + responsable puis PIN (4 chiffres)."
            :sticky-footer="true"
            :extra-modal-window-attribute-bag="new \Illuminate\View\ComponentAttributeBag([
                'class' => 'daily-close-gate-window dark !bg-gradient-to-b !from-slate-950 !via-slate-900 !to-zinc-950 !text-slate-100 !shadow-2xl !ring-2 !ring-cyan-500/45',
                'data-daily-close-gate' => '1',
            ])"
        >
            {{-- 濃色ベース + シアン／アンバー／バイオレット／エメラルド／ローズ／イエロー（6色以上）・文字は常に薄いトーンで可読性固定 --}}
            <div class="flex flex-col gap-3 text-slate-200">
                <div class="flex justify-end">
                    <x-filament::button
                        type="button"
                        wire:click="closeGateAndGoTop"
                        color="gray"
                        size="xs"
                        outlined
                    >
                        Fermer
                    </x-filament::button>
                </div>

                @if ($errors->isNotEmpty())
                    <div
                        class="rounded-lg border-2 border-red-500 bg-red-950 px-3 py-3 shadow-lg ring-1 ring-red-400/50"
                        role="alert"
                        wire:key="daily-close-gate-errors"
                    >
                        @foreach ($errors->all() as $message)
                            <p class="text-center text-base font-black leading-snug text-red-100 sm:text-lg">
                                {{ $message }}
                            </p>
                        @endforeach
                    </div>
                @endif

                <div
                    class="rounded-lg border border-cyan-500/50 bg-slate-900/90 p-2.5 shadow-inner ring-1 ring-violet-500/25 sm:p-3"
                >
                    <div class="mb-2 flex items-center gap-1.5 sm:mb-2.5">
                        <x-filament::icon
                            icon="heroicon-o-bolt"
                            class="h-4 w-4 shrink-0 text-amber-400 sm:h-5 sm:w-5"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-bold text-amber-200 sm:text-sm">Shift</p>
                            <p class="mt-0.5 line-clamp-2 text-[10px] leading-snug text-slate-400 sm:text-xs">
                                Etat actif: cadre lumineux + badge "Actif".
                            </p>
                        </div>
                    </div>
                    {{-- スマホでも常に2列・約半分の高さで1画面に収める --}}
                    <div class="grid grid-cols-2 gap-1.5 sm:gap-2">
                        <button
                            type="button"
                            wire:click="$set('gateShift', 'lunch')"
                            @class([
                                'relative flex min-h-[3rem] flex-col items-center justify-center gap-0.5 rounded-lg border px-1 py-1 text-center shadow-sm transition duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 sm:min-h-[3.5rem] sm:px-1.5 sm:py-1.5',
                                'z-10 scale-[1.02] border-2 border-yellow-200 bg-gradient-to-br from-rose-500 via-rose-700 to-red-950 pt-2 shadow-[0_0_22px_rgba(250,204,21,0.55)] ring-2 ring-yellow-300 ring-offset-2 ring-offset-slate-900 sm:pt-2.5' => $this->gateShift === 'lunch',
                                'border border-slate-600 bg-slate-900/90 text-slate-500 hover:border-slate-500 hover:bg-slate-800' => $this->gateShift !== 'lunch',
                            ])
                        >
                            @if ($this->gateShift === 'lunch')
                                <span
                                    class="absolute -top-1.5 left-1/2 z-20 -translate-x-1/2 whitespace-nowrap rounded-full bg-yellow-300 px-1.5 py-px text-[8px] font-black leading-none text-red-900 shadow-sm sm:text-[9px]"
                                >Actif</span>
                            @endif
                            <x-filament::icon
                                icon="heroicon-o-sun"
                                @class([
                                    'h-4 w-4 shrink-0 sm:h-5 sm:w-5',
                                    'text-yellow-200 drop-shadow-sm' => $this->gateShift === 'lunch',
                                    'text-slate-500' => $this->gateShift !== 'lunch',
                                ])
                            />
                            <span
                                @class([
                                    'text-[11px] font-black leading-none sm:text-xs',
                                    'text-white drop-shadow-[0_1px_2px_rgba(0,0,0,0.9)]' => $this->gateShift === 'lunch',
                                    'text-slate-500' => $this->gateShift !== 'lunch',
                                ])
                            >Midi</span>
                            <span
                                @class([
                                    'text-[9px] font-semibold leading-tight',
                                    'text-amber-100' => $this->gateShift === 'lunch',
                                    'text-slate-600' => $this->gateShift !== 'lunch',
                                ])
                            >M</span>
                        </button>
                        <button
                            type="button"
                            wire:click="$set('gateShift', 'dinner')"
                            @class([
                                'relative flex min-h-[3rem] flex-col items-center justify-center gap-0.5 rounded-lg border px-1 py-1 text-center shadow-sm transition duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 sm:min-h-[3.5rem] sm:px-1.5 sm:py-1.5',
                                'z-10 scale-[1.02] border-2 border-yellow-200 bg-gradient-to-br from-rose-500 via-rose-700 to-red-950 pt-2 shadow-[0_0_22px_rgba(250,204,21,0.55)] ring-2 ring-yellow-300 ring-offset-2 ring-offset-slate-900 sm:pt-2.5' => $this->gateShift === 'dinner',
                                'border border-slate-600 bg-slate-900/90 text-slate-500 hover:border-slate-500 hover:bg-slate-800' => $this->gateShift !== 'dinner',
                            ])
                        >
                            @if ($this->gateShift === 'dinner')
                                <span
                                    class="absolute -top-1.5 left-1/2 z-20 -translate-x-1/2 whitespace-nowrap rounded-full bg-yellow-300 px-1.5 py-px text-[8px] font-black leading-none text-red-900 shadow-sm sm:text-[9px]"
                                >Actif</span>
                            @endif
                            <x-filament::icon
                                icon="heroicon-o-moon"
                                @class([
                                    'h-4 w-4 shrink-0 sm:h-5 sm:w-5',
                                    'text-yellow-200 drop-shadow-sm' => $this->gateShift === 'dinner',
                                    'text-slate-500' => $this->gateShift !== 'dinner',
                                ])
                            />
                            <span
                                @class([
                                    'text-[11px] font-black leading-none sm:text-xs',
                                    'text-white drop-shadow-[0_1px_2px_rgba(0,0,0,0.9)]' => $this->gateShift === 'dinner',
                                    'text-slate-500' => $this->gateShift !== 'dinner',
                                ])
                            >Soir</span>
                            <span
                                @class([
                                    'text-[9px] font-semibold leading-tight',
                                    'text-amber-100' => $this->gateShift === 'dinner',
                                    'text-slate-600' => $this->gateShift !== 'dinner',
                                ])
                            >S</span>
                        </button>
                    </div>
                </div>

                <div
                    class="rounded-lg border border-emerald-500/45 bg-slate-900/90 p-2.5 ring-1 ring-emerald-500/20 sm:p-3"
                >
                    <div class="mb-2 flex items-center gap-1.5 sm:mb-2.5">
                        <x-filament::icon
                            icon="heroicon-o-user-circle"
                            class="h-4 w-4 shrink-0 text-emerald-400 sm:h-5 sm:w-5"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-bold text-emerald-200 sm:text-sm">Responsable et PIN</p>
                            <p class="mt-0.5 text-[10px] text-slate-400 sm:text-xs">Sans PIN, non affiché.</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-x-4">
                        <div class="space-y-1.5">
                            <label class="fi-fo-field-wrp-label block" for="gate-staff-select">
                                <span class="text-xs font-medium text-slate-200 sm:text-sm">Responsable (staff)</span>
                            </label>
                            @if (count($this->staffOptions()) === 0)
                                <p class="text-sm text-rose-300">
                                    Aucun staff avec PIN. Admin doit renseigner
                                    <span class="font-mono text-rose-200">pin_code</span>  .
                                </p>
                            @else
                                <x-filament::input.wrapper
                                    :valid="true"
                                    suffix-icon="heroicon-m-chevron-down"
                                    suffix-icon-color="gray"
                                    class="fi-fo-select !bg-slate-800 !ring-1 !ring-slate-600 dark:!bg-slate-800 [&_.fi-select-input]:!text-slate-100 [&_.fi-input-wrp-suffix]:border-slate-600 [&_.fi-input-wrp-icon]:text-slate-400"
                                >
                                    <x-filament::input.select
                                        wire:model.live.debounce.500ms="gateStaffId"
                                        id="gate-staff-select"
                                    >
                                        <option value="">Choisir</option>
                                        @foreach ($this->staffOptions() as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                            @endif
                        </div>
                        <div class="space-y-1.5">
                            <label class="fi-fo-field-wrp-label block" for="gate-pin-input">
                                <span class="text-xs font-medium text-slate-200 sm:text-sm">PIN (4 chiffres)</span>
                            </label>
                            <x-filament::input.wrapper
                                :valid="true"
                                class="!bg-slate-800 !ring-1 !ring-slate-600 dark:!bg-slate-800 [&_.fi-input]:!text-slate-100 [&_.fi-input]:placeholder:!text-slate-500"
                            >
                                <x-filament::input
                                    wire:model.live.debounce.500ms="gatePinInput"
                                    id="gate-pin-input"
                                    type="password"
                                    inputmode="numeric"
                                    maxlength="4"
                                    autocomplete="one-time-code"
                                    placeholder="••••"
                                    class="min-h-10 text-center font-mono text-sm tracking-[0.35em] sm:min-h-11 sm:text-base"
                                />
                            </x-filament::input.wrapper>
                        </div>
                    </div>
                </div>

                <p class="text-center text-[10px] leading-tight text-slate-400 sm:text-xs">
                    Etapes: <span class="text-cyan-300/90">Shift</span> →
                    <span class="text-emerald-300/90">Responsable</span> →
                    <span class="text-violet-300/90">PIN</span> →
                    <span class="text-amber-300/90">Valider</span>
                </p>
            </div>

            <x-slot name="footer">
                <div
                    class="border-t border-cyan-500/30 bg-slate-950/95 pt-1 dark:border-cyan-500/30"
                >
                    <x-filament::button
                        type="button"
                        wire:click="confirmCloseSessionGate"
                        wire:loading.attr="disabled"
                        wire:target="confirmCloseSessionGate"
                        color="warning"
                        size="md"
                        :loading-indicator="false"
                        class="w-full font-black shadow-lg ring-1 ring-amber-300/40 sm:!py-2.5"
                        :disabled="count($this->staffOptions()) === 0"
                    >
                        <span wire:loading.remove wire:target="confirmCloseSessionGate">Valider lock et ouvrir</span>
                        <span wire:loading wire:target="confirmCloseSessionGate">Verification...</span>
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>

        <div class="flex flex-wrap items-center gap-2">
            @if ($this->closeSessionReady)
                <x-filament::badge color="success" size="sm">Lock OK</x-filament::badge>
            @else
                <x-filament::badge color="warning" size="sm">En attente lock</x-filament::badge>
            @endif
            <span class="text-xs text-gray-500 dark:text-gray-400" aria-hidden="true">→</span>
            <span class="text-xs text-gray-600 dark:text-gray-400">Saisie caisse</span>
            <span class="text-xs text-gray-500 dark:text-gray-400" aria-hidden="true">→</span>
            <span class="text-xs text-gray-600 dark:text-gray-400">Envoyer</span>
        </div>

        <div class="-mt-1">
            <button type="button" class="text-[11px] font-semibold text-primary-600 underline decoration-dotted underline-offset-2 dark:text-primary-400" x-on:click="$dispatch('open-modal', { id: 'daily-close-help' })">Comment compter la caisse ?</button>
        </div>

        @if ($this->closeSessionReady)
            @php
                $lockedShift = $this->data['shift'] ?? 'dinner';
                $respName = $this->responsibleStaffDisplayName();
            @endphp
            <div
                @class([
                    'overflow-hidden rounded-xl border-[3px] border-black p-2.5 shadow-[4px_4px_0_0_rgba(0,0,0,1)] sm:p-3',
                    'bg-gradient-to-br from-amber-400 via-orange-500 to-red-700' => $lockedShift === 'lunch',
                    'bg-gradient-to-br from-sky-500 via-indigo-700 to-violet-950' => $lockedShift !== 'lunch',
                ])
            >
                <div class="flex flex-col gap-3 md:flex-row md:items-stretch md:gap-2.5 lg:gap-3">
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border-2 border-yellow-300 bg-black/35 sm:h-14 sm:w-14"
                        >
                            @if ($lockedShift === 'lunch')
                                <x-filament::icon
                                    icon="heroicon-o-sun"
                                    class="h-8 w-8 text-yellow-200 sm:h-9 sm:w-9"
                                />
                            @else
                                <x-filament::icon
                                    icon="heroicon-o-moon"
                                    class="h-8 w-8 text-sky-200 sm:h-9 sm:w-9"
                                />
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] font-black uppercase tracking-[0.12em] text-yellow-100 drop-shadow-[0_1px_0_rgba(0,0,0,0.8)]">
                                {{ $lockedShift === 'lunch' ? 'Midi' : 'Soir' }} · verrouille
                            </p>
                            <p class="mt-0.5 text-base font-black leading-tight text-white drop-shadow-[0_2px_0_rgba(0,0,0,0.5)] sm:text-lg">
                                {{ $lockedShift === 'lunch' ? 'Cloture midi' : 'Cloture soir' }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="min-h-[4.5rem] min-w-0 flex-1 rounded-lg border-2 border-yellow-300 bg-black/30 px-3 py-2.5 sm:min-h-0 md:flex md:max-w-xl md:flex-col md:justify-center md:px-4"
                    >
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-yellow-300">Responsable</p>
                        <p class="mt-1 break-words text-xl font-black leading-snug text-white sm:text-2xl md:text-3xl">
                            {{ $respName }}
                        </p>
                    </div>

                    <div class="shrink-0 md:flex md:items-center">
                        <x-filament::button
                            type="button"
                            wire:click="reopenSessionGate"
                            color="warning"
                            size="sm"
                            class="min-h-11 w-full font-black shadow-[2px_2px_0_0_rgba(0,0,0,0.85)] md:min-h-12 md:w-auto md:px-5"
                        >
                            Changer shift/responsable
                        </x-filament::button>
                    </div>
                </div>
            </div>
        @endif

        <div
            class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
        >
            @if ($this->closeSessionReady)
                @if ($this->fetchedRecettesPanel !== null)
                    <div
                        class="mb-2 rounded-lg border-2 border-amber-400/80 bg-amber-50 px-3 py-2.5 text-gray-950 shadow-sm ring-1 ring-amber-500/25 dark:border-amber-500/50 dark:bg-amber-950/40 dark:text-white dark:ring-amber-400/20"
                        wire:key="daily-close-fetched-recettes-panel"
                    >
                        <p class="text-[10px] font-black uppercase tracking-wider text-amber-900 dark:text-amber-200">
                            Ventes API · {{ $this->shiftLabel($this->data['shift'] ?? null) }}
                        </p>
                        <p class="mt-1 font-mono text-xl font-bold tabular-nums text-amber-950 dark:text-amber-50">
                            {{ $this->formatMoneyCompact($this->fetchedRecettesAmountForCurrentShift()) }}
                            <span class="text-sm font-semibold text-amber-800 dark:text-amber-200/90">DT</span>
                        </p>
                        <p class="mt-1 text-xs text-gray-700 dark:text-gray-300">
                            Date {{ $this->fetchedRecettesPanel['date'] ?? '' }}
                        </p>
                    </div>
                @endif
                <div
                    wire:loading
                    wire:target="fetchRecettesFromApi"
                    class="mb-2 rounded-lg border border-dashed border-gray-300 bg-gray-50/80 px-2 py-1.5 text-xs font-medium text-gray-600 dark:border-white/15 dark:bg-white/5 dark:text-gray-400"
                >
                    Chargement des recettes…
                </div>
                <x-filament-panels::form id="form" wire:submit="calculate">
                    {{ $this->form }}

                    <x-filament-panels::form.actions
                        :actions="$this->getCachedFormActions()"
                        :full-width="$this->hasFullWidthFormActions()"
                    />
                </x-filament-panels::form>
            @else
                <div class="space-y-2 py-5 text-center">
                    <p class="text-base font-semibold text-gray-900 dark:text-white">Valide le lock dans le modal</p>
                    <p class="mx-auto max-w-md text-sm text-gray-600 dark:text-gray-400">
                        Choisis shift, responsable et PIN 4 chiffres pour ouvrir le formulaire.
                    </p>
                    <x-filament::button type="button" wire:click="openSessionGateModal" color="primary">
                        Ouvrir
                    </x-filament::button>
                </div>
            @endif
        </div>

        <div
            class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
        >
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 pb-2 dark:border-white/10">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">History</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Historique cloture (50)</p>
                    <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">
                        Detail: <code class="rounded bg-gray-100 px-1 font-mono text-[10px] dark:bg-white/10">close_snapshot</code>
                    </p>
                </div>
                <x-filament::button
                    type="button"
                    wire:click="toggleHistoryDetail"
                    color="gray"
                    size="sm"
                    outlined
                >
                    {{ $historyDetailOpen ? 'Masquer details' : 'Afficher details' }}
                </x-filament::button>
            </div>

            @if ($historyDetailOpen)
                <div class="mt-3 max-h-[28rem] overflow-auto rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
                    <table class="fi-ta-table w-full min-w-[80rem] border-collapse text-left text-xs">
                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-white/5">
                            <tr class="border-b border-gray-200 dark:border-white/10">
                                <th class="whitespace-nowrap border-e border-gray-200 px-2 py-0.5 text-start font-medium text-gray-700 dark:border-white/10 dark:text-gray-300">Heure</th>
                                <th class="whitespace-nowrap border-e border-gray-200 px-2 py-0.5 text-start font-medium text-gray-700 dark:border-white/10 dark:text-gray-300">Date</th>
                                <th class="whitespace-nowrap border-e border-gray-200 px-2 py-0.5 text-start font-medium text-gray-700 dark:border-white/10 dark:text-gray-300">S</th>
                                <th class="whitespace-nowrap border-e border-gray-200 px-2 py-0.5 text-end font-medium text-gray-700 dark:border-white/10 dark:text-gray-300">Ventes</th>
                                <th class="whitespace-nowrap border-e border-gray-200 px-2 py-0.5 text-end font-medium text-gray-700 dark:border-white/10 dark:text-gray-300">Fond</th>
                                <th class="whitespace-nowrap border-e border-gray-200 px-2 py-0.5 text-end font-medium text-gray-700 dark:border-white/10 dark:text-gray-300">Cash</th>
                                <th class="whitespace-nowrap border-e border-gray-200 px-2 py-0.5 text-end font-medium text-gray-700 dark:border-white/10 dark:text-gray-300">Cheque</th>
                                <th class="whitespace-nowrap border-e border-gray-200 px-2 py-0.5 text-end font-medium text-gray-700 dark:border-white/10 dark:text-gray-300">ｶｰﾄﾞ</th>
                                <th class="whitespace-nowrap border-e border-gray-200 px-2 py-0.5 text-end font-medium text-gray-700 dark:border-white/10 dark:text-gray-300">ﾁｯﾌﾟ</th>
                                <th class="whitespace-nowrap border-e border-gray-200 px-2 py-0.5 text-end font-medium text-gray-700 dark:border-white/10 dark:text-gray-300">Mesure caisse</th>
                                <th class="whitespace-nowrap border-e border-gray-200 px-2 py-0.5 text-end font-medium text-gray-700 dark:border-white/10 dark:text-gray-300">Tip+POS</th>
                                <th class="whitespace-nowrap border-e border-gray-200 px-2 py-0.5 text-end font-medium text-gray-700 dark:border-white/10 dark:text-gray-300">Ecart</th>
                                <th class="whitespace-nowrap border-e border-gray-200 px-2 py-0.5 text-start font-medium text-gray-700 dark:border-white/10 dark:text-gray-300">Statut</th>
                                <th class="whitespace-nowrap border-e border-gray-200 px-2 py-0.5 text-start font-medium text-gray-700 dark:border-white/10 dark:text-gray-300">Responsable</th>
                                <th class="whitespace-nowrap border-e border-gray-200 px-2 py-0.5 text-start font-medium text-gray-700 dark:border-white/10 dark:text-gray-300">Operateur</th>
                                <th class="whitespace-nowrap px-2 py-0.5 text-start font-medium text-gray-700 dark:text-gray-300">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-white/10 dark:bg-gray-900">
                            @forelse ($this->closeHistoryRows() as $h)
                                <tr
                                    @class([
                                        'text-gray-900 hover:bg-gray-50 dark:text-gray-100 dark:hover:bg-white/5',
                                        'bg-danger-50/40 dark:bg-danger-950/20' => $h->verdict !== 'bravo',
                                    ])
                                >
                                    <td class="whitespace-nowrap border-e border-gray-100 px-2 py-0.5 font-mono text-[10px] dark:border-white/10">
                                        {{ $h->created_at?->format('m/d H:i') }}
                                    </td>
                                    <td class="whitespace-nowrap border-e border-gray-100 px-2 py-0.5 font-mono text-[10px] dark:border-white/10">
                                        {{ $h->business_date?->format('Y-m-d') }}
                                    </td>
                                    <td class="whitespace-nowrap border-e border-gray-100 px-2 py-0.5 font-medium dark:border-white/10">
                                        {{ $h->shift === 'lunch' ? 'L' : 'D' }}
                                    </td>
                                    <td class="whitespace-nowrap border-e border-gray-100 px-2 py-0.5 text-end font-mono tabular-nums dark:border-white/10">
                                        {{ $this->formatMoneyCompact($h->recettes) }}
                                    </td>
                                    <td class="whitespace-nowrap border-e border-gray-100 px-2 py-0.5 text-end font-mono tabular-nums dark:border-white/10">
                                        {{ $this->formatMoneyCompact($h->montant_initial) }}
                                    </td>
                                    <td class="whitespace-nowrap border-e border-gray-100 px-2 py-0.5 text-end font-mono tabular-nums dark:border-white/10">
                                        {{ $this->formatMoneyCompact($h->cash) }}
                                    </td>
                                    <td class="whitespace-nowrap border-e border-gray-100 px-2 py-0.5 text-end font-mono tabular-nums dark:border-white/10">
                                        {{ $this->formatMoneyCompact($h->cheque) }}
                                    </td>
                                    <td class="whitespace-nowrap border-e border-gray-100 px-2 py-0.5 text-end font-mono tabular-nums dark:border-white/10">
                                        {{ $this->formatMoneyCompact($h->carte) }}
                                    </td>
                                    <td class="whitespace-nowrap border-e border-gray-100 px-2 py-0.5 text-end font-mono tabular-nums dark:border-white/10">
                                        {{ $this->formatMoneyCompact($h->chips) }}
                                    </td>
                                    <td class="whitespace-nowrap border-e border-gray-100 px-2 py-0.5 text-end font-mono font-semibold text-primary-600 tabular-nums dark:border-white/10 dark:text-primary-400">
                                        {{ $this->formatMoneyCompact($h->register_total) }}
                                    </td>
                                    <td class="whitespace-nowrap border-e border-gray-100 px-2 py-0.5 text-end font-mono tabular-nums dark:border-white/10">
                                        {{ $this->formatMoneyCompact((float) $h->chips + (float) $h->recettes) }}
                                    </td>
                                    <td class="whitespace-nowrap border-e border-gray-100 px-2 py-0.5 text-end font-mono tabular-nums dark:border-white/10">
                                        {{ $this->formatMoneyCompact($h->final_difference) }}
                                    </td>
                                    <td class="whitespace-nowrap border-e border-gray-100 px-2 py-0.5 dark:border-white/10">
                                        @php
                                            [$vIcon, $vIconClass] = $this->historyVerdictIcon($h);
                                        @endphp
                                        <span class="inline-flex items-center gap-1">
                                            <x-filament::icon :icon="$vIcon" :class="$vIconClass" />
                                            <span class="font-mono text-[10px]">{{ $this->historyVerdictLabel($h) }}</span>
                                        </span>
                                    </td>
                                    <td
                                        class="max-w-[6rem] truncate border-e border-gray-100 px-2 py-0.5 text-[10px] text-gray-800 dark:border-white/10 dark:text-gray-200"
                                        title="{{ $this->historyResponsibleTitle($h) }}"
                                    >
                                        <span class="font-medium">{{ $this->historyResponsibleDisplay($h) }}</span>
                                        @if (! $h->responsible_pin_verified)
                                            <span
                                                class="ms-0.5 rounded bg-gray-200 px-0.5 text-[9px] font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300"
                                            >Legacy</span>
                                        @endif
                                    </td>
                                    <td class="max-w-[6rem] truncate border-e border-gray-100 px-2 py-0.5 text-[10px] text-gray-800 dark:border-white/10 dark:text-gray-200" title="Operateur panneau (Filament)">
                                        {{ $this->historyOperatorDisplay($h) }}
                                    </td>
                                    <td class="whitespace-nowrap px-2 py-0.5">
                                        <x-filament::button
                                            type="button"
                                            wire:click="openHistorySnapshotModal({{ (int) $h->id }})"
                                            size="xs"
                                            color="gray"
                                            outlined
                                        >
                                            Detail
                                        </x-filament::button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="16" class="px-2 py-3 text-center text-xs text-gray-600 dark:text-gray-400">
                                        Aucun historique caisse.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <x-filament::modal
        id="finance-history-snapshot-modal"
        :close-by-clicking-away="true"
        :close-by-escaping="true"
        width="5xl"
        x-on:modal-closed.window="if ($event.detail.id === 'finance-history-snapshot-modal') { $wire.set('selectedHistoryFinanceId', null) }"
    >
        @php
            $snapFin = $this->selectedHistoryFinance();
        @endphp
        @if ($snapFin)
            <div class="max-h-[80vh] space-y-4 overflow-y-auto text-gray-950 dark:text-white">
                <div class="border-b border-gray-200 pb-3 dark:border-white/10">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Snapshot</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                        Detail cloture #{{ $snapFin->id }} · {{ $snapFin->business_date?->format('Y-m-d') }} ·
                        {{ $snapFin->shift === 'lunch' ? 'Midi' : 'Soir' }}
                    </p>
                </div>

                @if (filled($snapFin->close_snapshot) && is_array($snapFin->close_snapshot))
                    @php
                        $snap = $snapFin->close_snapshot;
                        $d = $snap['derived'] ?? [];
                        $ms = $d['measured_share_pct'] ?? [];
                        $rs = $d['relative_to_sales'] ?? [];
                    @endphp
                    <div class="grid grid-cols-2 gap-2 text-xs md:grid-cols-4">
                        <div class="rounded-lg bg-gray-50 p-2 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                            <p class="font-medium text-gray-600 dark:text-gray-400">tip/mesure %</p>
                            <p class="font-mono font-semibold text-primary-600 dark:text-primary-400">
                                {{ isset($ms['chips_of_register']) ? number_format($ms['chips_of_register'], 2).'%' : '—' }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-2 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                            <p class="font-medium text-gray-600 dark:text-gray-400">ｶｰﾄﾞ/Ventes%</p>
                            <p class="font-mono font-semibold text-primary-600 dark:text-primary-400">
                                {{ isset($rs['carte_to_recettes_pct']) ? number_format($rs['carte_to_recettes_pct'], 2).'%' : '—' }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-2 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                            <p class="font-medium text-gray-600 dark:text-gray-400">ﾁｯﾌﾟ/Ventes%</p>
                            <p class="font-mono font-semibold text-primary-600 dark:text-primary-400">
                                {{ isset($rs['chips_to_recettes_pct']) ? number_format($rs['chips_to_recettes_pct'], 2).'%' : '—' }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-2 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                            <p class="font-medium text-gray-600 dark:text-gray-400">schema</p>
                            <p class="font-mono">{{ $snap['schema_version'] ?? '—' }}</p>
                        </div>
                    </div>

                    <div class="rounded-lg bg-gray-50 p-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <p class="mb-1 text-xs font-medium text-gray-600 dark:text-gray-400">close_snapshot（JSON）</p>
                        <pre class="max-h-48 overflow-auto whitespace-pre-wrap break-all font-mono text-[10px] leading-snug text-gray-900 dark:text-gray-100">{{ json_encode($snap, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                    </div>
                @else
                    <p class="text-xs text-gray-600 dark:text-gray-400">Donnees anterieures au snapshot.</p>
                @endif

                <x-filament::button
                    type="button"
                    x-on:click="$dispatch('close-modal', { id: 'finance-history-snapshot-modal' })"
                    color="gray"
                    class="w-full"
                >
                    Fermer
                </x-filament::button>
            </div>
        @elseif ($this->selectedHistoryFinanceId !== null)
            <div class="space-y-3 py-2">
                <p class="text-sm font-medium text-gray-900 dark:text-white">Donnee introuvable.</p>
                <p class="text-xs text-gray-600 dark:text-gray-400">Supprimee ou ID invalide.</p>
                <x-filament::button
                    type="button"
                    x-on:click="$dispatch('close-modal', { id: 'finance-history-snapshot-modal' })"
                    color="gray"
                    class="w-full"
                >
                    Fermer
                </x-filament::button>
            </div>
        @else
            <p class="text-sm text-gray-600 dark:text-gray-400">Chargement...</p>
        @endif
    </x-filament::modal>

    <x-filament::modal id="daily-close-help" width="2xl">
        <div class="space-y-2 text-sm text-gray-900 dark:text-gray-100">
            <p class="font-semibold">Guide rapide</p>
            <ol class="list-decimal space-y-1 ps-4 text-xs">
                <li>Retire le fond de caisse avant de compter le cash.</li>
                <li>Paramètres : ventes POS + tip déclaré. Mesure caisse : cash + chèque + carte (sans tip).</li>
                <li>Bravo si mesure caisse = tip déclaré + ventes POS (tolérance).</li>
                <li>Si erreur : revérifie tickets, double comptage, ou saisie tip / POS.</li>
            </ol>
        </div>
    </x-filament::modal>

    <x-filament::modal
        id="daily-close-recettes-api-error"
        :close-by-clicking-away="true"
        :close-by-escaping="true"
        width="md"
        icon="heroicon-o-exclamation-triangle"
        icon-color="danger"
        heading="Erreur"
    >
        <div class="space-y-4 py-1">
            <p class="text-sm font-semibold leading-relaxed text-danger-700 dark:text-danger-400">
                {{ $this->recettesApiErrorModalBody }}
            </p>
            <x-filament::button
                type="button"
                wire:click="closeRecettesApiErrorModal"
                color="gray"
                class="w-full text-gray-950 dark:text-white"
            >
                Fermer
            </x-filament::button>
        </div>
    </x-filament::modal>

    <x-filament::modal
        id="daily-close-result-modal"
        :close-by-clicking-away="true"
        :close-by-escaping="true"
        width="2xl"
    >
        @php
            $v = $resultModalCalc['verdict'] ?? '';
            $verdictLabel = match ($v) {
                'bravo' => 'Bravo',
                'plus_error' => 'Erreur (+)',
                'minus_error' => 'Erreur (-)',
                'failed' => 'Failed',
                default => '—',
            };
            $fmt = static fn ($n) => number_format((float) $n, 3, '.', ',');
        @endphp

        @if ($resultModalKind === 'bravo')
            <div class="space-y-4 rounded-xl border-2 border-black bg-gradient-to-br from-emerald-100 via-cyan-50 to-sky-100 p-4 shadow-[4px_4px_0_0_rgba(0,0,0,1)] dark:border-white/20 dark:from-emerald-900/30 dark:via-cyan-950/30 dark:to-sky-950/30">
                <p class="text-center text-xs font-medium uppercase tracking-wide text-success-700 dark:text-success-400">Register close</p>
                <div class="relative overflow-hidden rounded-lg border border-amber-300/70 bg-gradient-to-r from-amber-200/80 via-yellow-100/80 to-rose-100/80 px-3 py-3 dark:border-amber-500/40 dark:from-amber-900/30 dark:via-yellow-900/20 dark:to-rose-900/20">
                    <div class="pointer-events-none absolute -top-1 left-2 text-amber-500 animate-bounce">✦</div>
                    <div class="pointer-events-none absolute -top-1 right-3 text-rose-500 animate-pulse">✦</div>
                    <div class="pointer-events-none absolute -bottom-1 left-1/3 text-sky-500 animate-bounce">✦</div>
                    <p class="text-center text-lg font-black text-gray-900 dark:text-white">Bravo !</p>
                    <p class="mt-1 text-center text-sm font-semibold text-emerald-700 dark:text-emerald-300">
                        Merci {{ $this->responsibleStaffDisplayName() }} !
                    </p>
                </div>
                <p class="text-center text-sm text-gray-700 dark:text-gray-300">{{ $resultModalShiftLabel }}</p>
                @if ($resultModalDbSaved)
                    <p class="text-center text-xs text-gray-600 dark:text-gray-400">Sauvegarde en base OK</p>
                @endif

                <dl class="space-y-2 rounded-lg bg-white p-3 text-sm shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex justify-between gap-2 border-b border-gray-300 pb-2 dark:border-white/10">
                        <dt class="font-medium text-gray-700 dark:text-gray-300">Tip déclaré (paramètres)</dt>
                        <dd class="font-mono font-semibold tabular-nums">{{ $fmt($resultModalCalc['declared_tip'] ?? ($resultModalPayload['chips'] ?? 0)) }}</dd>
                    </div>
                    <div class="flex justify-between gap-2 border-b border-gray-300 pb-2 dark:border-white/10">
                        <dt class="font-medium text-sky-700 dark:text-sky-300">Ventes POS ({{ $resultModalShiftLabel }})</dt>
                        <dd class="font-mono font-semibold tabular-nums text-sky-700 dark:text-sky-300">{{ $fmt($resultModalCalc['expected_sales'] ?? 0) }}</dd>
                    </div>
                    <div class="flex justify-between gap-2 border-b border-gray-300 pb-2 dark:border-white/10">
                        <dt class="font-medium text-sky-700 dark:text-sky-300">Tip déclaré + ventes POS</dt>
                        <dd class="font-mono font-semibold tabular-nums text-sky-700 dark:text-sky-300">{{ $fmt($resultModalCalc['sum_tip_plus_pos_sales'] ?? (($resultModalCalc['declared_tip'] ?? 0) + ($resultModalCalc['expected_sales'] ?? 0))) }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="font-medium text-primary-700 dark:text-primary-300">Mesure caisse (cash + chèque + carte)</dt>
                        <dd class="font-mono font-semibold tabular-nums text-primary-700 dark:text-primary-300">{{ $fmt($resultModalCalc['measured_without_declared_tip'] ?? 0) }}</dd>
                    </div>
                </dl>

                <p class="text-center text-xs leading-relaxed text-gray-700 dark:text-gray-300">{{ $resultModalHint }}</p>

                <x-filament::button
                    type="button"
                    x-on:click="$dispatch('close-modal', { id: 'daily-close-result-modal' })"
                    color="gray"
                    class="w-full"
                >
                    Fermer
                </x-filament::button>
            </div>
        @else
            <div class="space-y-4 rounded-xl border-2 border-black bg-gradient-to-br from-amber-100 via-orange-100 to-red-100 p-4 shadow-[4px_4px_0_0_rgba(0,0,0,1)] dark:border-white/20 dark:from-amber-900/30 dark:via-orange-950/30 dark:to-red-950/30">
                <p class="text-center text-xs font-medium uppercase tracking-wide text-warning-800 dark:text-warning-400">Check again</p>
                <p class="text-center text-base font-semibold text-gray-900 dark:text-white">Recheck avant renvoi</p>
                <p class="text-center text-sm text-gray-700 dark:text-gray-300">{{ $resultModalShiftLabel }}</p>
                <p class="text-center text-sm font-medium text-warning-800 dark:text-warning-300">Statut: {{ $verdictLabel }}</p>
                @if ($resultModalDbSaved)
                    <p class="text-center text-xs text-gray-600 dark:text-gray-400">
                        Sauvegarde faite (record failed).
                    </p>
                @endif

                <dl class="space-y-2 rounded-lg bg-white p-3 text-sm shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex justify-between gap-2 border-b border-gray-300 pb-2 dark:border-white/10">
                        <dt class="font-medium text-gray-700 dark:text-gray-300">Tip déclaré (paramètres)</dt>
                        <dd class="font-mono font-semibold tabular-nums">{{ $fmt($resultModalCalc['declared_tip'] ?? ($resultModalPayload['chips'] ?? 0)) }}</dd>
                    </div>
                    <div class="flex justify-between gap-2 border-b border-gray-300 pb-2 dark:border-white/10">
                        <dt class="font-medium text-sky-700 dark:text-sky-300">Ventes POS ({{ $resultModalShiftLabel }})</dt>
                        <dd class="font-mono font-semibold tabular-nums text-sky-700 dark:text-sky-300">{{ $fmt($resultModalCalc['expected_sales'] ?? 0) }}</dd>
                    </div>
                    <div class="flex justify-between gap-2 border-b border-gray-300 pb-2 dark:border-white/10">
                        <dt class="font-medium text-sky-700 dark:text-sky-300">Tip déclaré + ventes POS</dt>
                        <dd class="font-mono font-semibold tabular-nums text-sky-700 dark:text-sky-300">{{ $fmt($resultModalCalc['sum_tip_plus_pos_sales'] ?? (($resultModalCalc['declared_tip'] ?? 0) + ($resultModalCalc['expected_sales'] ?? 0))) }}</dd>
                    </div>
                    <div class="flex justify-between gap-2 border-b border-gray-300 pb-2 dark:border-white/10">
                        <dt class="font-medium text-primary-700 dark:text-primary-300">Mesure caisse (cash + chèque + carte)</dt>
                        <dd class="font-mono font-semibold tabular-nums text-primary-700 dark:text-primary-300">{{ $fmt($resultModalCalc['measured_without_declared_tip'] ?? 0) }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="font-medium text-gray-700 dark:text-gray-300">Écart (mesure − (tip déclaré + ventes POS))</dt>
                        @php $diff = (float) ($resultModalCalc['final_difference'] ?? 0); @endphp
                        <dd class="inline-flex items-center gap-1 font-mono font-semibold tabular-nums text-danger-600 dark:text-danger-400">
                            @if ($diff > 0)
                                <x-filament::icon icon="heroicon-o-plus-circle" class="h-4 w-4 text-warning-600 dark:text-warning-400" />
                            @elseif ($diff < 0)
                                <x-filament::icon icon="heroicon-o-minus-circle" class="h-4 w-4 text-danger-600 dark:text-danger-400" />
                            @endif
                            <span>{{ $fmt($diff) }}</span>
                        </dd>
                    </div>
                </dl>

                <div class="rounded-lg bg-white p-3 text-xs font-medium leading-relaxed text-gray-800 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-100 dark:ring-white/10">
                    {{ $resultModalHint }}
                </div>

                <x-filament::button
                    type="button"
                    x-on:click="$dispatch('close-modal', { id: 'daily-close-result-modal' })"
                    color="warning"
                    class="w-full"
                >
                    Fermer et corriger
                </x-filament::button>
            </div>
        @endif
    </x-filament::modal>

    <div class="pointer-events-none fixed bottom-3 end-3 z-40 flex justify-end">
        <x-filament::button
            type="button"
            wire:click="openAdminDoorModal"
            size="xs"
            color="gray"
            outlined
            class="pointer-events-auto !rounded-full"
            title="Door"
        >
            Door
        </x-filament::button>
    </div>

    <x-filament::modal
        id="daily-close-admin-door"
        :close-by-clicking-away="true"
        :close-by-escaping="true"
        width="4xl"
    >
        <div class="space-y-4 text-gray-950 dark:text-white">
            <div class="border-b border-gray-200 pb-3 dark:border-white/10">
                <p class="text-sm font-semibold text-gray-900 dark:text-white">Door manager (lecture)</p>
                <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                    Seuils et historique visibles ici uniquement.
                </p>
            </div>

            @if (! $this->isAdminDoorUnlocked())
                <form wire:submit.prevent="attemptUnlockAdminDoor" class="space-y-3">
                    <div>
                        <label
                            for="daily-close-door-pin"
                            class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 text-sm font-medium text-gray-950 dark:text-white"
                        >
                            PIN
                        </label>
                        <x-filament::input.wrapper :valid="true" class="mt-1 max-w-sm">
                            <x-filament::input
                                id="daily-close-door-pin"
                                type="password"
                                autocomplete="off"
                                wire:model="doorPinInput"
                                class="font-mono"
                            />
                        </x-filament::input.wrapper>
                    </div>
                    <x-filament::button type="submit" color="primary" size="sm">
                        Autoriser
                    </x-filament::button>
                </form>
            @else
                <div class="flex flex-wrap items-center gap-2">
                    <x-filament::button type="button" wire:click="lockAdminDoor" color="gray" size="sm" outlined>
                        Fermer lecture
                    </x-filament::button>
                </div>

                <div class="space-y-2">
                    <p class="text-xs font-medium text-gray-900 dark:text-white">Seuils tolerance (+)</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">La tolerance + est definie par plage de ventes (saisie).</p>
                    <div class="overflow-x-auto rounded-lg bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <table class="fi-ta-table w-full min-w-[28rem] divide-y divide-gray-200 text-start text-sm dark:divide-white/10">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-white/5">
                                    <th class="fi-ta-header-cell px-3 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Plage ventes</th>
                                    <th class="fi-ta-header-cell px-3 py-2 text-end font-medium text-gray-700 dark:text-gray-300">Tolerance (+)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/10">
                                @foreach (\App\Services\FinanceCalculatorService::toleranceBandsForAdmin() as $band)
                                    <tr class="fi-ta-row bg-white hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-white/5">
                                        <td class="fi-ta-cell p-3 text-gray-900 dark:text-white">{{ $band['range'] }}</td>
                                        <td class="fi-ta-cell p-3 text-end font-mono text-gray-900 dark:text-white">
                                            {{ number_format($band['tolerance'], 3, '.', ',') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-2">
                    <p class="text-xs font-medium text-gray-900 dark:text-white">Historique (40)</p>
                    <div class="overflow-x-auto rounded-lg bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <table class="fi-ta-table w-full min-w-[48rem] divide-y divide-gray-200 text-start text-sm dark:divide-white/10">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-white/5">
                                    <th class="fi-ta-header-cell px-2 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Date/heure</th>
                                    <th class="fi-ta-header-cell px-2 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Date</th>
                                    <th class="fi-ta-header-cell px-2 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Shift</th>
                                    <th class="fi-ta-header-cell px-2 py-2 text-end font-medium text-gray-700 dark:text-gray-300">Ventes</th>
                                    <th class="fi-ta-header-cell px-2 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Statut</th>
                                    <th class="fi-ta-header-cell px-2 py-2 text-end font-medium text-gray-700 dark:text-gray-300">Tolerance utilisee</th>
                                    <th class="fi-ta-header-cell px-2 py-2 text-end font-medium text-gray-700 dark:text-gray-300">Ecart final</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                @forelse ($this->adminDoorFinanceRows() as $row)
                                    <tr class="fi-ta-row bg-white hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-white/5">
                                        <td class="fi-ta-cell whitespace-nowrap p-2 text-gray-900 dark:text-white">{{ $row->created_at?->format('Y-m-d H:i') }}</td>
                                        <td class="fi-ta-cell whitespace-nowrap p-2 text-gray-900 dark:text-white">{{ $row->business_date?->format('Y-m-d') }}</td>
                                        <td class="fi-ta-cell whitespace-nowrap p-2 text-gray-900 dark:text-white">
                                            {{ $row->shift === 'lunch' ? 'Midi' : ($row->shift === 'dinner' ? 'Soir' : $row->shift) }}
                                        </td>
                                        <td class="fi-ta-cell whitespace-nowrap p-2 text-end font-mono text-gray-900 dark:text-white">{{ number_format((float) $row->recettes, 3, '.', ',') }}</td>
                                        <td class="fi-ta-cell whitespace-nowrap p-2 text-gray-900 dark:text-white">
                                            @php
                                                $doorV = $row->verdict ?? '';
                                                $doorVerdictLabel = match ($doorV) {
                                                    'bravo' => 'Bravo',
                                                    'plus_error' => 'Erreur (+)',
                                                    'minus_error' => 'Erreur (-)',
                                                    default => $doorV ?: '—',
                                                };
                                            @endphp
                                            {{ $doorVerdictLabel }}
                                        </td>
                                        <td class="fi-ta-cell whitespace-nowrap p-2 text-end font-mono text-gray-900 dark:text-white">{{ number_format((float) $row->tolerance_used, 3, '.', ',') }}</td>
                                        <td class="fi-ta-cell whitespace-nowrap p-2 text-end font-mono text-gray-900 dark:text-white">{{ number_format((float) $row->final_difference, 3, '.', ',') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="p-4 text-center text-sm text-gray-600 dark:text-gray-400">
                                            Aucune cloture enregistree.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </x-filament::modal>
</x-filament-panels::page>
