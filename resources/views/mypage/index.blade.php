<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mon espace — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important;}</style>
</head>
<body
    class="min-h-screen bg-gradient-to-br from-pink-100 via-yellow-100 to-cyan-100 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950"
    data-auto-logout-url="{{ route('mypage.auto-logout') }}"
    data-timecard-url="{{ route('timecard.index') }}"
>
    @php
        $roleChipClass = match($roleColor ?? 'gray') {
            'red' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-200',
            'green' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-200',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
        };

        $fmtShift = function (?string $planned, string $status, ?string $actual) use ($statusResolver): string {
            if ($status === 'none') {
                return '—';
            }

            $p = $planned ?: '-';
            $icon = $statusResolver->icon($status);

            return match ($status) {
                'late' => "{$p} ▶ {$icon} Absent/retard",
                'future' => "{$p} ▶ {$icon}",
                default => "{$p} ▶ {$icon} ".($actual ?: '--:--'),
            };
        };

        $inventoryIncomplete = collect($inventoryTimingRows)->contains(fn ($r): bool => ! ($r['complete'] ?? false));
        $levelIcon = match (true) {
            $motivationLevel >= 9 => '👑',
            $motivationLevel >= 7 => '🦄',
            $motivationLevel >= 5 => '🐉',
            $motivationLevel >= 4 => '🔥',
            $motivationLevel >= 3 => '⚡',
            $motivationLevel >= 2 => '🔰',
            default => '🌱',
        };
        $roleIcon = match ($roleColor ?? 'gray') {
            'red' => '🍳',
            'green' => '🛎️',
            default => '🧩',
        };
        $isLateAny = ($lunchStatus ?? 'none') === 'late' || ($dinnerStatus ?? 'none') === 'late';
    @endphp

    <x-client-nav :show-logout="true" />

    <main class="mx-auto w-full max-w-3xl px-2 py-2 sm:px-3">
        @if (session('status'))
            <div class="mb-2 rounded-xl border border-emerald-300 bg-emerald-50 px-2 py-1.5 text-sm text-emerald-700 dark:border-emerald-600/30 dark:bg-emerald-900/20 dark:text-emerald-300">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-2 rounded-xl border border-rose-300 bg-rose-50 px-2 py-1.5 text-sm text-rose-700 dark:border-rose-600/30 dark:bg-rose-900/20 dark:text-rose-300">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-2 rounded-xl border border-rose-300 bg-rose-50 px-2 py-1.5 text-sm text-rose-700 dark:border-rose-600/30 dark:bg-rose-900/20 dark:text-rose-300">{{ $errors->first() }}</div>
        @endif

        @php
            $todayLabel = \App\Support\BusinessDate::current()->format('d/m');
            $todayAttendanceRow = $todayAttendance ?? null;
            $todayLate = (int) ($todayAttendanceRow?->late_minutes ?? 0) > 0;
            $lunchWorked = $todayAttendanceRow?->lunch_in_at !== null;
            $dinnerWorked = $todayAttendanceRow?->dinner_in_at !== null;
            $lunchApplied = (bool) ($todayAttendanceRow?->is_lunch_tip_applied ?? false);
            $dinnerApplied = (bool) ($todayAttendanceRow?->is_dinner_tip_applied ?? false);
        @endphp
        <section class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <article class="rounded-2xl border-2 border-black bg-gradient-to-br from-indigo-100 via-sky-100 to-cyan-100 p-2 text-gray-900 shadow-[0_6px_0_0_rgba(0,0,0,1)] dark:border-slate-500/40 dark:from-gray-900 dark:via-indigo-950/40 dark:to-slate-900 dark:text-gray-100">
                <div class="mb-1 flex items-center justify-between">
                    <h1 class="text-base font-black tracking-wide text-indigo-800 dark:text-indigo-200">👑 Profile</h1>
                    <span class="text-xs font-black text-indigo-700 dark:text-indigo-200">Aujourd'hui: {{ $businessDate->format('Y-m-d') }}</span>
                    <span class="inline-flex items-center gap-1 rounded-full bg-white/90 px-1.5 py-0.5 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-300/70 dark:bg-white/10 dark:text-indigo-200 dark:ring-indigo-400/30">
                        <span class="h-1.5 w-1.5 rounded-full bg-cyan-500 animate-pulse"></span>
                        <span id="idle-timer">180s</span>
                    </span>
                </div>
                @if ($staff)
                    <div class="mx-1 rounded-xl border border-indigo-300/70 bg-white/85 p-2 backdrop-blur-sm dark:border-indigo-400/30 dark:bg-white/5">
                        <div class="flex items-center gap-2 text-xs">
                            <span class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm font-extrabold {{ $roleChipClass }}">
                                <span>{{ $roleIcon }}</span>
                                <span>{{ \Illuminate\Support\Str::limit($roleLabel ?? 'Other', 5, '') }}</span>
                            </span>
                            <span class="truncate text-base font-extrabold tracking-wide text-indigo-800 dark:text-indigo-100">{{ $staff->name }}</span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-gradient-to-r from-amber-300 via-orange-300 to-pink-300 px-2 py-0.5 text-[11px] font-extrabold text-gray-900 ring-1 ring-amber-200/70">
                                <span>{{ $levelIcon }}</span>
                                <span>Lv.  {{ $staff->jobLevel?->name }}</span>
                            </span>
                        </div>
                    </div>
                    @if (($staff->jobLevel?->level ?? 0) != 0)
                        <div class="mt-1 rounded-lg border border-amber-300/70 bg-white/80 p-1.5 dark:border-amber-500/30 dark:bg-white/5">
                            <div class="mb-1 text-xs font-extrabold text-amber-700 dark:text-amber-200">🪙 Statut tip du jour</div>
                            <div class="space-y-1 text-xs leading-relaxed">
                                <p class="{{ $lunchApplied ? 'font-semibold text-emerald-300' : ($lunchWorked && $todayLate ? 'text-slate-300/80' : 'text-yellow-200/90') }}">
                                    @if (! $lunchWorked)
                                            <span class="text-slate-500/90 dark:text-slate-300/70">😶‍🌫️ {{ $todayLabel }} Déjeuner tip -- (absent)</span>
                                    @elseif ($lunchApplied)
                                        <span class="inline-flex items-center gap-1 rounded border border-emerald-300/70 bg-white px-1.5 py-0.5 text-sm font-black text-emerald-700">
                                            <span>✅</span>
                                            <span>{{ $todayLabel }} Déjeuner : demande tip Bravo terminée</span>
                                        </span>
                                    @elseif ($todayLate)
                                        <span class="inline-flex items-center gap-1 rounded border border-slate-300/60 bg-slate-100/90 px-1.5 py-0.5 text-xs font-semibold text-slate-700">
                                            <span>📋</span>
                                            <span>{{ $todayLabel }} Déjeuner : tip non autorisé (retard)</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-md border border-emerald-300 bg-white px-2 py-1 text-sm font-black text-emerald-700 shadow-sm ring-1 ring-emerald-200/70">
                                            <span class="rounded bg-emerald-600 px-1 py-0.5 text-xs font-extrabold tracking-wide text-white">BRAVO</span>
                                            <span>🎉 {{ $todayLabel }} Déjeuner : en attente de réception du tip</span>
                                        </span>
                                    @endif
                                </p>
                                <p class="{{ $dinnerApplied ? 'font-semibold text-emerald-300' : ($dinnerWorked && $todayLate ? 'text-slate-300/80' : 'text-yellow-200/90') }}">
                                    @if (! $dinnerWorked)
                                        <span class="text-slate-500/90 dark:text-slate-300/70">😶‍🌫️ {{ $todayLabel }} Dîner tip -- (absent)</span>
                                    @elseif ($dinnerApplied)
                                        <span class="inline-flex items-center gap-1 rounded border border-emerald-300/70 bg-white px-1.5 py-0.5 text-sm font-black text-emerald-700">
                                            <span>✅</span>
                                            <span>{{ $todayLabel }} Dîner : demande tip Bravo terminée</span>
                                        </span>
                                    @elseif ($todayLate)
                                        <span class="inline-flex items-center gap-1 rounded border border-slate-300/60 bg-slate-100/90 px-1.5 py-0.5 text-xs font-semibold text-slate-700">
                                            <span>📋</span>
                                            <span>{{ $todayLabel }} Dîner : tip non autorisé (retard)</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-md border border-sky-300 bg-white px-2 py-1 text-sm font-black text-sky-700 shadow-sm ring-1 ring-sky-200/70">
                                            <span class="rounded bg-sky-600 px-1 py-0.5 text-xs font-extrabold tracking-wide text-white">BRAVO</span>
                                            <span>🎊✅ {{ $todayLabel }} Dîner : Tip validée</span>
                                        </span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    @else
                        <div class="mt-1 rounded-lg border border-amber-300/70 bg-white/80 p-1.5 dark:border-amber-500/30 dark:bg-white/5 flex items-center">
                            <span class="text-xs font-extrabold text-amber-700 dark:text-amber-200">
                                Continuez vos efforts, vous pourrez bientôt recevoir des pourboires. Merci pour votre travail !
                            </span>
                        </div>
                    @endif
                    <div class="mt-1 rounded-lg border border-indigo-300/70 bg-white/80 p-1 dark:border-indigo-400/20 dark:bg-white/5">
                        <div class="mb-1 flex items-center gap-1 text-xs font-extrabold text-indigo-700 dark:text-indigo-200">
                            <span>💠</span>
                            <span>Historique tip</span>
                            <span class="ml-auto rounded-full border border-indigo-200 bg-white/80 px-1.5 py-0.5 text-xs font-bold text-indigo-700 dark:border-indigo-500/30 dark:bg-white/10 dark:text-indigo-200">Last 3</span>
                        </div>
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="border-b border-indigo-200/80 dark:border-indigo-500/20">
                                    <th class="px-1 py-0.5 text-left font-semibold">📅 Date</th>
                                    <th class="px-1 py-0.5 text-right font-semibold">☀️ Lunch</th>
                                    <th class="px-1 py-0.5 text-right font-semibold">🌙 Dinner</th>
                                    <th class="px-1 py-0.5 text-right font-semibold">💎 Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (($tipRecentNonZero3 ?? collect()) as $d)
                                    <tr class="tip-row border-b border-indigo-100/90 odd:bg-white/70 even:bg-indigo-50/70 dark:border-indigo-500/10 dark:odd:bg-white/5 dark:even:bg-indigo-900/10 last:border-b-0">
                                        <td class="px-1 py-0.5 font-mono">{{ $d['date'] }}</td>
                                        <td class="px-1 py-0.5 text-right font-mono">{{ number_format((float) ($d['lunch'] ?? 0), 1) }}</td>
                                        <td class="px-1 py-0.5 text-right font-mono">{{ number_format((float) ($d['dinner'] ?? 0), 1) }}</td>
                                        <td class="px-1 py-0.5 text-right font-mono font-extrabold">{{ number_format((float) ($d['total'] ?? 0), 1) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-1 py-1 text-center font-semibold text-indigo-700/80 dark:text-indigo-200/80">Aucune donnee</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-1 rounded-lg border border-emerald-300/70 bg-white/70 p-1.5 dark:border-emerald-400/20 dark:bg-white/5">
                        <div class="mb-1 text-xs font-extrabold text-emerald-700 dark:text-emerald-200">⏰ Presence</div>
                        <div class="space-y-1 text-xs leading-relaxed">
                            <span class="group relative block rounded border border-amber-300 bg-amber-100 px-1.5 py-1 font-extrabold text-amber-800 dark:border-amber-500/30 dark:bg-amber-900/30 dark:text-amber-200">
                                Retards ce mois: {{ (int) ($monthLateCount ?? 0) }}
                                <span class="absolute right-0 top-full z-20 mt-1 hidden min-w-36 rounded-md border border-slate-200 bg-white p-2 text-xs font-semibold text-slate-700 shadow-lg group-hover:block dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200" role="tooltip">
                                    <strong class="mb-1 block text-xs text-slate-800 dark:text-slate-100">Dates de retard (mois)</strong>
                                    @forelse (($monthLateDates ?? collect()) as $line)
                                        <span class="block">{{ $line }}</span>
                                    @empty
                                        <span class="block">Aucun</span>
                                    @endforelse
                                </span>
                            </span>
                            <span class="group relative block rounded border border-slate-300 bg-slate-100 px-1.5 py-1 font-extrabold text-slate-700 dark:border-slate-500/30 dark:bg-slate-800/40 dark:text-slate-200">
                                Absences ce mois: {{ (int) ($monthAbsentCount ?? 0) }}
                                <span class="absolute right-0 top-full z-20 mt-1 hidden min-w-36 rounded-md border border-slate-200 bg-white p-2 text-xs font-semibold text-slate-700 shadow-lg group-hover:block dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200" role="tooltip">
                                    <strong class="mb-1 block text-xs text-slate-800 dark:text-slate-100">Dates d'absence (mois)</strong>
                                    @forelse (($monthAbsentDates ?? collect()) as $line)
                                        <span class="block">{{ $line }}</span>
                                    @empty
                                        <span class="block">Aucune</span>
                                    @endforelse
                                </span>
                            </span>
                            @if (($monthLateCount ?? 0) === 0 && ($monthAbsentCount ?? 0) === 0)
                                <div class="rounded-md border border-emerald-300 bg-gradient-to-r from-emerald-100 via-lime-100 to-cyan-100 px-1.5 py-1 text-xs font-extrabold text-emerald-700 dark:border-emerald-500/30 dark:from-emerald-900/30 dark:via-lime-900/20 dark:to-cyan-900/30 dark:text-emerald-200">
                                    🏅 Presence exemplaire Bravo!
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="mt-1 flex items-center justify-end text-xs">
                        <a href="{{ route('timecard.index') }}" class="rounded bg-white/80 px-2 py-1 font-semibold text-indigo-700 ring-1 ring-indigo-300/60 dark:bg-white/10 dark:text-indigo-200">Retour</a>
                    </div>
                @else
                    <p class="text-xs text-gray-600 dark:text-gray-300">Authentification PIN requise. Ouvrez de nouveau Mon espace depuis l'accueil.</p>
                @endif
            </article>

            <article class="sm:col-span-2 rounded-2xl border border-rose-300/80 bg-gradient-to-br from-rose-100 via-orange-100 to-indigo-100 p-3 text-gray-900 shadow-sm shadow-rose-200/40 dark:border-rose-500/30 dark:from-gray-900 dark:via-rose-950/40 dark:to-indigo-950/40 dark:text-gray-100">
                <h2 class="mb-2 text-base font-bold text-rose-700 dark:text-rose-200">📋 Taches prioritaires</h2>
                @if ($staff)
                    <div class="overflow-x-auto rounded-lg border-2 border-rose-300/80 bg-rose-50/80 ring-2 ring-rose-300/50 dark:border-rose-500/30 dark:bg-rose-950/20 dark:ring-rose-500/20">
                        <table class="w-full min-w-[16rem] text-xs">
                            <thead>
                                <tr class="border-b border-rose-200 bg-rose-100/80 dark:border-rose-500/20 dark:bg-rose-900/30">
                                    <th class="px-1.5 py-0.5 text-left font-semibold">Type</th>
                                    <th class="px-1.5 py-0.5 text-left font-semibold">Element</th>
                                    <th class="px-1.5 py-0.5 text-center font-semibold">Progression</th>
                                    <th class="px-1.5 py-0.5 text-center font-semibold">Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($routineTasks as $task)
                                    @php $done = $routineLogIds->contains($task->id); @endphp
                                    <tr class="border-b border-rose-100 dark:border-rose-500/10">
                                        <td class="px-1.5 py-0.5">📌 Routine</td>
                                        <td class="px-1.5 py-0.5 truncate max-w-36">{{ $task->name }}</td>
                                        <td class="px-1.5 py-0.5 text-center">{{ $done ? '1/1' : '0/1' }}</td>
                                        <td class="px-1.5 py-0.5 text-center">
                                            <span class="{{ $done ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300 animate-pulse font-extrabold' }}">{{ $done ? 'Termine' : 'Non termine' }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-1.5 py-1 text-center text-gray-600 dark:text-gray-300">Aucune routine assignee</td></tr>
                                @endforelse

                                @forelse ($inventoryTimingRows as $row)
                                    <tr class="border-b border-rose-100 dark:border-rose-500/10 last:border-b-0">
                                        <td class="px-1.5 py-0.5">📦 Inventory</td>
                                        <td class="px-1.5 py-0.5 truncate max-w-36">{{ $row['label'] }}</td>
                                        <td class="px-1.5 py-0.5 text-center">{{ $row['filled'] }}/{{ $row['total'] }}</td>
                                        <td class="px-1.5 py-0.5 text-center">
                                            <span class="{{ $row['complete'] ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-500 animate-pulse font-extrabold' }}">{{ $row['complete'] ? 'Termine' : 'Non fait' }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-1.5 py-1 text-center text-gray-600 dark:text-gray-300">Aucun inventaire assigne</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-1 grid grid-cols-2 gap-1 text-xs">
                        <span class="{{ $routinesPendingCount > 0 ? 'text-rose-600 dark:text-rose-300 animate-pulse' : 'text-emerald-600 dark:text-emerald-300' }}">
                            Routine: {{ $routinesPendingCount > 0 ? 'en attente' : 'tout termine' }}
                        </span>
                        <span class="{{ $inventoryIncomplete ? 'text-rose-500 animate-pulse' : 'text-emerald-600 dark:text-emerald-300' }}">
                            Inventaire: {{ $inventoryIncomplete ? 'en attente' : 'termine' }}
                        </span>
                    </div>
                @else
                    <p class="text-sm text-gray-600 dark:text-gray-300">Selectionnez un personnel pour afficher la liste des taches.</p>
                @endif
            </article>
        </section>

        @if ($staff)
            @php
                $todayBusinessDate = \App\Support\BusinessDate::current()->toDateString();
            @endphp
            <section class="mt-2 rounded-2xl border border-slate-300/80 bg-white/80 p-2 shadow-sm dark:border-slate-600/40 dark:bg-slate-900/60">
                <h3 class="mb-2 text-sm font-bold text-slate-800 dark:text-slate-100">📊 Resultats mensuels de presence</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-[11px]">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-700">
                                <th class="px-2 py-1 text-left font-semibold">Date</th>
                                <th class="px-2 py-1 text-left font-semibold">LUNCH</th>
                                <th class="px-2 py-1 text-left font-semibold">DINNER</th>
                                <th class="px-2 py-1 text-left font-semibold">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse (($monthAttendances ?? collect()) as $row)
                                @php
                                    $dateValue = $row->date instanceof \Illuminate\Support\Carbon ? $row->date->toDateString() : \Illuminate\Support\Carbon::parse($row->date)->toDateString();
                                    $isNotToday = $dateValue !== $todayBusinessDate;
                                    $hasMissingClockOut = $isNotToday && (
                                        ($row->lunch_in_at !== null && $row->lunch_out_at === null)
                                        || ($row->dinner_in_at !== null && $row->dinner_out_at === null)
                                    );
                                @endphp
                                <tr class="border-b border-slate-100 dark:border-slate-800 last:border-b-0">
                                    <td class="px-2 py-1 font-mono">{{ \Illuminate\Support\Carbon::parse($dateValue)->format('m/d') }}</td>
                                    <td class="px-2 py-1 font-mono">{{ $row->lunch_in_at?->format('H:i') ?? '--:--' }} - {{ $row->lunch_out_at?->format('H:i') ?? '--:--' }}</td>
                                    <td class="px-2 py-1 font-mono">{{ $row->dinner_in_at?->format('H:i') ?? '--:--' }} - {{ $row->dinner_out_at?->format('H:i') ?? '--:--' }}</td>
                                    <td class="px-2 py-1">
                                        <div class="flex flex-wrap gap-1">
                                            @if ((int) ($row->late_minutes ?? 0) > 0)
                                                <span class="rounded-full bg-orange-100 px-2 py-0.5 text-xs font-semibold text-orange-800">Retard</span>
                                            @endif
                                            @if ($hasMissingClockOut)
                                                <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">Pointage oublie</span>
                                            @endif
                                            @if ((int) ($row->late_minutes ?? 0) === 0 && ! $hasMissingClockOut)
                                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Normal</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-2 py-2 text-center text-xs text-slate-500">Aucun enregistrement de presence ce mois.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div
                    x-data="{
                        message: '',
                        errorOpen: false,
                        staffName: @js($staff->name ?? ''),
                        sendWhatsapp() {
                            if (this.message.trim() === '') {
                                this.errorOpen = true;
                                return;
                            }
                            const text = `[Demande de correction: ${this.staffName}]\n\n${this.message}`;
                            window.open(`https://wa.me/21651992184?text=${encodeURIComponent(text)}`, '_blank');
                        },
                    }"
                    class="mt-2 rounded-xl border border-emerald-200 bg-emerald-50/70 p-2 dark:border-emerald-700/40 dark:bg-emerald-900/20"
                >
                    <label class="mb-1 block text-xs font-semibold text-emerald-900 dark:text-emerald-200">Demande de correction (WhatsApp manager)</label>
                    <textarea
                        x-model="message"
                        class="block w-full rounded-lg border border-emerald-300 bg-white px-2 py-2 text-xs text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 dark:border-emerald-600/40 dark:bg-slate-900 dark:text-slate-100"
                        rows="2"
                        placeholder="Ex.: merci de corriger l'oubli de pointage sortie diner du 30/03."
                    ></textarea>
                    <button
                        type="button"
                        class="mt-2 inline-flex items-center gap-1 rounded-lg bg-[#25D366] px-3 py-2 text-xs font-bold text-white transition hover:bg-green-600"
                        @click="sendWhatsapp()"
                    >
                        <span>💬</span>
                        <span>Envoyer au manager via WhatsApp</span>
                    </button>

                    <div
                        x-show="errorOpen"
                        x-cloak
                        x-transition.opacity
                        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
                        @click.self="errorOpen = false"
                    >
                        <div class="w-full max-w-sm rounded-xl border border-rose-200 bg-white p-4 shadow-xl">
                            <h3 class="text-base font-bold text-rose-700">Erreur de saisie</h3>
                            <p class="mt-2 text-sm text-slate-700">Veuillez saisir la demande avant l'envoi.</p>
                            <button
                                type="button"
                                class="mt-4 inline-flex w-full items-center justify-center rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700"
                                @click="errorOpen = false"
                            >
                                Fermer
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        @endif
    </main>

    <script>
        (() => {
            const limitSeconds = 180;
            const timerEl = document.getElementById('idle-timer');
            const body = document.body;
            const autoLogoutUrl = body?.dataset?.autoLogoutUrl || '/mypage/auto-logout';
            const timecardUrl = body?.dataset?.timecardUrl || '/timecard';
            let remain = limitSeconds;
            let ticking = null;

            const reset = () => {
                remain = limitSeconds;
                if (timerEl) timerEl.textContent = `${remain}s`;
            };

            const forceRedirect = () => {
                window.location.href = timecardUrl;
            };

            const applyTipRowVisibility = () => {
                const compact = window.innerHeight < 700;
                const rows = document.querySelectorAll('.tip-row');
                rows.forEach((row, index) => {
                    row.style.display = (!compact || index < 6) ? '' : 'none';
                });
            };

            const logout = () => {
                fetch(autoLogoutUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ reason: 'idle-timeout' }),
                })
                    .catch(() => {})
                    .finally(() => {
                        window.location.href = '/timecard';
                    });
            };

            const clearMyPageSessionOnLeave = () => {
                fetch(autoLogoutUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    keepalive: true,
                    body: JSON.stringify({ reason: 'page-leave' }),
                }).catch(() => {});
            };

            const tick = () => {
                remain -= 1;
                if (timerEl) timerEl.textContent = `${Math.max(remain, 0)}s`;
                if (remain <= 0) {
                    clearInterval(ticking);
                    logout();
                }
            };

            ['click', 'touchstart', 'keydown', 'scroll'].forEach((evt) => {
                window.addEventListener(evt, reset, { passive: true });
            });
            window.addEventListener('resize', applyTipRowVisibility, { passive: true });
            window.addEventListener('pagehide', clearMyPageSessionOnLeave);

            reset();
            applyTipRowVisibility();
            ticking = setInterval(tick, 1000);
        })();
    </script>
</body>
</html>
