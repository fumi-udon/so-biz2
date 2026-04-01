<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pointage — {{ config('app.name', 'Laravel') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>[x-cloak]{display:none!important;}</style>
    @filamentStyles
</head>
<body x-data="{ openMyPageModal: false }" class="min-h-screen bg-slate-100 text-gray-900 antialiased">
    <header class="border-b-4 border-black bg-gradient-to-r from-slate-900 via-indigo-900 to-slate-900 text-white">
        <div class="mx-auto flex w-full max-w-5xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="text-base font-black tracking-[0.18em] text-amber-300 drop-shadow">SOYA BIZ</a>
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
    <div class="mx-auto w-full max-w-4xl px-4 py-4 sm:px-6 lg:px-8">
        {{ $slot }}
    </div>
    <footer class="py-3 text-center text-xs text-gray-500">
        {{ config('app.name') }}
    </footer>

    @php
        $staffs = \App\Models\Staff::where('is_active', true)->orderBy('name')->get();
    @endphp
    <div
        x-show="openMyPageModal"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
        @click.self="openMyPageModal = false"
    >
        <div class="w-full max-w-md rounded-2xl border-4 border-black bg-white p-5 shadow-[0_12px_0_0_rgba(0,0,0,1)]">
            <h2 class="mb-1 text-xl font-black tracking-wider text-slate-900">Connexion Mon espace</h2>
            <p class="mb-4 text-sm font-semibold text-slate-600">Selectionnez le personnel et saisissez le PIN.</p>
            <p class="-mt-3 mb-4 text-[10px] text-slate-400">本人確認</p>
            <form method="POST" action="{{ route('mypage.open') }}" class="space-y-3">
                @csrf
                <div>
                    <label for="mypage_staff_id" class="mb-1 block text-sm font-black tracking-wide text-slate-800">Personnel</label>
                    <div class="relative">
                        <select
                            id="mypage_staff_id"
                            name="staff_id"
                            required
                            class="block w-full !appearance-none rounded-lg border-2 border-black bg-white !bg-none px-3 py-2.5 pr-10 text-sm font-semibold text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                        >
                            <option value="">Veuillez selectionner</option>
                            @foreach ($staffs as $staff)
                                <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-700">▾</span>
                    </div>
                </div>
                <div>
                    <label for="mypage_pin" class="mb-1 block text-sm font-black tracking-wide text-slate-800">Code PIN</label>
                    <input
                        id="mypage_pin"
                        type="password"
                        name="pin_code"
                        required
                        maxlength="4"
                        pattern="[0-9]*"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        class="block w-full rounded-lg border-2 border-black px-3 py-2.5 text-center font-mono text-lg font-bold tracking-[0.3em] text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                        placeholder="••••"
                    >
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" @click="openMyPageModal = false" class="rounded-lg border-2 border-slate-300 px-3 py-2 text-sm font-bold text-slate-700">
                        Fermer
                    </button>
                    <button type="submit" class="rounded-lg border-2 border-black bg-emerald-400 px-3 py-2 text-sm font-black text-black shadow-[0_4px_0_0_rgba(0,0,0,1)] active:translate-y-1 active:shadow-none">
                        Entrer
                    </button>
                </div>
            </form>
        </div>
    </div>
    @filamentScripts(withCore: true)
</body>
</html>
