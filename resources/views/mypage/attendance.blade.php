<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Presence (Mon espace) — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important;}</style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <x-client-nav />

    <div
        x-data="{
            outOpen: false,
            inOpen: false,
            outData: { id: '', lunchOut: '', dinnerOut: '' },
            inData: { id: '', lunchIn: '', dinnerIn: '' },
            openOut(data) { this.outData = data; this.outOpen = true },
            openIn(data) { this.inData = data; this.inOpen = true },
        }"
        class="mx-auto w-full max-w-6xl px-4 py-4"
    >
        <section class="mb-4 rounded-2xl border-2 border-black bg-gradient-to-r from-indigo-900 via-blue-900 to-purple-900 p-4 text-white shadow-[0_8px_0_0_rgba(0,0,0,1)]">
            <h1 class="text-2xl font-black tracking-wide">CENTRE DE PRESENCE</h1>
            <p class="mt-1 text-sm font-semibold text-slate-200">Controle mensuel des presences, corrections et alertes.</p>
            <p class="text-[10px] text-slate-300">勤怠確認と補正</p>
        </section>

        @if (session('status'))
            <div class="mb-3 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-3 rounded-xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ session('error') }}</div>
        @endif

        <form method="get" action="{{ route('mypage.attendance') }}" class="mb-4 rounded-xl border-2 border-black bg-white p-4 shadow-[0_6px_0_0_rgba(0,0,0,1)]">
            <label for="staff_select" class="mb-2 block text-sm font-black text-slate-800">Selection du personnel</label>
            <select name="staff_id" id="staff_select" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-3 text-base text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30" onchange="this.form.submit()">
                <option value="">— Selectionner —</option>
                @foreach ($staffList as $s)
                    <option value="{{ $s->id }}" @selected($staff && $staff->id === $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </form>

        @if (! $staff)
            <div class="rounded-xl border-2 border-dashed border-slate-300 bg-white p-8 text-center text-sm font-semibold text-slate-500">
                Selectionnez un personnel pour afficher les presences mensuelles et les statistiques.
            </div>
        @else
            @php
                $prevMonth = $monthStart->copy()->subMonth();
                $nextMonth = $monthStart->copy()->addMonth();
                $fmtHm = static function (int $minutes): string {
                    $h = intdiv($minutes, 60);
                    $m = $minutes % 60;
                    return sprintf('%d:%02d', $h, $m);
                };
                $todayBusinessDate = \App\Support\BusinessDate::current()->toDateString();
            @endphp

            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-xl font-black text-slate-900">Presence {{ $monthStart->translatedFormat('Y/m') }}</h2>
                <div class="inline-flex rounded-lg border-2 border-black bg-white p-0.5 text-sm font-bold shadow-[0_4px_0_0_rgba(0,0,0,1)]">
                    <a href="{{ route('mypage.attendance', ['staff_id' => $staff->id, 'month' => $prevMonth->format('Y-m')]) }}" class="rounded px-3 py-1 text-slate-700 hover:bg-slate-100">&laquo;</a>
                    <a href="{{ route('mypage.attendance', ['staff_id' => $staff->id, 'month' => \App\Support\BusinessDate::current()->format('Y-m')]) }}" class="rounded px-3 py-1 text-slate-700 hover:bg-slate-100">Mois courant</a>
                    <a href="{{ route('mypage.attendance', ['staff_id' => $staff->id, 'month' => $nextMonth->format('Y-m')]) }}" class="rounded px-3 py-1 text-slate-700 hover:bg-slate-100">&raquo;</a>
                </div>
            </div>

            <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded-xl border-2 border-black bg-gradient-to-br from-blue-100 to-cyan-100 p-4 shadow-[0_5px_0_0_rgba(0,0,0,1)]">
                    <p class="text-sm font-black text-blue-800">Heures cette semaine</p>
                    <p class="mt-1 font-mono text-3xl font-black text-blue-900">{{ $fmtHm($weekMinutes) }}</p>
                </div>
                <div class="rounded-xl border-2 border-black bg-gradient-to-br from-emerald-100 to-lime-100 p-4 shadow-[0_5px_0_0_rgba(0,0,0,1)]">
                    <p class="text-sm font-black text-emerald-800">Heures ce mois</p>
                    <p class="mt-1 font-mono text-3xl font-black text-emerald-900">{{ $fmtHm($monthMinutes) }}</p>
                </div>
                <div class="rounded-xl border-2 border-black bg-gradient-to-br from-amber-100 to-orange-100 p-4 shadow-[0_5px_0_0_rgba(0,0,0,1)]">
                    <p class="text-sm font-black text-amber-800">Retards ce mois</p>
                    <p class="mt-1 font-mono text-3xl font-black text-amber-900">{{ $monthLateCount }}<span class="ml-1 text-base">fois</span></p>
                </div>
            </div>

            <section class="mb-4 rounded-xl border-2 border-black bg-white p-3 shadow-[0_6px_0_0_rgba(0,0,0,1)]">
                <h3 class="mb-2 text-base font-black text-slate-900">🧾 Tableau detaille des pointages</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-black text-slate-700">Date</th>
                                <th class="px-3 py-2 text-left font-black text-slate-700">L Entree</th>
                                <th class="px-3 py-2 text-left font-black text-slate-700">L Sortie</th>
                                <th class="px-3 py-2 text-left font-black text-slate-700">D Entree</th>
                                <th class="px-3 py-2 text-left font-black text-slate-700">D Sortie</th>
                                <th class="px-3 py-2 text-right font-black text-slate-700">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($monthAttendances as $att)
                                @php
                                    $d = $att->date;
                                    $dateLabel = $d instanceof \Carbon\Carbon ? $d->format('m/d (D)') : \Carbon\Carbon::parse($d)->format('m/d (D)');
                                @endphp
                                <tr class="border-b border-slate-100 last:border-b-0">
                                    <td class="px-3 py-2 font-bold text-slate-800">{{ $dateLabel }}</td>
                                    <td class="px-3 py-2 font-mono">{{ $att->lunch_in_at?->format('H:i') ?? '—' }}</td>
                                    <td class="px-3 py-2 font-mono @if($att->lunch_in_at && ! $att->lunch_out_at) font-bold text-rose-600 @endif">{{ $att->lunch_out_at?->format('H:i') ?? ($att->lunch_in_at ? 'Sortie manquante' : '—') }}</td>
                                    <td class="px-3 py-2 font-mono">{{ $att->dinner_in_at?->format('H:i') ?? '—' }}</td>
                                    <td class="px-3 py-2 font-mono @if($att->dinner_in_at && ! $att->dinner_out_at) font-bold text-rose-600 @endif">{{ $att->dinner_out_at?->format('H:i') ?? ($att->dinner_in_at ? 'Sortie manquante' : '—') }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <div class="inline-flex gap-1">
                                            <button type="button" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-bold text-slate-700 hover:bg-slate-100" @click='openIn({ id: "{{ $att->id }}", lunchIn: "{{ $att->lunch_in_at?->format('H:i') ?? '' }}", dinnerIn: "{{ $att->dinner_in_at?->format('H:i') ?? '' }}" })'>Entree</button>
                                            <button type="button" class="rounded-md border border-blue-300 bg-blue-50 px-2 py-1 text-xs font-bold text-blue-700 hover:bg-blue-100" @click='openOut({ id: "{{ $att->id }}", lunchOut: "{{ $att->lunch_out_at?->format('H:i') ?? '' }}", dinnerOut: "{{ $att->dinner_out_at?->format('H:i') ?? '' }}" })'>Sortie</button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($monthAttendances->isEmpty())
                    <p class="mt-3 text-sm font-semibold text-slate-500">Aucune donnee de presence pour ce mois.</p>
                @endif
            </section>

            <section class="rounded-xl border-2 border-black bg-white p-4 shadow-[0_6px_0_0_rgba(0,0,0,1)]">
                <h3 class="mb-3 text-base font-black text-slate-900">📊 Resume mensuel des presences</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr class="border-b border-slate-200">
                                <th class="px-3 py-2 text-left font-black text-slate-700">Date</th>
                                <th class="px-3 py-2 text-left font-black text-slate-700">LUNCH</th>
                                <th class="px-3 py-2 text-left font-black text-slate-700">DINNER</th>
                                <th class="px-3 py-2 text-left font-black text-slate-700">Anomalie</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            @forelse (($attendances ?? $monthAttendances ?? collect()) as $row)
                                @php
                                    $dateValue = $row->date instanceof \Illuminate\Support\Carbon ? $row->date->toDateString() : \Illuminate\Support\Carbon::parse($row->date)->toDateString();
                                    $isNotToday = $dateValue !== $todayBusinessDate;
                                    $hasLunchMissingClockOut = $row->lunch_in_at !== null && $row->lunch_out_at === null;
                                    $hasDinnerMissingClockOut = $row->dinner_in_at !== null && $row->dinner_out_at === null;
                                    $hasMissingClockOut = $isNotToday && ($hasLunchMissingClockOut || $hasDinnerMissingClockOut);
                                @endphp
                                <tr class="border-b border-slate-100">
                                    <td class="px-3 py-2 font-mono text-slate-700">{{ \Illuminate\Support\Carbon::parse($dateValue)->format('m/d') }}</td>
                                    <td class="px-3 py-2 font-mono text-slate-700">{{ $row->lunch_in_at?->format('H:i') ?? '--:--' }} - {{ $row->lunch_out_at?->format('H:i') ?? '--:--' }}</td>
                                    <td class="px-3 py-2 font-mono text-slate-700">{{ $row->dinner_in_at?->format('H:i') ?? '--:--' }} - {{ $row->dinner_out_at?->format('H:i') ?? '--:--' }}</td>
                                    <td class="px-3 py-2">
                                        <div class="flex flex-wrap gap-1">
                                            @if ((int) ($row->late_minutes ?? 0) > 0)
                                                <span class="rounded-full bg-orange-100 px-2 py-0.5 text-xs font-bold text-orange-800">Retard</span>
                                            @endif
                                            @if ($hasMissingClockOut)
                                                <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-800">Pointage oublie</span>
                                            @endif
                                            @if ((int) ($row->late_minutes ?? 0) === 0 && ! $hasMissingClockOut)
                                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800">Normal</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-3 text-center text-sm text-slate-500">Aucune donnee disponible.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div
                    x-data="{
                        message: '',
                        errorOpen: false,
                        staffName: @js($staff->name),
                        sendWhatsapp() {
                            if (this.message.trim() === '') {
                                this.errorOpen = true;
                                return;
                            }
                            const text = `[Demande de correction: ${this.staffName}]\n\n${this.message}`;
                            window.open(`https://wa.me/21651992184?text=${encodeURIComponent(text)}`, '_blank');
                        },
                    }"
                    class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50/70 p-3"
                >
                    <label class="mb-1 block text-sm font-black text-emerald-900">Demande de correction au manager (WhatsApp)</label>
                    <textarea x-model="message" rows="3" class="block w-full rounded-lg border border-emerald-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30" placeholder="Ex.: J'ai oublie de pointer la sortie du 15/03. Sortie a 23:30."></textarea>
                    <button type="button" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-[#25D366] px-4 py-2 text-sm font-bold text-white transition hover:bg-green-600" @click="sendWhatsapp()">
                        <span>💬</span>
                        <span>Envoyer au manager via WhatsApp</span>
                    </button>

                    <div x-show="errorOpen" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4" @click.self="errorOpen = false">
                        <div class="w-full max-w-sm rounded-xl border border-rose-200 bg-white p-4 shadow-xl">
                            <h3 class="text-base font-bold text-rose-700">Erreur de saisie</h3>
                            <p class="mt-2 text-sm text-slate-700">Veuillez saisir le contenu de la correction avant l'envoi.</p>
                            <button type="button" class="mt-4 inline-flex w-full items-center justify-center rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700" @click="errorOpen = false">
                                Fermer
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        @if ($staff)
            <div x-show="outOpen" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/60 p-4">
                <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-4 shadow-xl">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-lg font-bold text-slate-900">Modifier les heures de sortie</h2>
                        <button type="button" class="text-slate-500 hover:text-slate-700" @click="outOpen = false">✕</button>
                    </div>
                    <form method="post" action="{{ route('mypage.attendance.update') }}">
                        @csrf
                        <input type="hidden" name="mode" value="out">
                        <input type="hidden" name="attendance_id" :value="outData.id">
                        <input type="hidden" name="staff_id" value="{{ $staff->id }}">
                        <input type="hidden" name="month" value="{{ $monthStart->format('Y-m') }}">
                        <p class="mb-3 text-xs text-slate-500">Enregistrement avec le PIN personnel uniquement.</p>
                        <p class="-mt-2 mb-3 text-[10px] text-slate-400">本人PINのみ</p>
                        <div class="mb-3">
                            <label class="mb-1 block text-sm font-semibold text-slate-700">Sortie dejeuner</label>
                            <input type="time" name="lunch_out" :value="outData.lunchOut" class="block w-full rounded-lg border border-slate-300 px-3 py-3 text-center font-mono">
                        </div>
                        <div class="mb-3">
                            <label class="mb-1 block text-sm font-semibold text-slate-700">Sortie diner</label>
                            <input type="time" name="dinner_out" :value="outData.dinnerOut" class="block w-full rounded-lg border border-slate-300 px-3 py-3 text-center font-mono">
                        </div>
                        <div class="mb-4">
                            <label class="mb-1 block text-sm font-semibold text-slate-700">PIN personnel (4 chiffres)</label>
                            <input type="password" name="pin_code" inputmode="numeric" maxlength="4" required class="block w-full rounded-lg border border-slate-300 px-3 py-3 text-center font-mono" placeholder="••••" autocomplete="one-time-code">
                        </div>
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700">Enregistrer</button>
                    </form>
                </div>
            </div>

            <div x-show="inOpen" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/60 p-4">
                <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-4 shadow-xl">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-lg font-bold text-slate-900">Modifier les heures d'entree</h2>
                        <button type="button" class="text-slate-500 hover:text-slate-700" @click="inOpen = false">✕</button>
                    </div>
                    <form method="post" action="{{ route('mypage.attendance.update') }}">
                        @csrf
                        <input type="hidden" name="mode" value="in">
                        <input type="hidden" name="attendance_id" :value="inData.id">
                        <input type="hidden" name="staff_id" value="{{ $staff->id }}">
                        <input type="hidden" name="month" value="{{ $monthStart->format('Y-m') }}">
                        <p class="mb-3 text-xs text-slate-500">La modification des entrees exige l'approbation PIN manager.</p>
                        <p class="-mt-2 mb-3 text-[10px] text-slate-400">管理者承認が必要</p>
                        <div class="mb-3">
                            <label class="mb-1 block text-sm font-semibold text-slate-700">Entree dejeuner</label>
                            <input type="time" name="lunch_in" :value="inData.lunchIn" class="block w-full rounded-lg border border-slate-300 px-3 py-3 text-center font-mono">
                        </div>
                        <div class="mb-3">
                            <label class="mb-1 block text-sm font-semibold text-slate-700">Entree diner</label>
                            <input type="time" name="dinner_in" :value="inData.dinnerIn" class="block w-full rounded-lg border border-slate-300 px-3 py-3 text-center font-mono">
                        </div>
                        <div class="mb-3">
                            <label class="mb-1 block text-sm font-semibold text-slate-700">PIN personnel (4 chiffres)</label>
                            <input type="password" name="pin_code" inputmode="numeric" maxlength="4" required class="block w-full rounded-lg border border-slate-300 px-3 py-3 text-center font-mono" placeholder="••••" autocomplete="one-time-code">
                        </div>
                        <div class="mb-4">
                            <label class="mb-1 block text-sm font-semibold text-slate-700">PIN manager (4 chiffres)</label>
                            <input type="password" name="manager_pin" inputmode="numeric" maxlength="4" required class="block w-full rounded-lg border border-rose-300 px-3 py-3 text-center font-mono" placeholder="••••" autocomplete="one-time-code">
                        </div>
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700">Enregistrer</button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</body>
</html>
