@extends('layouts.app')

@section('title', 'Clôture des tâches — '.config('app.name', 'Laravel'))

@section('content')
<div class="min-h-screen bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100">
    <x-client-nav />

    <div
        x-data="{
            openApproval: false,
            checked: {},
            initChecks(ids) { ids.forEach(id => this.checked[id] = false) },
            allChecked(ids) { return ids.length > 0 && ids.every(id => this.checked[id] === true) },
        }"
        x-init='initChecks(@json($tasks->pluck("id")->values()))'
        class="mx-auto w-full max-w-6xl px-3 py-3"
    >
        <header class="mb-4 rounded-2xl border-2 border-black bg-gradient-to-r from-slate-950 via-indigo-900 to-slate-950 p-4 text-white shadow-[0_6px_0_0_rgba(0,0,0,1)]">
            <h1 class="text-2xl font-black tracking-wide">Clôture des tâches</h1>
            <p class="mt-1 text-sm font-semibold text-slate-200">
                Vérification avant fermeture. Cochez chaque point, puis validez avec le responsable.
            </p>
        </header>

        @if (! empty($incompleteLines))
            <div class="mb-3 rounded-xl border border-rose-300 bg-rose-50 p-3 text-sm text-rose-900 dark:border-rose-700 dark:bg-rose-950/40 dark:text-rose-100">
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
            <div class="mb-3 rounded-xl border border-rose-300 bg-rose-50 p-3 text-sm font-semibold text-rose-900 dark:border-rose-700 dark:bg-rose-950/40 dark:text-rose-100">
                {{ session('error') }}
            </div>
        @endif

        @if ($tasks->isEmpty())
            <p class="rounded-xl border-2 border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-600 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300">
                Aucune tâche active enregistrée.
            </p>
        @else
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($tasks as $task)
                    <article class="overflow-hidden rounded-xl border-2 border-black bg-white shadow-[0_5px_0_0_rgba(0,0,0,1)] dark:border-slate-600 dark:bg-slate-900">
                        <div class="flex items-stretch">
                            <div class="w-28 shrink-0 bg-slate-100 dark:bg-slate-800">
                                @if ($task->image_path)
                                    <img src="{{ asset('storage/'.$task->image_path) }}" alt="" class="aspect-video h-full w-full object-cover">
                                @else
                                    <div class="flex h-full items-center justify-center text-2xl text-slate-400">🖼️</div>
                                @endif
                            </div>
                            <div class="flex grow flex-col p-3">
                                <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ $task->title }}</h2>
                                @if ($task->description)
                                    <p class="mt-1 line-clamp-4 text-sm text-slate-600 dark:text-slate-300">{{ $task->description }}</p>
                                @endif
                            </div>
                        </div>
                        <label class="flex items-center gap-2 border-t border-slate-200 px-3 py-2 text-sm font-semibold text-slate-800 dark:border-slate-600 dark:text-slate-200">
                            <input type="checkbox" x-model="checked[{{ $task->id }}]" class="h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 dark:border-slate-500">
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
                    @click="openApproval = true"
                    :disabled="{{ $blocked ? 'true' : 'false' }} || !allChecked(@json($tasks->pluck('id')->values()))"
                    class="inline-flex w-full items-center justify-center rounded-xl border-2 border-black bg-amber-300 px-4 py-3 text-lg font-black text-black shadow-[0_6px_0_0_rgba(0,0,0,1)] enabled:hover:bg-amber-200 enabled:active:translate-y-1 enabled:active:shadow-none disabled:cursor-not-allowed disabled:opacity-50 dark:text-slate-950"
                >
                    Valider et continuer
                </button>
            </div>

            <div x-show="openApproval" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="openApproval = false">
                <div class="w-full max-w-md rounded-xl border-2 border-black bg-white p-4 shadow-[0_8px_0_0_rgba(0,0,0,1)] dark:border-slate-600 dark:bg-slate-900">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-lg font-black text-slate-900 dark:text-white">Validation responsable</h2>
                        <button type="button" @click="openApproval = false" class="rounded border border-slate-300 px-2 py-1 text-sm text-slate-800 dark:border-slate-600 dark:text-slate-200">✕</button>
                    </div>
                    <form action="{{ route('close-check.process') }}" method="post" class="space-y-3">
                        @csrf
                        <div>
                            <label for="staff_id" class="mb-1 block text-sm font-bold text-slate-800 dark:text-slate-200">Responsable du jour</label>
                            <select name="staff_id" id="staff_id" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-800 dark:text-white" required>
                                <option value="">Choisir…</option>
                                @foreach ($staffList as $staff)
                                    <option value="{{ $staff->id }}" @selected(old('staff_id') == $staff->id)>{{ $staff->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="pin_code" class="mb-1 block text-sm font-bold text-slate-800 dark:text-slate-200">Code PIN (4 chiffres)</label>
                            <input
                                type="password"
                                name="pin_code"
                                id="pin_code"
                                inputmode="numeric"
                                maxlength="4"
                                pattern="[0-9]{4}"
                                autocomplete="one-time-code"
                                required
                                placeholder="••••"
                                class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-center font-mono text-lg text-slate-900 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                            >
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" @click="openApproval = false" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-800 dark:border-slate-600 dark:text-slate-200">Retour</button>
                            <button type="submit" class="rounded-lg border-2 border-black bg-indigo-600 px-3 py-2 text-sm font-black text-white dark:border-indigo-500">Clôturer</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
