<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portail d'exploitation — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important;}</style>
</head>
<body x-data="{ openMyPageModal: @js(request()->boolean('open_mypage')) }" class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    @php
        $mypageStaffList = \App\Models\Staff::query()->where('is_active', true)->orderBy('name')->get();
    @endphp

    <x-client-nav />

    <main class="mx-auto w-full max-w-5xl px-3 py-3">
        @if (session('error'))
            <div class="mb-2 rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">{{ session('error') }}</div>
        @endif

        <section class="mb-2 rounded-2xl border-2 border-black bg-gradient-to-r from-red-600 via-orange-500 to-amber-300 p-0.5 shadow-[0_6px_0_0_rgba(0,0,0,1)]">
            <div class="rounded-[14px] bg-black/90 px-3 py-2">
                <p class="text-sm font-black tracking-[0.14em] text-yellow-200">BATTLE READY BUSINESS MENU</p>
                <p class="text-xs font-semibold text-slate-300">Lancez les operations essentielles en un geste.</p>
                <p class="text-[10px] text-slate-400">業務開始の入口</p>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-2 lg:grid-cols-4">
            <a href="{{ route('timecard.index') }}" class="group rounded-xl border-2 border-black bg-gradient-to-br from-blue-500 to-cyan-300 p-2 shadow-[0_5px_0_0_rgba(0,0,0,1)] transition hover:-translate-y-0.5 active:translate-y-1 active:shadow-none">
                <p class="text-lg">🕒</p>
                <p class="text-sm font-black tracking-wide text-black">Pointage</p>
                <p class="text-xs font-semibold text-black/75">Enregistrer entree/sortie</p>
            </a>

            <button type="button" @click="openMyPageModal = true" class="group rounded-xl border-2 border-black bg-gradient-to-br from-emerald-400 to-lime-300 p-2 text-left shadow-[0_5px_0_0_rgba(0,0,0,1)] transition hover:-translate-y-0.5 active:translate-y-1 active:shadow-none">
                <p class="text-lg">🪪</p>
                <p class="text-sm font-black tracking-wide text-black">Mon espace</p>
                <p class="text-xs font-semibold text-black/75">Acces par PIN</p>
            </button>

            <a href="{{ route('inventory.index') }}" class="group rounded-xl border-2 border-black bg-gradient-to-br from-cyan-400 to-sky-300 p-2 shadow-[0_5px_0_0_rgba(0,0,0,1)] transition hover:-translate-y-0.5 active:translate-y-1 active:shadow-none">
                <p class="text-lg">📦</p>
                <p class="text-sm font-black tracking-wide text-black">Inventaire</p>
                <p class="text-xs font-semibold text-black/75">Saisie du stock</p>
            </a>

            <a href="{{ route('close-check.index') }}" class="group rounded-xl border-2 border-black bg-gradient-to-br from-rose-500 to-red-400 p-2 shadow-[0_5px_0_0_rgba(0,0,0,1)] transition hover:-translate-y-0.5 active:translate-y-1 active:shadow-none">
                <p class="text-lg">🔒</p>
                <p class="text-sm font-black tracking-wide text-white">Cloture</p>
                <p class="text-xs font-semibold text-white/80">Verification de fin de service</p>
            </a>

            <a href="{{ url('/admin/daily-close-check') }}" class="group rounded-xl border-2 border-black bg-gradient-to-br from-fuchsia-600 to-violet-500 p-2 shadow-[0_5px_0_0_rgba(0,0,0,1)] transition hover:-translate-y-0.5 active:translate-y-1 active:shadow-none">
                <p class="text-lg">💰</p>
                <p class="text-sm font-black tracking-wide text-white">Cloture caisse</p>
                <p class="text-xs font-semibold text-white/80">レジ締め</p>
            </a>

            <a href="{{ route('mypage.attendance') }}" class="rounded-xl border-2 border-black bg-gradient-to-br from-violet-500 to-purple-400 p-2 shadow-[0_5px_0_0_rgba(0,0,0,1)] transition hover:-translate-y-0.5 active:translate-y-1 active:shadow-none">
                <p class="text-lg">📊</p>
                <p class="text-sm font-black tracking-wide text-white">Suivi des heures</p>
                <p class="text-xs font-semibold text-white/80">Consulter les presences</p>
            </a>

            <a href="{{ url('/admin') }}" class="rounded-xl border-2 border-black bg-gradient-to-br from-slate-800 to-slate-600 p-2 shadow-[0_5px_0_0_rgba(0,0,0,1)] transition hover:-translate-y-0.5 active:translate-y-1 active:shadow-none">
                <p class="text-lg">⚙️</p>
                <p class="text-sm font-black tracking-wide text-white">Administration</p>
                <p class="text-xs font-semibold text-slate-300">Parametrage</p>
            </a>

            <div class="rounded-xl border-2 border-dashed border-slate-400 bg-white/70 p-2">
                <p class="text-lg">🧾</p>
                <p class="text-sm font-black tracking-wide text-slate-700">Rapport journalier</p>
                <p class="text-xs font-semibold text-slate-500">Bientot disponible</p>
            </div>

            <div class="rounded-xl border-2 border-dashed border-slate-400 bg-white/70 p-2">
                <p class="text-lg">📣</p>
                <p class="text-sm font-black tracking-wide text-slate-700">Messages</p>
                <p class="text-xs font-semibold text-slate-500">Bientot disponible</p>
            </div>
        </section>
    </main>

    <footer class="mt-3 border-t border-slate-300 py-2 text-center text-xs font-medium text-slate-500">
        &copy; {{ date('Y') }} {{ config('app.name') }} System.
    </footer>

    <div
        x-show="openMyPageModal"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-3"
        @click.self="openMyPageModal = false"
    >
        <div class="w-full max-w-sm rounded-2xl border-4 border-black bg-white p-4 shadow-[0_10px_0_0_rgba(0,0,0,1)]">
            <h2 class="mb-1 text-base font-black tracking-wide text-slate-900">Connexion Mon espace</h2>
            <p class="mb-3 text-sm font-semibold text-slate-600">Selectionnez le personnel puis saisissez le PIN (4 chiffres).</p>
            <p class="mb-3 -mt-2 text-[10px] text-slate-400">本人確認（4桁PIN）</p>
            <form method="POST" action="{{ route('mypage.open') }}" class="space-y-2.5">
                @csrf
                <div>
                    <label for="mypage_modal_staff_id" class="mb-1 block text-xs font-black tracking-wide text-slate-800">Personnel</label>
                    <div class="relative">
                        <select id="mypage_modal_staff_id" name="staff_id" required class="block w-full appearance-none rounded-lg border-2 border-black bg-white px-3 py-2.5 pr-9 text-sm font-semibold text-slate-900">
                            <option value="">Veuillez selectionner</option>
                            @foreach ($mypageStaffList as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-700">▾</span>
                    </div>
                    @if ($mypageStaffList->isEmpty())
                        <p class="mt-1 text-xs font-semibold text-rose-600">Aucun personnel actif disponible.</p>
                    @endif
                </div>
                <div>
                    <label for="mypage_modal_pin" class="mb-1 block text-xs font-black tracking-wide text-slate-800">PIN (4 chiffres)</label>
                    <input id="mypage_modal_pin" type="password" name="pin_code" required maxlength="4" pattern="[0-9]*" inputmode="numeric" autocomplete="one-time-code" placeholder="••••" class="block w-full rounded-lg border-2 border-black px-3 py-2.5 text-center font-mono text-lg font-bold tracking-[0.25em] text-slate-900" @disabled($mypageStaffList->isEmpty())>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" @click="openMyPageModal = false" class="rounded-lg border-2 border-slate-300 px-2 py-2 text-sm font-bold text-slate-700">Fermer</button>
                    <button type="submit" class="rounded-lg border-2 border-black bg-emerald-400 px-2 py-2 text-sm font-black text-black shadow-[0_4px_0_0_rgba(0,0,0,1)] active:translate-y-1 active:shadow-none" @disabled($mypageStaffList->isEmpty())>Entrer</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
