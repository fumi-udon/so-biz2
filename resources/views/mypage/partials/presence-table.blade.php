{{--
    Variables expected from parent (mypage.index):
    $presenceAttendances : Collection<Attendance>
--}}
@php
    $todayString = \App\Support\BusinessDate::current()->toDateString();
@endphp

<div class="mt-3 overflow-hidden rounded-2xl border-2 border-black shadow-[0_6px_0_0_rgba(0,0,0,1)] dark:border-slate-600/80">

    <div class="flex items-center justify-between border-b-2 border-black bg-slate-900 px-3 py-2">
        <span class="font-mono text-[11px] font-black uppercase tracking-widest text-yellow-300">
            🧾 Tableau detaille des pointages
        </span>
    </div>

    <div class="overflow-x-auto bg-white dark:bg-slate-900">
        <table class="min-w-full text-sm">
            <thead class="border-b-2 border-black bg-slate-100 dark:border-slate-700 dark:bg-slate-800">
                <tr>
                    <th class="px-3 py-2 text-left font-black text-slate-700 dark:text-slate-200">Date</th>
                    <th class="px-3 py-2 text-left font-black text-slate-700 dark:text-slate-200">☀️ IN</th>
                    <th class="px-3 py-2 text-left font-black text-slate-700 dark:text-slate-200">☀️ OUT</th>
                    <th class="px-3 py-2 text-left font-black text-slate-700 dark:text-slate-200">🌙 IN</th>
                    <th class="px-3 py-2 text-left font-black text-slate-700 dark:text-slate-200">🌙 OUT</th>
                    <th class="px-3 py-2 text-left font-black text-slate-700 dark:text-slate-200">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($presenceAttendances as $row)
                    @php
                        $dateVal = $row->date instanceof \Illuminate\Support\Carbon
                            ? $row->date->toDateString()
                            : \Illuminate\Support\Carbon::parse($row->date)->toDateString();
                        $isToday          = $dateVal === $todayString;
                        $lunchOutMissing  = ! $isToday && $row->lunch_in_at  !== null && $row->lunch_out_at  === null;
                        $dinnerOutMissing = ! $isToday && $row->dinner_in_at !== null && $row->dinner_out_at === null;
                        $hasMissing       = $lunchOutMissing || $dinnerOutMissing;
                        $isLate           = (int) ($row->late_minutes ?? 0) > 0;
                        $dateLabel        = \Illuminate\Support\Carbon::parse($dateVal)->format('m/d (D)');
                    @endphp
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-3 py-2 font-bold text-slate-800 dark:text-slate-100">{{ $dateLabel }}</td>

                        {{-- Lunch IN --}}
                        <td class="px-2 py-1 font-mono text-slate-700 dark:text-slate-300">
                            {{ $row->lunch_in_at?->format('H:i') ?? '—' }}
                            @if ($isLate && $row->lunch_in_at)
                                <span class="ml-1 inline-flex rounded-full bg-rose-100 px-1.5 py-0.5 text-[9px] font-bold text-rose-700 dark:bg-rose-950/60 dark:text-rose-300">+{{ $row->late_minutes }}m</span>
                            @endif
                        </td>

                        {{-- Lunch OUT --}}
                        <td class="px-2 py-1 font-mono {{ $lunchOutMissing ? 'font-bold text-rose-600 dark:text-rose-400' : 'text-slate-700 dark:text-slate-300' }}">
                            @if ($row->lunch_out_at)
                                {{ $row->lunch_out_at->format('H:i') }}
                            @elseif ($row->lunch_in_at)
                                @if ($isToday)
                                    <span class="text-amber-600 dark:text-amber-400">En cours</span>
                                @else
                                    Oubli sortie
                                @endif
                            @else
                                —
                            @endif
                        </td>

                        {{-- Dinner IN --}}
                        <td class="px-2 py-1 font-mono text-slate-700 dark:text-slate-300">
                            {{ $row->dinner_in_at?->format('H:i') ?? '—' }}
                        </td>

                        {{-- Dinner OUT --}}
                        <td class="px-2 py-1 font-mono {{ $dinnerOutMissing ? 'font-bold text-rose-600 dark:text-rose-400' : 'text-slate-700 dark:text-slate-300' }}">
                            @if ($row->dinner_out_at)
                                {{ $row->dinner_out_at->format('H:i') }}
                            @elseif ($row->dinner_in_at)
                                @if ($isToday)
                                    <span class="text-amber-600 dark:text-amber-400">En cours</span>
                                @else
                                    Oubli sortie
                                @endif
                            @else
                                —
                            @endif
                        </td>

                        {{-- Statut --}}
                        <td class="px-2 py-1">
                            <div class="flex flex-wrap gap-1">
                                @if ($isLate)
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-950/50 dark:text-amber-200">Retard</span>
                                @endif
                                @if ($hasMissing)
                                    <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-800 dark:bg-rose-950/50 dark:text-rose-200">Pointage oublie</span>
                                @endif
                                @if (! $isLate && ! $hasMissing)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200">Normal</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-4 text-center text-sm text-slate-500 dark:text-slate-400">
                            Aucune donnee de presence pour ce mois.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
