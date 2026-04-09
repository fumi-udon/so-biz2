@extends('layouts.app')

@section('title', 'Clôture des tâches — '.config('app.name', 'Laravel'))

@section('content')
<div class="min-h-screen bg-zinc-100 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
    <x-client-nav />

    <div
        x-data="{
            openApproval: false,
            authStep: 1,
            oathChecked: false,
            staffOathName: '',
            checked: {},
            initChecks(ids) { ids.forEach(id => this.checked[id] = false) },
            allChecked(ids) { return ids.length > 0 && ids.every(id => this.checked[id] === true) },
            openApprovalModal() {
                this.openApproval = true;
                this.authStep = 1;
                this.oathChecked = false;
                this.staffOathName = '';
            },
            closeApprovalModal() {
                this.openApproval = false;
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
        x-init='initChecks(@json($tasks->pluck("id")->values()))'
        class="mx-auto w-full max-w-6xl px-3 py-3"
    >
        <header class="mb-4 rounded-lg border border-zinc-700 bg-zinc-900 p-4 shadow-inner dark:border-zinc-600">
            <p class="font-mono text-[10px] uppercase tracking-widest text-amber-400 sm:text-xs">Système — clôture des tâches</p>
            <h1 class="mt-2 font-mono text-lg font-bold uppercase tracking-widest text-zinc-100 sm:text-xl">Clôture des tâches</h1>
            <p class="mt-2 text-sm text-zinc-300">
                Vérification avant fermeture. Cochez chaque point, puis validez avec le responsable.
            </p>
        </header>

        @if (! empty($incompleteLines))
            <div class="mb-3 rounded-lg border border-rose-800/60 bg-rose-950/30 p-3 text-sm text-rose-100 dark:border-rose-800 dark:bg-rose-950/40">
                <p class="mb-2 font-mono text-xs font-bold uppercase tracking-widest text-rose-300">Alerte inventaire</p>
                <ul class="list-disc space-y-1 pl-5 text-rose-50">
                    @foreach ($incompleteLines as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
                <p class="mt-2 font-semibold text-rose-100">
                    Terminez depuis Mon espace avant la clôture finale.
                </p>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-3 rounded-lg border border-rose-700 bg-rose-950/40 p-3 text-sm font-semibold text-rose-100">
                {{ session('error') }}
            </div>
        @endif

        @if ($tasks->isEmpty())
            <p class="rounded-lg border border-dashed border-zinc-500 bg-zinc-50 p-8 text-center text-sm text-zinc-600 dark:bg-zinc-900 dark:text-zinc-400">
                Aucune tâche active enregistrée.
            </p>
        @else
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($tasks as $task)
                    <article
                        class="overflow-hidden rounded-xl border border-zinc-300 shadow-inner transition-colors dark:border-zinc-700"
                        :class="checked[{{ $task->id }}] ? 'border-l-4 border-emerald-500 bg-emerald-50/50 text-zinc-900 opacity-90 dark:bg-emerald-900/20 dark:text-zinc-100' : 'border-l-4 border-amber-500 bg-zinc-50 text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100'"
                    >
                        <div class="flex items-stretch">
                            <div class="w-28 shrink-0 bg-zinc-200 dark:bg-zinc-800">
                                @if ($task->image_path)
                                    <img src="{{ asset('storage/'.$task->image_path) }}" alt="" class="aspect-video h-full w-full object-cover">
                                @else
                                    <div class="flex h-full min-h-[5rem] items-center justify-center text-2xl text-zinc-500">🖼️</div>
                                @endif
                            </div>
                            <div class="flex grow flex-col p-3">
                                <h2 class="text-base font-bold text-zinc-900 dark:text-white">{{ $task->title }}</h2>
                                @if ($task->description)
                                    <p class="mt-1 line-clamp-4 text-sm text-zinc-600 dark:text-zinc-300">{{ $task->description }}</p>
                                @endif
                            </div>
                        </div>
                        <label class="flex items-center gap-2 border-t border-zinc-300 px-3 py-2 text-sm font-semibold text-zinc-800 dark:border-zinc-600 dark:text-zinc-200">
                            <input type="checkbox" x-model="checked[{{ $task->id }}]" class="h-5 w-5 rounded border-zinc-400 text-emerald-600 focus:ring-emerald-500 dark:border-zinc-500">
                            <span x-show="checked[{{ $task->id }}]" x-cloak>✅</span>
                            <span>Vu / OK</span>
                        </label>
                    </article>
                @endforeach
            </div>

            @php $blocked = count($incompleteLines ?? []) > 0; @endphp
            <div class="mt-5">
                <button
                    type="button"
                    @click="openApprovalModal()"
                    :disabled="{{ $blocked ? 'true' : 'false' }} || !allChecked(@json($tasks->pluck('id')->values()))"
                    class="inline-flex w-full items-center justify-center rounded-lg border border-amber-600 bg-amber-600 px-4 py-3 font-mono text-sm font-bold uppercase tracking-widest text-zinc-950 border-b-4 border-b-amber-900 transition enabled:hover:brightness-105 enabled:active:border-b-0 enabled:active:translate-y-1 disabled:cursor-not-allowed disabled:opacity-50 disabled:active:translate-y-0 dark:border-amber-500 dark:bg-amber-500 dark:border-b-amber-800"
                >
                    Valider et continuer
                </button>
            </div>

            <div x-show="openApproval" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/90 p-4 backdrop-blur-sm" @click.self="closeApprovalModal()">
                <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-lg border border-zinc-700 bg-zinc-900 p-4 shadow-inner">
                    <div class="mb-4 flex items-start justify-between gap-2 border-b border-zinc-700 pb-3">
                        <div>
                            <p class="font-mono text-[10px] uppercase tracking-widest text-amber-400 sm:text-xs">Warning — canal sécurisé</p>
                            <h2 class="mt-1 font-mono text-sm font-bold uppercase tracking-widest text-zinc-100 sm:text-base">Validation responsable</h2>
                            <p class="mt-1 font-mono text-[9px] uppercase tracking-widest text-zinc-500" x-show="authStep === 1" x-cloak>Étape 1 — identité</p>
                            <p class="mt-1 font-mono text-[9px] uppercase tracking-widest text-zinc-500" x-show="authStep === 2" x-cloak>Étape 2 — serment</p>
                        </div>
                        <button type="button" @click="closeApprovalModal()" class="rounded border border-zinc-600 bg-zinc-800 px-2 py-1 font-mono text-xs text-zinc-200 border-b-4 border-b-black active:border-b-0 active:translate-y-0.5">✕</button>
                    </div>
                    <form x-ref="closeCheckForm" action="{{ route('close-check.process') }}" method="post" class="space-y-3">
                        @csrf
                        <div x-show="authStep === 1" x-cloak class="space-y-3">
                            <div>
                                <label for="staff_id" class="mb-1 block font-mono text-xs font-bold uppercase tracking-wider text-zinc-300">Responsable du jour</label>
                                <select name="staff_id" id="staff_id" x-ref="staffSel" class="block w-full appearance-none rounded border border-zinc-600 bg-zinc-950 px-3 py-2.5 text-sm text-zinc-100 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500/40" required>
                                    <option value="">Choisir…</option>
                                    @foreach ($staffList as $staff)
                                        <option value="{{ $staff->id }}" @selected(old('staff_id') == $staff->id)>{{ $staff->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="pin_code" class="mb-1 block font-mono text-xs font-bold uppercase tracking-wider text-zinc-300">Code PIN (4 chiffres)</label>
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
                                    class="block w-full rounded border border-zinc-600 bg-zinc-950 px-3 py-2.5 text-center font-mono text-lg tracking-widest text-zinc-100 placeholder:text-zinc-600 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500/40"
                                >
                            </div>
                            <div class="grid grid-cols-2 gap-2 pt-1">
                                <button type="button" @click="closeApprovalModal()" class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2.5 font-mono text-xs font-semibold uppercase tracking-wider text-zinc-100 border-b-4 border-b-black active:border-b-0 active:translate-y-1">Retour</button>
                                <button type="button" @click="advanceToOath()" class="rounded-lg border border-amber-600 bg-amber-600 px-3 py-2.5 font-mono text-xs font-bold uppercase tracking-wider text-zinc-950 border-b-4 border-b-amber-900 active:border-b-0 active:translate-y-1 dark:border-amber-500 dark:bg-amber-500 dark:border-b-amber-800">Continuer</button>
                            </div>
                        </div>

                        <div x-show="authStep === 2" x-cloak class="space-y-4 border-t border-zinc-700 pt-4">
                            <div class="rounded border border-zinc-600 bg-zinc-950/80 p-3 shadow-inner">
                                <p class="font-mono text-[10px] uppercase leading-relaxed tracking-wide text-zinc-200 sm:text-[9px]">
                                    Moi, <span class="normal-case text-amber-500" x-text="staffOathName"></span>, je certifie sur l'honneur que toutes les tâches de fermeture ont été accomplies. Je prends l'entière responsabilité de l'état du restaurant.
                                </p>
                            </div>
                            <label class="flex cursor-pointer items-start gap-3 rounded border border-zinc-600 bg-zinc-950/50 p-3">
                                <input type="checkbox" x-model="oathChecked" class="mt-0.5 h-5 w-5 shrink-0 rounded border-zinc-500 text-emerald-600 focus:ring-emerald-500">
                                <span class="font-mono text-xs font-bold uppercase tracking-wider text-zinc-200">JE LE JURE</span>
                            </label>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <button type="button" @click="authStep = 1; oathChecked = false" class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2.5 font-mono text-xs font-semibold uppercase tracking-wider text-zinc-100 border-b-4 border-b-black active:border-b-0 active:translate-y-1">Précédent</button>
                                <button
                                    type="submit"
                                    :disabled="!oathChecked"
                                    class="rounded-lg border border-emerald-600 bg-emerald-700 px-3 py-2.5 font-mono text-xs font-bold uppercase tracking-wider text-white border-b-4 border-b-emerald-950 shadow-inner enabled:hover:brightness-105 enabled:active:border-b-0 enabled:active:translate-y-1 disabled:cursor-not-allowed disabled:opacity-40 disabled:grayscale dark:border-emerald-500 dark:bg-emerald-600"
                                >
                                    Confirmer la fermeture
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
