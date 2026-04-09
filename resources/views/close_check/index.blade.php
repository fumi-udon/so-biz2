@extends('layouts.app')

@section('title', 'Clôture des tâches — '.config('app.name', 'Laravel'))

@push('head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
@endpush

@section('content')
@php
    $blocked = count($incompleteLines ?? []) > 0;
@endphp
<div class="min-h-screen bg-gray-50 text-gray-950 dark:bg-gray-900 dark:text-gray-100">
    <x-client-nav />

    <div
        x-data="{
            showModal: false,
            authStep: 1,
            oathChecked: false,
            checked: {},
            staffOathName: '',
            blocked: @json($blocked),
            taskIds: @json($tasks->pluck('id')->values()),
            init() {
                this.taskIds.forEach((id) => { this.checked[id] = false });
            },
            allTasksDone() {
                return this.taskIds.length > 0 && this.taskIds.every((id) => this.checked[id] === true);
            },
            canOpenModal() {
                return !this.blocked && this.allTasksDone();
            },
            openBossRoom() {
                if (!this.canOpenModal()) {
                    return;
                }
                this.showModal = true;
                this.authStep = 1;
                this.oathChecked = false;
                this.staffOathName = '';
            },
            closeBossRoom() {
                this.showModal = false;
                this.authStep = 1;
                this.oathChecked = false;
                this.staffOathName = '';
            },
            advanceToOath() {
                const staff = this.$refs.staffSel;
                const pin = this.$refs.pinInp;
                const form = this.$refs.closeCheckForm;
                if (!staff || !pin || !form) {
                    return;
                }
                if (!form.reportValidity()) {
                    return;
                }
                this.staffOathName = staff.options[staff.selectedIndex].text.trim();
                this.authStep = 2;
                this.oathChecked = false;
            },
        }"
        class="mx-auto w-full max-w-6xl px-3 py-3"
    >
        <header class="mb-4 rounded-2xl border-2 border-red-900 bg-gradient-to-r from-red-950 via-rose-950 to-red-950 p-4 text-white shadow-[0_6px_0_0_rgba(0,0,0,1)]">
            <h1 class="font-['Press_Start_2P'] text-[11px] leading-snug tracking-tight text-amber-200 sm:text-sm">CLOSE CHECK</h1>
            <p class="mt-2 text-sm font-bold text-rose-100">
                Vérification avant fermeture. Cochez chaque point, puis validez avec le responsable.
            </p>
        </header>

        @if (! empty($incompleteLines))
            <div class="mb-3 rounded-xl border border-rose-400 bg-rose-50 p-3 text-sm text-rose-950 dark:border-rose-700 dark:bg-rose-950/40 dark:text-rose-100">
                <p class="mb-2 font-bold">Inventaire ou tâches non terminés (toute l’équipe)</p>
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($incompleteLines as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
                <p class="mt-2 font-semibold">
                    Terminez depuis Mon espace avant la clôture finale.
                </p>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-3 rounded-xl border border-rose-400 bg-rose-50 p-3 text-sm font-semibold text-rose-950 dark:border-rose-700 dark:bg-rose-950/40 dark:text-rose-100">
                {{ session('error') }}
            </div>
        @endif

        @if ($tasks->isEmpty())
            <p class="rounded-xl border-2 border-dashed border-slate-400 bg-white p-8 text-center text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300">
                Aucune tâche active enregistrée.
            </p>
        @else
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($tasks as $task)
                    <label
                        class="flex cursor-pointer flex-col overflow-hidden rounded-xl border-2 shadow-[0_4px_0_0_rgba(0,0,0,0.15)] transition-colors duration-200 dark:shadow-[0_4px_0_0_rgba(0,0,0,0.4)]"
                        :class="checked[{{ $task->id }}] ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-500 dark:bg-emerald-950/40' : 'border-rose-500 bg-rose-50 dark:border-rose-600 dark:bg-rose-950/35'"
                    >
                        <div class="flex items-stretch">
                            <div class="w-28 shrink-0 bg-slate-200 dark:bg-slate-800">
                                @if ($task->image_path)
                                    <img src="{{ asset('storage/'.$task->image_path) }}" alt="" class="aspect-video h-full w-full object-cover">
                                @else
                                    <div class="flex h-full min-h-[5rem] items-center justify-center text-2xl text-slate-500 dark:text-slate-500">🖼️</div>
                                @endif
                            </div>
                            <div class="flex grow flex-col p-3">
                                <h2 class="text-base font-bold text-gray-950 dark:text-white">{{ $task->title }}</h2>
                                @if ($task->description)
                                    <p class="mt-1 line-clamp-4 text-sm text-gray-700 dark:text-gray-300">{{ $task->description }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2 border-t border-black/10 px-3 py-2.5 dark:border-white/10">
                            <input type="checkbox" x-model="checked[{{ $task->id }}]" class="h-5 w-5 shrink-0 rounded border-gray-400 text-emerald-600 focus:ring-emerald-500 dark:border-gray-500">
                            <span class="text-sm font-bold text-gray-950 dark:text-gray-100">Vu / OK</span>
                            <span x-show="checked[{{ $task->id }}]" x-cloak class="ml-auto text-lg" aria-hidden="true">✅</span>
                        </div>
                    </label>
                @endforeach
            </div>

            <div class="mt-5">
                <button
                    type="button"
                    @click="openBossRoom()"
                    :disabled="blocked || !allTasksDone()"
                    :class="{ 'animate-pulse': canOpenModal() }"
                    class="inline-flex w-full items-center justify-center rounded-xl border-2 border-amber-600 bg-gradient-to-b from-amber-400 to-orange-600 px-4 py-3.5 text-lg font-black text-gray-950 shadow-[0_6px_0_0_#9a3412] enabled:hover:brightness-105 enabled:active:translate-y-1 enabled:active:shadow-[0_3px_0_0_#9a3412] disabled:cursor-not-allowed disabled:opacity-45 disabled:animate-none dark:border-amber-500 dark:from-amber-500 dark:to-orange-700 dark:text-gray-950 dark:shadow-[0_6px_0_0_#7c2d12]"
                >
                    Valider et continuer
                </button>
            </div>

            {{-- Boss room: PIN → oath → submit --}}
            <div
                x-show="showModal"
                x-cloak
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 z-[60] flex items-center justify-center bg-red-950/80 p-4 backdrop-blur-[2px] dark:bg-red-950/85"
                role="dialog"
                aria-modal="true"
                aria-labelledby="boss-modal-title"
                @keydown.escape.window="closeBossRoom()"
                @click.self="closeBossRoom()"
            >
                <div
                    class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border-2 border-red-800 bg-gray-950 p-4 text-gray-100 shadow-[0_12px_0_0_rgba(0,0,0,0.5)] ring-2 ring-red-600/40 sm:p-6"
                    @click.stop
                >
                    <div class="mb-4 flex items-start justify-between gap-2">
                        <p id="boss-modal-title" class="font-['Press_Start_2P'] text-[8px] leading-relaxed text-red-300 sm:text-[9px]">
                            WARNING: ZONE DE SÉCURITÉ
                        </p>
                        <button
                            type="button"
                            class="shrink-0 rounded border border-red-700/80 bg-red-950 px-2 py-1 text-xs font-bold text-red-200 hover:bg-red-900"
                            @click="closeBossRoom()"
                        >
                            ✕
                        </button>
                    </div>

                    <form x-ref="closeCheckForm" action="{{ route('close-check.process') }}" method="post" class="space-y-4">
                        @csrf

                        <div x-show="authStep === 1" x-cloak class="space-y-4">
                            <p class="text-center text-xs font-semibold uppercase tracking-wide text-red-300/90">
                                Étape 1 — Identification
                            </p>
                            <div>
                                <label for="staff_id" class="mb-1 block text-sm font-bold text-red-100">Responsable du jour</label>
                                <select
                                    name="staff_id"
                                    id="staff_id"
                                    x-ref="staffSel"
                                    class="block w-full appearance-none rounded-lg border border-red-800/80 bg-red-950/50 px-3 py-2.5 text-sm text-gray-100 shadow-inner focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/40"
                                    required
                                >
                                    <option value="">Choisir…</option>
                                    @foreach ($staffList as $staff)
                                        <option value="{{ $staff->id }}" @selected(old('staff_id') == $staff->id)>{{ $staff->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="pin_code" class="mb-1 block text-sm font-bold text-red-100">Code PIN (4 chiffres)</label>
                                <input
                                    type="password"
                                    name="pin_code"
                                    id="pin_code"
                                    x-ref="pinInp"
                                    inputmode="numeric"
                                    maxlength="4"
                                    pattern="[0-9]{4}"
                                    autocomplete="one-time-code"
                                    required
                                    placeholder="••••"
                                    class="block w-full rounded-lg border border-red-800/80 bg-red-950/50 px-3 py-2.5 text-center font-mono text-lg tracking-widest text-white placeholder:text-red-800 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/40"
                                >
                            </div>
                            <div class="grid grid-cols-2 gap-2 pt-1">
                                <button
                                    type="button"
                                    class="rounded-xl border-2 border-gray-600 bg-gradient-to-b from-gray-700 to-gray-900 px-3 py-2.5 text-sm font-bold text-gray-100 shadow-[0_4px_0_0_#374151] active:translate-y-0.5 active:shadow-none"
                                    @click="closeBossRoom()"
                                >
                                    Retour
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl border-2 border-amber-500 bg-gradient-to-b from-amber-500 to-orange-700 px-3 py-2.5 text-sm font-black text-gray-950 shadow-[0_4px_0_0_#9a3412] active:translate-y-0.5 active:shadow-[0_2px_0_0_#9a3412]"
                                    @click="advanceToOath()"
                                >
                                    Continuer
                                </button>
                            </div>
                        </div>

                        <div x-show="authStep === 2" x-cloak class="space-y-4 border-t border-red-900/80 pt-4">
                            <p class="text-center text-xs font-semibold uppercase tracking-wide text-amber-200/90">
                                Étape 2 — Serment
                            </p>
                            <div class="rounded-xl border border-red-900/60 bg-black/40 p-4 text-left shadow-inner">
                                <p class="text-sm sm:text-base leading-snug font-bold text-amber-100">
                                    Moi, <span class="text-red-300 font-black" x-text="staffOathName"></span>, je certifie et assume cette fermeture.
                                </p>
                            </div>
                       
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-red-800/60 bg-red-950/30 p-3">
                                <input type="checkbox" x-model="oathChecked" class="mt-1 h-5 w-5 shrink-0 rounded border-red-600 text-amber-500 focus:ring-amber-500">
                                <span class="text-sm font-bold leading-snug text-red-100">Je le jure</span>
                            </label>
                            <button
                                type="submit"
                                :disabled="!oathChecked"
                                class="w-full rounded-xl border-2 border-emerald-600 bg-gradient-to-b from-emerald-500 to-emerald-800 px-4 py-3.5 text-center font-['Press_Start_2P'] text-[8px] font-bold leading-relaxed text-white shadow-[0_6px_0_0_#14532d] enabled:hover:brightness-110 enabled:active:translate-y-1 enabled:active:shadow-[0_3px_0_0_#14532d] disabled:cursor-not-allowed disabled:opacity-40 disabled:grayscale sm:text-[9px]"
                            >
                                CONFIRMER LA FERMETURE
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
