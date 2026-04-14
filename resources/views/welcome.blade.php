@extends('layouts.app')

@section('title', "Portail d'exploitation — ".config('app.name'))

@section('content')
<div
    x-data="{
        openMyPageModal: @js(request()->boolean('open_mypage')),
        absentModalOpen: false,
        absentModalName: '',
        absentModalDates: [],
        openAbsentModal(name, dates) {
            this.absentModalName = name;
            this.absentModalDates = dates;
            this.absentModalOpen = true;
        }
    }"
    class="min-h-screen bg-gray-50 text-gray-900 antialiased dark:bg-gray-900 dark:text-gray-100"
>
    <x-client-nav />

    <main class="mx-auto w-full max-w-5xl px-3 py-3">
        @if (session('error'))
            <div class="mb-2 rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">{{ session('error') }}</div>
        @endif

        <section class="mb-2 rounded-2xl border-2 border-black bg-gradient-to-r from-red-600 via-orange-500 to-amber-300 p-0.5 shadow-[0_6px_0_0_rgba(0,0,0,1)]">
            <div class="rounded-[14px] bg-black/90 px-3 py-2">
                <p class="text-sm font-black tracking-[0.14em] text-yellow-200">BATTLE READY BUSINESS MENU</p>
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

<!-- 
            <a href="{{ route('inventory.index') }}" class="group rounded-xl border-2 border-black bg-gradient-to-br from-cyan-400 to-sky-300 p-2 shadow-[0_5px_0_0_rgba(0,0,0,1)] transition hover:-translate-y-0.5 active:translate-y-1 active:shadow-none">
                <p class="text-lg">📦</p>
                <p class="text-sm font-black tracking-wide text-black">Inventaire</p>
                <p class="text-xs font-semibold text-black/75">Saisie du stock</p>
            </a> -->

            <a href="{{ route('close-check.index') }}" class="group rounded-xl border-2 border-black bg-gradient-to-br from-rose-500 to-red-400 p-2 shadow-[0_5px_0_0_rgba(0,0,0,1)] transition hover:-translate-y-0.5 active:translate-y-1 active:shadow-none">
                <p class="text-lg">🔒</p>
                <p class="text-sm font-black tracking-wide text-white">Close check</p>
                <p class="text-xs font-semibold text-white/80">Vérification de fin de service</p>
            </a>

            <a href="{{ route('daily-close') }}" class="group rounded-xl border-2 border-black bg-gradient-to-br from-fuchsia-600 to-violet-500 p-2 shadow-[0_5px_0_0_rgba(0,0,0,1)] transition hover:-translate-y-0.5 active:translate-y-1 active:shadow-none">
                <p class="text-lg">💰</p>
                <p class="text-sm font-black tracking-wide text-white">Clôture caisse</p>
                <p class="text-xs font-semibold text-white/80">Saisie caisse & PIN responsable</p>
            </a>


            <a href="{{ url('/admin') }}" class="rounded-xl border-2 border-black bg-gradient-to-br from-slate-800 to-slate-600 p-2 shadow-[0_5px_0_0_rgba(0,0,0,1)] transition hover:-translate-y-0.5 active:translate-y-1 active:shadow-none">
                <p class="text-lg">⚙️</p>
                <p class="text-sm font-black tracking-wide text-white">Administration</p>
                <p class="text-xs font-semibold text-slate-300">Parametrage</p>
            </a>

            <!-- <div class="rounded-xl border-2 border-dashed border-slate-400 bg-white/70 p-2">
                <p class="text-lg">🧾</p>
                <p class="text-sm font-black tracking-wide text-slate-700">Rapport journalier</p>
                <p class="text-xs font-semibold text-slate-500">Bientot disponible</p>
            </div> -->

            <!-- <div class="rounded-xl border-2 border-dashed border-slate-400 bg-white/70 p-2">
                <p class="text-lg">📣</p>
                <p class="text-sm font-black tracking-wide text-slate-700">Messages</p>
                <p class="text-xs font-semibold text-slate-500">Bientot disponible</p>
            </div> -->
        </section>
    </main>

    @include('welcome.partials.today-shift-roster')

    {{-- ── 勤怠ガント（今月 遅刻・欠勤） ────────────────────────────────── --}}
    <section class="mx-auto mt-4 w-full max-w-5xl px-3">
        <div class="overflow-hidden rounded-xl border-2 border-black shadow-[4px_4px_0_0_rgba(0,0,0,1)]">

            {{-- ヘッダー（黄帯・カプコン風） --}}
            <div class="flex items-center justify-between border-b-2 border-black bg-amber-400 px-3 py-1.5">
                <div class="flex items-center gap-2">
                    <span class="font-mono text-[11px] font-black uppercase tracking-[0.22em] text-black">★ PERFORMANCE BULLETIN</span>
                    <span class="rounded border border-black/20 bg-white/50 px-1.5 py-0.5 font-mono text-[10px] font-black text-black">{{ $ganttMonthLabel }}</span>
                </div>
                <span class="font-mono text-[10px] font-bold uppercase tracking-wider text-black/60">Retard · Absence</span>
            </div>

            {{-- ボディ（白地） --}}
            @if ($ganttProblematic->isEmpty() && $ganttBravo->isEmpty())
                <div class="bg-white px-4 py-5 text-center font-mono text-sm text-gray-400">Aucune donnee ce mois.</div>
            @else
                <div class="divide-y divide-gray-100 bg-white">

                    {{-- 遅刻・欠勤ありのスタッフ --}}
                    @foreach ($ganttProblematic as $row)
                        @php
                            $lateW   = $ganttMaxVal > 0 ? round($row['late']   / $ganttMaxVal * 100) : 0;
                            $absentW = $ganttMaxVal > 0 ? round($row['absent'] / $ganttMaxVal * 100) : 0;
                        @endphp
                        <div class="flex items-center gap-3 px-3 py-2">
                            {{-- 名前・カウント列 --}}
                            <div class="w-28 shrink-0">
                                @if ($row['absent'] > 0)
                                    <button
                                        type="button"
                                        @click="openAbsentModal(@js($row['staff']->name), @js($row['absent_dates']))"
                                        class="w-full truncate text-left font-mono text-[11px] font-black text-rose-700 underline decoration-dotted hover:text-rose-900"
                                        title="{{ $row['staff']->name }} — cliquez pour voir les absences"
                                    >{{ $row['staff']->name }}</button>
                                @else
                                    <div class="truncate font-mono text-[12px] font-black text-gray-900" title="{{ $row['staff']->name }}">{{ $row['staff']->name }}</div>
                                @endif
                                <div class="font-mono text-[11px] font-semibold text-blue-500">Rtrd:{{ $row['late'] }} Abst:{{ $row['absent'] }}</div>
                            </div>
                            {{-- バー列 --}}
                            <div class="flex flex-1 flex-col gap-1">
                                {{-- 遅刻バー --}}
                                @if ($row['late'] > 0)
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-10 shrink-0 font-mono text-[10px] font-black uppercase tracking-wide text-amber-700">Retard</span>
                                        <div class="relative h-4 flex-1 overflow-hidden rounded border border-amber-200 bg-amber-50">
                                            <div class="h-full bg-amber-400" style="width: {{ $lateW }}%"></div>
                                            <span class="absolute inset-y-0 right-1.5 flex items-center font-mono text-[9px] font-black text-amber-900">{{ $row['late'] }}x</span>
                                        </div>
                                    </div>
                                @endif
                                {{-- 欠勤バー --}}
                                @if ($row['absent'] > 0)
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-10 shrink-0 font-mono text-[10px] font-black uppercase tracking-wide text-rose-600">Absent</span>
                                        <div class="relative h-4 flex-1 overflow-hidden rounded border border-rose-200 bg-rose-50">
                                            <div class="h-full bg-rose-400" style="width: {{ $absentW }}%"></div>
                                            <span class="absolute inset-y-0 right-1.5 flex items-center font-mono text-[9px] font-black text-rose-900">{{ $row['absent'] }}x</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    {{-- Bravo 枠 --}}
                    @if ($ganttBravo->isNotEmpty())
                        <div class="border-t-2 border-black bg-emerald-50 px-3 py-2">
                            <div class="mb-1.5 flex items-center gap-1.5">
                                <span class="font-mono text-[10px] font-black uppercase tracking-[0.2em] text-emerald-700">★ PERFECT ATTENDANCE</span>
                                <span class="rounded border border-emerald-400 bg-emerald-100 px-1.5 py-0.5 font-mono text-[9px] font-bold text-emerald-700">{{ $ganttBravo->count() }} staff</span>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($ganttBravo as $br)
                                    <span class="inline-flex items-center gap-0.5 rounded border border-emerald-400 bg-white px-2 py-0.5 font-mono text-[10px] font-semibold text-emerald-800">
                                        <span class="text-emerald-500">✓</span>
                                        {{ $br['staff']->name }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- 全員 0 の場合 --}}
                    @if ($ganttProblematic->isEmpty())
                        <div class="bg-white py-2 text-center font-mono text-[11px] font-black uppercase tracking-widest text-emerald-600">
                            ★ PERFECT MONTH — AUCUNE ANOMALIE ★
                        </div>
                    @endif

                </div>
            @endif
        </div>
    </section>

    {{-- ── TODAY'S TIP APPLICANTS ────────────────────────────────────────── --}}
    <section class="mx-auto mt-4 w-full max-w-5xl px-3">
        <div class="overflow-hidden rounded-xl border-2 border-black shadow-[4px_4px_0_0_rgba(0,0,0,1)]">

            {{-- ヘッダー（アンバー帯） --}}
            <div class="flex items-center justify-between border-b-2 border-black bg-amber-400 px-3 py-1.5">
                <div class="flex items-center gap-2">
                    <span class="font-mono text-[11px] font-black uppercase tracking-[0.22em] text-black">🪙 TODAY'S TIP APPLICANTS</span>
                    <span class="rounded border border-black/20 bg-white/50 px-1.5 py-0.5 font-mono text-[10px] font-black text-black">{{ \App\Support\BusinessDate::current()->format('m/d') }}</span>
                </div>
                <span class="font-mono text-[9px] font-semibold text-black/60">Filament 勤怠</span>
            </div>

            {{-- ボディ --}}
            <div class="bg-white px-3 py-2">
                {{-- Lunch 行 --}}
                <div class="mb-1 flex items-center gap-2">
                    <span class="w-14 shrink-0 font-mono text-[10px] font-black uppercase tracking-wide text-amber-600">🍽 Lunch</span>
                    @if ($tipLunchAppliers->isNotEmpty())
                        <div class="flex flex-wrap gap-1">
                            @foreach ($tipLunchAppliers as $s)
                                <span class="inline-flex items-center gap-0.5 rounded border border-amber-400 bg-white px-2 py-0.5 font-mono text-[10px] font-semibold text-amber-800">
                                    <span class="text-amber-500">🪙</span>{{ $s->name }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        <span class="font-mono text-[10px] text-slate-400">—</span>
                    @endif
                </div>
                {{-- Dinner 行 --}}
                <div class="flex items-center gap-2">
                    <span class="w-14 shrink-0 font-mono text-[10px] font-black uppercase tracking-wide text-amber-600">🌙 Dinner</span>
                    @if ($tipDinnerAppliers->isNotEmpty())
                        <div class="flex flex-wrap gap-1">
                            @foreach ($tipDinnerAppliers as $s)
                                <span class="inline-flex items-center gap-0.5 rounded border border-amber-400 bg-white px-2 py-0.5 font-mono text-[10px] font-semibold text-amber-800">
                                    <span class="text-amber-500">🪙</span>{{ $s->name }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        <span class="font-mono text-[10px] text-slate-400">—</span>
                    @endif
                </div>
            </div>

        </div>
    </section>

    {{-- ── Notes / News (過去5日) ──────────────────────────────────────── --}}
    <section class="mx-auto mt-4 w-full max-w-5xl px-3 pb-4">
        <div class="rounded-2xl border-2 border-black bg-white shadow-[0_4px_0_0_rgba(0,0,0,1)] overflow-hidden">
            <div class="flex items-center justify-between border-b-2 border-black bg-slate-900 px-3 py-2">
                <div class="flex items-center gap-2">
                    <span class="text-base">📰</span>
                    <span class="text-xs font-black uppercase tracking-widest text-yellow-300">Info du jour</span>
                    <span class="rounded-full bg-slate-700 px-1.5 py-0.5 text-[9px] font-semibold text-slate-400">5 derniers jours</span>
                </div>
            </div>
            @if ($recentNews->isEmpty())
                <p class="px-4 py-4 text-center text-xs text-slate-500">Aucune note pour les 5 derniers jours.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[28rem] border-collapse text-xs">
                        <thead class="bg-slate-50">
                            <tr class="border-b border-slate-200">
                                <th class="whitespace-nowrap px-3 py-1.5 text-left font-bold text-slate-500">Date</th>
                                <th class="px-3 py-1.5 text-left font-bold text-slate-500">Titre</th>
                                <th class="px-3 py-1.5 text-left font-bold text-slate-500">Contenu</th>
                                <th class="whitespace-nowrap px-3 py-1.5 text-left font-bold text-slate-500">Par</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($recentNews as $news)
                                <tr class="hover:bg-amber-50/50 transition-colors">
                                    <td class="whitespace-nowrap px-3 py-1.5 font-mono text-[10px] text-slate-500">
                                        {{ $news->posted_date->format('m/d') }}
                                    </td>
                                    <td class="px-3 py-1.5 font-semibold text-slate-900 max-w-[12rem] truncate">
                                        {{ $news->title }}
                                    </td>
                                    <td class="px-3 py-1.5 text-slate-700 max-w-[24rem]">
                                        <span class="line-clamp-2 whitespace-pre-wrap leading-relaxed">{{ $news->body }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-1.5 text-[10px] text-slate-500">
                                        {{ $news->staff?->name ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

    <footer class="mt-3 border-t border-slate-300 py-2 text-center text-xs font-medium text-slate-500">
        &copy; {{ date('Y') }} {{ config('app.name') }} System.
    </footer>

    {{-- ── Note ボタン（右下）+ PIN 認証モーダル ─────────────────────────── --}}
    <div
        x-data="{ openNoteModal: false }"
        class="fixed bottom-4 right-4 z-40"
    >
        {{-- Note ボタン --}}
        <button
            type="button"
            @click="openNoteModal = true"
            class="rounded-full border border-slate-400 bg-white/80 px-3 py-1.5 text-[11px] font-semibold text-slate-600 shadow-sm backdrop-blur hover:bg-white hover:text-slate-900 transition"
            title="Gestion des notes"
        >📝 Note</button>

        {{-- PIN モーダル --}}
        <div
            x-show="openNoteModal"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
            @click.self="openNoteModal = false"
        >
            <div class="w-full max-w-sm rounded-2xl border-4 border-black bg-white p-5 shadow-[0_10px_0_0_rgba(0,0,0,1)]">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-black tracking-wide text-slate-900">Authentification Note</h2>
                        <p class="mt-0.5 text-[11px] text-slate-500">Manager ou Niveau {{ 4 }}+ requis.</p>
                    </div>
                    <button type="button" @click="openNoteModal = false" class="text-slate-400 hover:text-slate-700 text-xl leading-none">&times;</button>
                </div>
                <form method="POST" action="{{ route('news.auth') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label for="note_staff_id" class="mb-1 block text-xs font-black text-slate-800">Personnel</label>
                        <div class="relative">
                            <select id="note_staff_id" name="staff_id" required class="block w-full appearance-none rounded-lg border-2 border-black bg-white px-3 py-2.5 pr-9 text-sm font-semibold text-slate-900">
                                <option value="">Veuillez selectionner</option>
                                @foreach ($mypageStaffList as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-700">▾</span>
                        </div>
                    </div>
                    <div>
                        <label for="note_pin" class="mb-1 block text-xs font-black text-slate-800">PIN (4 chiffres)</label>
                        <input
                            id="note_pin"
                            type="password"
                            name="pin_code"
                            required
                            maxlength="4"
                            pattern="[0-9]*"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            placeholder="••••"
                            class="block w-full rounded-lg border-2 border-black px-3 py-2.5 text-center font-mono text-lg font-bold tracking-[0.25em] text-slate-900"
                        >
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" @click="openNoteModal = false" class="rounded-lg border-2 border-slate-300 px-2 py-2 text-sm font-bold text-slate-700">Annuler</button>
                        <button type="submit" class="rounded-lg border-2 border-black bg-amber-400 px-2 py-2 text-sm font-black text-black shadow-[0_4px_0_0_rgba(0,0,0,1)] active:translate-y-1 active:shadow-none">Entrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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

    {{-- 欠勤日詳細 モーダル --}}
    <div
        x-show="absentModalOpen"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
        @click.self="absentModalOpen = false"
    >
        <div class="w-full max-w-sm rounded-2xl border-4 border-rose-700 bg-white p-5 shadow-[0_10px_0_0_rgba(0,0,0,1)]">
            <div class="mb-3 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-black tracking-wide text-slate-900" x-text="absentModalName + ' — Absences'"></h2>
                    <p class="mt-0.5 text-[11px] text-slate-500">Jours sans pointage (shift prevu)</p>
                </div>
                <button type="button" @click="absentModalOpen = false" class="text-slate-400 hover:text-slate-700 text-xl leading-none">&times;</button>
            </div>
            <div class="max-h-64 overflow-y-auto">
                <template x-if="absentModalDates.length === 0">
                    <p class="py-4 text-center text-xs text-slate-400">Aucune absence enregistree.</p>
                </template>
                <template x-if="absentModalDates.length > 0">
                    <ul class="divide-y divide-slate-100">
                        <template x-for="d in absentModalDates" :key="d">
                            <li class="flex items-center gap-2 px-1 py-1.5">
                                <span class="inline-block h-1.5 w-1.5 rounded-full bg-rose-400"></span>
                                <span class="font-mono text-xs font-semibold text-slate-700" x-text="d"></span>
                            </li>
                        </template>
                    </ul>
                </template>
            </div>
            <div class="mt-3 text-right">
                <button type="button" @click="absentModalOpen = false" class="rounded-lg border-2 border-slate-300 px-4 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50">Fermer</button>
            </div>
        </div>
    </div>

</div>
@endsection
