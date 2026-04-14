@props([
    'variant' => 'light',
    'showLogout' => false,
])

@php $isDark = $variant === 'dark'; @endphp

<nav x-data="{ open: false }" class="sticky top-0 z-40 border-b-4 border-black bg-gradient-to-r from-slate-950 via-indigo-900 to-slate-950 text-white">
    <div class="mx-auto flex w-full max-w-6xl items-center justify-between px-3 py-2.5">
        <a href="{{ route('home') }}" class="text-lg font-black tracking-wide text-amber-300">{{ config('app.name', 'So-biz') }}</a>
        <div class="hidden items-center gap-2 md:flex">
            <a href="{{ route('home') }}" class="rounded-md border border-white/30 bg-white/10 px-3 py-1.5 text-sm font-bold">Accueil</a>
            <a href="{{ route('timecard.index') }}" class="rounded-md border border-white/30 bg-white/10 px-3 py-1.5 text-sm font-bold">Pointage</a>
            <a href="{{ route('mypage.reauth') }}" class="rounded-md border border-white/30 bg-white/10 px-3 py-1.5 text-sm font-bold">Mon espace</a>
            <a href="{{ route('inventory.index') }}" class="rounded-md border border-white/30 bg-white/10 px-3 py-1.5 text-sm font-bold">Inventory</a>
            <a href="{{ route('close-check.index') }}" class="rounded-md border border-white/30 bg-white/10 px-3 py-1.5 text-sm font-bold">Clôture tâches</a>
            @if ($showLogout)
                <a href="{{ route('timecard.index') }}" class="rounded-md border border-rose-200/60 bg-rose-500/80 px-2 py-1 text-xs font-black">Deconnexion</a>
            @endif
        </div>
        <button type="button" class="rounded-md border border-white/30 bg-white/10 px-2 py-1 text-sm md:hidden" @click="open = !open" :aria-expanded="open ? 'true' : 'false'" aria-label="Ouvrir le menu">
            ☰
        </button>
    </div>
    <div x-show="open" x-cloak class="border-t border-white/20 bg-slate-900/95 px-3 py-2 md:hidden">
        <div class="grid grid-cols-2 gap-2">
            <a href="{{ route('home') }}" class="rounded-md border border-white/30 bg-white/10 px-2 py-1.5 text-center text-sm font-bold">Accueil</a>
            <a href="{{ route('timecard.index') }}" class="rounded-md border border-white/30 bg-white/10 px-2 py-1.5 text-center text-sm font-bold">Pointage</a>
            <a href="{{ route('mypage.reauth') }}" class="rounded-md border border-white/30 bg-white/10 px-2 py-1.5 text-center text-sm font-bold">Mon espace</a>
            <a href="{{ route('inventory.index') }}" class="rounded-md border border-white/30 bg-white/10 px-2 py-1.5 text-center text-sm font-bold">Inventory</a>
            <a href="{{ route('close-check.index') }}" class="rounded-md border border-white/30 bg-white/10 px-2 py-1.5 text-center text-sm font-bold">Clôture tâches</a>
            @if ($showLogout)
                <a href="{{ route('timecard.index') }}" class="col-span-2 rounded-md border border-rose-200/60 bg-rose-500/80 px-2 py-1.5 text-center text-sm font-black">Deconnexion</a>
            @endif
        </div>
    </div>
</nav>
