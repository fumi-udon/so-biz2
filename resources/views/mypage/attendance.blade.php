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

    <div class="mx-auto w-full max-w-6xl px-4 py-4">
        <section class="mb-4 rounded-2xl border-2 border-black bg-gradient-to-r from-indigo-900 via-blue-900 to-purple-900 p-4 text-white shadow-[0_8px_0_0_rgba(0,0,0,1)]">
            <h1 class="text-2xl font-black tracking-wide">CENTRE DE PRESENCE</h1>

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
            @endphp

            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-xl font-black text-slate-900">Presence {{ $monthStart->translatedFormat('Y/m') }}</h2>
                <div class="inline-flex rounded-lg border-2 border-black bg-white p-0.5 text-sm font-bold shadow-[0_4px_0_0_rgba(0,0,0,1)]">
                    <a href="{{ route('mypage.attendance', ['staff_id' => $staff->id, 'month' => $prevMonth->format('Y-m')]) }}" class="rounded px-3 py-1 text-slate-700 hover:bg-slate-100">&laquo;</a>
                    <a href="{{ route('mypage.attendance', ['staff_id' => $staff->id, 'month' => \App\Support\BusinessDate::current()->format('Y-m')]) }}" class="rounded px-3 py-1 text-slate-700 hover:bg-slate-100">Mois courant</a>
                    <a href="{{ route('mypage.attendance', ['staff_id' => $staff->id, 'month' => $nextMonth->format('Y-m')]) }}" class="rounded px-3 py-1 text-slate-700 hover:bg-slate-100">&raquo;</a>
                </div>
            </div>

            <div class="mb-2 grid grid-cols-1 gap-2 md:grid-cols-3">
                <!-- <div class="rounded-lg border-2 border-black bg-white p-2 shadow-[0_3px_0_0_rgba(0,0,0,1)]">
                    <div class="flex items-center justify-between">
                        <div class="font-mono text-sm font-black text-slate-800">Heures cette semaine</div>
                        <div class="font-mono text-sm font-black text-blue-900">{{ $fmtHm($weekMinutes) }}</div>
                    </div>
                </div> -->
                <div class="rounded-lg border-2 border-black bg-white p-2 shadow-[0_3px_0_0_rgba(0,0,0,1)]">
                    <div class="flex items-center justify-between">
                        <div class="font-mono text-sm font-black text-slate-800">Heures ce mois</div>
                        <div class="font-mono text-sm font-black text-emerald-900">{{ $fmtHm($monthMinutes) }}</div>
                    </div>
                </div>
                <div class="rounded-lg border-2 border-black bg-white p-2 shadow-[0_3px_0_0_rgba(0,0,0,1)]">
                    <div class="flex items-center justify-between">
                        <div class="font-mono text-sm font-black text-slate-800">Retards ce mois</div>
                        <div class="font-mono text-sm font-black text-amber-900">{{ $monthLateCount }} <span class="text-sm font-normal">fois</span></div>
                    </div>
                </div>
            </div>

            {{-- 勤怠テーブル（閲覧のみ・修正は Filament 管理画面） --}}
            <div class="mb-4">
                <div class="mb-2">
                    <h3 class="text-base font-black text-slate-900">🧾 Tableau detaille des pointages</h3>
                    <p class="mt-1 text-xs leading-relaxed text-slate-700">
                        <span class="font-semibold text-slate-900">【日本語】</span>データの修正が必要な場合はマネージャーまでご連絡ください（管理画面 Filament でのみ変更可能です）。
                    </p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-600">
                        Lecture seule : les corrections se font par un manager via l&apos;admin Filament.
                    </p>
                </div>

                <div class="rounded-xl border-2 border-black bg-white p-3 shadow-[0_6px_0_0_rgba(0,0,0,1)]">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="border-b border-slate-200 bg-slate-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-black text-slate-700">Date</th>
                                    <th class="px-3 py-2 text-left font-black text-slate-700">☀️IN</th>
                                    <th class="px-3 py-2 text-left font-black text-slate-700">☀️OUT</th>
                                    <th class="px-3 py-2 text-left font-black text-slate-700">🌙IN</th>
                                    <th class="px-3 py-2 text-left font-black text-slate-700">🌙OUT</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($monthAttendances as $att)
                                    @php
                                        $d = $att->date;
                                        $dateLabel = $d instanceof \Carbon\Carbon ? $d->format('m/d (D)') : \Carbon\Carbon::parse($d)->format('m/d (D)');
                                        $lunchOutMissing = $att->lunch_in_at !== null && $att->lunch_out_at === null;
                                        $dinnerOutMissing = $att->dinner_in_at !== null && $att->dinner_out_at === null;
                                    @endphp
                                    <tr class="border-b border-slate-100 last:border-b-0">
                                        <td class="px-3 py-2 font-bold text-slate-800">{{ $dateLabel }}</td>
                                        <td class="px-2 py-1 font-mono text-slate-800">{{ $att->lunch_in_at?->format('H:i') ?? '—' }}</td>
                                        <td class="px-2 py-1 font-mono {{ $lunchOutMissing ? 'font-bold text-rose-600' : 'text-slate-800' }}">
                                            @if ($att->lunch_out_at)
                                                {{ $att->lunch_out_at->format('H:i') }}
                                            @elseif ($att->lunch_in_at)
                                                Sortie manquante
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-2 py-1 font-mono text-slate-800">{{ $att->dinner_in_at?->format('H:i') ?? '—' }}</td>
                                        <td class="px-2 py-1 font-mono {{ $dinnerOutMissing ? 'font-bold text-rose-600' : 'text-slate-800' }}">
                                            @if ($att->dinner_out_at)
                                                {{ $att->dinner_out_at->format('H:i') }}
                                            @elseif ($att->dinner_in_at)
                                                Sortie manquante
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-3 py-3 text-center text-sm text-slate-500">Aucune donnee de presence pour ce mois.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <section class="rounded-xl border-2 border-black bg-white p-4 shadow-[0_6px_0_0_rgba(0,0,0,1)]">
                <div
                    x-data="{
                        message: '',
                        errorOpen: false,
                        staffName: @js($staff->name ?? ''),
                        sendWhatsapp() {
                            // bug検証：メッセージ入力未対応時の挙動確認
                            if (this.message.trim() === '') {
                                this.errorOpen = true;
                                return;
                            }
                            const text = `[Demande de correction: ${this.staffName ?? ''}]\n\n${this.message}`;
                            window.open(`https://wa.me/21651992184?text=${encodeURIComponent(text)}`, '_blank');
                        },
                    }"
                    class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50/70 p-3"
                >
                    <label class="mb-1 block text-sm font-black text-emerald-900">Demande de correction au manager (WhatsApp)</label>
                    {{-- bug検証: 送信直後にinputが空欄にならないか検証 --}}
                    <textarea x-model="message" rows="3" class="block w-full rounded-lg border border-emerald-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30" placeholder="Ex.: J'ai oublie de pointer la sortie du 15/03. Sortie a 23:30."></textarea>
                    <button type="button" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-[#25D366] px-4 py-2 text-sm font-bold text-white transition hover:bg-green-600"
                        @click="sendWhatsapp()"
                        id="test-bug-whatsapp"
                    >
                        <span>💬</span>
                        <span>Envoyer au manager via WhatsApp</span>
                    </button>

                    <div x-show="errorOpen" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4" @click.self="errorOpen = false">
                        <div class="w-full max-w-sm rounded-xl border border-rose-200 bg-white p-4 shadow-xl">
                            <h3 class="text-base font-bold text-rose-700">Erreur de saisie</h3>
                            {{-- bug検証: アラート表示内容確認 --}}
                            <p class="mt-2 text-sm text-slate-700">Veuillez saisir le contenu de la correction avant l'envoi.</p>
                            <button type="button" class="mt-4 inline-flex w-full items-center justify-center rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700" @click="errorOpen = false">
                                Fermer
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            {{-- ── 編集ログ履歴（直近1か月・10件ずつ） ────────────────────── --}}
            @if ($editLogs && $editLogs->total() > 0)
                @php
                    $fieldLabels = [
                        'lunch_in_at'  => '☀️ IN',
                        'lunch_out_at' => '☀️ OUT',
                        'dinner_in_at' => '🌙 IN',
                        'dinner_out_at'=> '🌙 OUT',
                    ];
                @endphp
                <section class="mt-4 rounded-xl border-2 border-black bg-white shadow-[0_6px_0_0_rgba(0,0,0,1)]">
                    <div class="flex items-center justify-between border-b-2 border-black bg-slate-900 px-3 py-2">
                        <span class="font-mono text-[11px] font-black uppercase tracking-widest text-yellow-300">Historique des modifications</span>
                        <span class="rounded border border-slate-600 bg-slate-800 px-2 py-0.5 font-mono text-[10px] text-slate-400">{{ $editLogs->total() }} entree(s) — 30 derniers jours</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50">
                                    <th class="px-3 py-2 text-left font-black text-slate-600">Date/Heure</th>
                                    <th class="px-3 py-2 text-left font-black text-slate-600">Jour vise</th>
                                    <th class="px-3 py-2 text-left font-black text-slate-600">Champ</th>
                                    <th class="px-3 py-2 text-center font-black text-slate-600">Avant</th>
                                    <th class="px-3 py-2 text-center font-black text-slate-600">Apres</th>
                                    <th class="px-3 py-2 text-left font-black text-slate-600">Editeur</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($editLogs as $log)
                                    <tr class="hover:bg-slate-50">
                                        <td class="whitespace-nowrap px-3 py-2 font-mono text-slate-700">
                                            {{ \Carbon\Carbon::parse($log->created_at)->format('m/d H:i') }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2 font-mono font-bold text-slate-800">
                                            {{ $log->attendance?->date ? \Carbon\Carbon::parse($log->attendance->date)->format('m/d') : '—' }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <span class="rounded-full border border-slate-300 bg-slate-100 px-2 py-0.5 font-mono font-semibold text-slate-700">
                                                {{ $fieldLabels[$log->field_name] ?? $log->field_name }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <span class="font-mono text-rose-600">{{ $log->old_value ?? '—' }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <span class="font-mono font-bold text-emerald-700">{{ $log->new_value ?? '—' }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-slate-700">{{ $log->editorStaff?->name ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($editLogs->hasPages())
                        <div class="border-t border-slate-200 px-3 py-2">
                            {{ $editLogs->links('pagination::simple-tailwind') }}
                        </div>
                    @endif
                </section>
            @endif
        @endif
    </div>
</body>
</html>
