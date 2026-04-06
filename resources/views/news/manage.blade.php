<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Gestion des notes — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important;}</style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">

    {{-- Navbar --}}
    <nav class="sticky top-0 z-30 border-b-2 border-black bg-slate-900 px-3 py-2">
        <div class="mx-auto flex max-w-4xl items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-lg font-black text-yellow-300">📰</span>
                <span class="text-sm font-black tracking-wide text-white">Gestion des notes</span>
                <span class="rounded-full bg-slate-700 px-2 py-0.5 text-[10px] font-semibold text-slate-300">{{ $editorStaff->name }}</span>
            </div>
            <form method="POST" action="{{ route('news.logout') }}">
                @csrf
                <button type="submit" class="rounded-lg border border-slate-600 bg-slate-800 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-700">
                    Quitter
                </button>
            </form>
        </div>
    </nav>

    <main class="mx-auto w-full max-w-4xl px-3 py-4 space-y-4">

        {{-- Flash messages --}}
        @if (session('status'))
            <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700">
                ✅ {{ session('status') }}
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">
                ⚠️ {{ session('error') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 space-y-0.5">
                @foreach ($errors->all() as $e)
                    <p>{{ $e }}</p>
                @endforeach
            </div>
        @endif

        {{-- ── Formulaire ajout / edition ─────────────────────────────── --}}
        <section
            x-data="{ open: @js($editNote !== null) }"
            class="rounded-2xl border-2 border-black bg-white shadow-[0_5px_0_0_rgba(0,0,0,1)]"
        >
            <button
                type="button"
                @click="open = !open"
                class="flex w-full items-center justify-between px-4 py-3"
            >
                <span class="text-sm font-black tracking-wide text-slate-900">
                    {{ $editNote ? '✏️  Modifier la note #'.$editNote->id : '➕  Ajouter une note' }}
                </span>
                <span x-text="open ? '▲' : '▼'" class="text-xs text-slate-500"></span>
            </button>

            <div x-show="open" x-cloak class="border-t-2 border-black px-4 pb-4 pt-3">
                @if ($editNote)
                    <form method="POST" action="{{ route('news.update', $editNote->id) }}" class="space-y-3">
                        @csrf
                @else
                    <form method="POST" action="{{ route('news.store') }}" class="space-y-3">
                        @csrf
                @endif

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-black text-slate-800">Titre <span class="text-rose-600">*</span></label>
                                <input
                                    type="text"
                                    name="title"
                                    required
                                    maxlength="120"
                                    value="{{ old('title', $editNote?->title ?? '') }}"
                                    placeholder="Titre de la note..."
                                    class="block w-full rounded-lg border-2 border-black px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-amber-500 focus:outline-none"
                                >
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-black text-slate-800">Date <span class="text-rose-600">*</span></label>
                                <input
                                    type="date"
                                    name="posted_date"
                                    required
                                    value="{{ old('posted_date', $editNote?->posted_date?->toDateString() ?? $today) }}"
                                    class="block w-full rounded-lg border-2 border-black px-3 py-2 text-sm text-slate-900 focus:border-amber-500 focus:outline-none"
                                >
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-black text-slate-800">Contenu <span class="text-rose-600">*</span></label>
                            <textarea
                                name="body"
                                required
                                rows="4"
                                maxlength="2000"
                                placeholder="Contenu de la note..."
                                class="block w-full rounded-lg border-2 border-black px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-amber-500 focus:outline-none"
                            >{{ old('body', $editNote?->body ?? '') }}</textarea>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="submit"
                                class="rounded-lg border-2 border-black bg-amber-400 px-5 py-2 text-sm font-black text-black shadow-[0_3px_0_0_rgba(0,0,0,1)] active:translate-y-0.5 active:shadow-none"
                            >
                                {{ $editNote ? 'Mettre a jour' : 'Ajouter' }}
                            </button>
                            @if ($editNote)
                                <a href="{{ route('news.manage') }}" class="rounded-lg border-2 border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600">
                                    Annuler
                                </a>
                            @endif
                        </div>
                    </form>
            </div>
        </section>

        {{-- ── Liste des notes ──────────────────────────────────────────── --}}
        <section class="rounded-2xl border-2 border-black bg-white shadow-[0_5px_0_0_rgba(0,0,0,1)] overflow-hidden">
            <div class="border-b-2 border-black bg-slate-900 px-4 py-2.5">
                <p class="text-xs font-black uppercase tracking-widest text-yellow-300">Toutes les notes</p>
                <p class="text-[10px] text-slate-400">{{ $notes->count() }} note(s) enregistree(s)</p>
            </div>

            @if ($notes->isEmpty())
                <p class="px-4 py-6 text-center text-sm text-slate-500">Aucune note pour le moment.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[36rem] border-collapse text-sm">
                        <thead class="bg-slate-50">
                            <tr class="border-b border-slate-200">
                                <th class="px-3 py-2 text-left text-xs font-bold text-slate-600 whitespace-nowrap">Date</th>
                                <th class="px-3 py-2 text-left text-xs font-bold text-slate-600">Titre</th>
                                <th class="px-3 py-2 text-left text-xs font-bold text-slate-600">Contenu</th>
                                <th class="px-3 py-2 text-left text-xs font-bold text-slate-600 whitespace-nowrap">Auteur</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-slate-600 whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($notes as $note)
                                <tr class="hover:bg-amber-50/60 transition-colors {{ $note->id === ($editNote?->id) ? 'bg-amber-50 ring-1 ring-inset ring-amber-400' : '' }}">
                                    <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-slate-700">
                                        {{ $note->posted_date->format('Y-m-d') }}
                                    </td>
                                    <td class="px-3 py-2 font-semibold text-slate-900 max-w-[14rem] truncate">
                                        {{ $note->title }}
                                    </td>
                                    <td class="px-3 py-2 text-xs text-slate-700 max-w-[22rem]">
                                        <span class="line-clamp-2 whitespace-pre-wrap">{{ $note->body }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-xs text-slate-500">
                                        {{ $note->staff?->name ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-center">
                                        <div class="inline-flex items-center gap-1">
                                            <a
                                                href="{{ route('news.manage', ['edit' => $note->id]) }}"
                                                class="rounded border border-slate-300 bg-white px-2 py-0.5 text-[10px] font-semibold text-slate-700 hover:bg-slate-50"
                                            >Modifier</a>
                                            <form
                                                method="POST"
                                                action="{{ route('news.destroy', $note->id) }}"
                                                onsubmit="return confirm('Supprimer cette note ?')"
                                                class="inline"
                                            >
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="rounded border border-rose-300 bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-700 hover:bg-rose-100"
                                                >Supprimer</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

    </main>
</body>
</html>
