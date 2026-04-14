{{--
    Variables expected from parent (mypage.index):
    $staff              : Staff
    $presenceMonthStart : Carbon  (selected month)
    $presenceMinutes    : int     (total work minutes for the month)
    $presenceLateCount  : int
    $presenceEquivPaye  : float|null (null when hourly_wage not set or 0)
--}}
@php
    $prevMonth      = $presenceMonthStart->copy()->subMonth();
    $nextMonth      = $presenceMonthStart->copy()->addMonth();
    $businessNow    = \App\Support\BusinessDate::current();
    $isCurrentMonth = $presenceMonthStart->format('Y-m') === $businessNow->format('Y-m');

    $fmtHm = static function (int $minutes): string {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return sprintf('%d:%02d', $h, $m);
    };
@endphp

<section class="mt-4 overflow-hidden rounded-2xl border-2 border-black shadow-[0_6px_0_0_rgba(0,0,0,1)] dark:border-slate-600/80">

    {{-- ── ヘッダー ── --}}
    <div class="flex items-center justify-between bg-slate-900 px-3 py-2">
        <span class="font-mono text-[11px] font-black uppercase tracking-widest text-yellow-300">
            📊 Centre de Presence
        </span>
        <span class="font-mono text-[11px] font-bold text-slate-300">
            {{ $presenceMonthStart->translatedFormat('F Y') }}
        </span>
    </div>

    {{-- ── 月切替ナビ ── --}}
    <div class="flex items-center justify-between border-b-2 border-black bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
        <a
            href="{{ route('mypage.index', ['staff_id' => $staff->id, 'month' => $prevMonth->format('Y-m')]) }}"
            class="inline-flex items-center gap-1 rounded-lg border-2 border-black bg-slate-100 px-3 py-1 text-xs font-black text-slate-800 shadow-[0_2px_0_0_rgba(0,0,0,1)] transition hover:-translate-y-0.5 active:translate-y-0.5 active:shadow-none dark:border-slate-500 dark:bg-slate-800 dark:text-slate-100"
        >
            ◀ {{ $prevMonth->format('Y/m') }}
        </a>

        <div class="flex items-center gap-2">
            @unless ($isCurrentMonth)
                <a
                    href="{{ route('mypage.index', ['staff_id' => $staff->id]) }}"
                    class="rounded-lg border border-indigo-400 bg-indigo-50 px-2 py-1 text-xs font-bold text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-500/60 dark:bg-indigo-950/40 dark:text-indigo-300 dark:hover:bg-indigo-900/40"
                >
                    Mois courant
                </a>
            @endunless
        </div>

        <a
            href="{{ route('mypage.index', ['staff_id' => $staff->id, 'month' => $nextMonth->format('Y-m')]) }}"
            class="inline-flex items-center gap-1 rounded-lg border-2 border-black bg-slate-100 px-3 py-1 text-xs font-black text-slate-800 shadow-[0_2px_0_0_rgba(0,0,0,1)] transition hover:-translate-y-0.5 active:translate-y-0.5 active:shadow-none dark:border-slate-500 dark:bg-slate-800 dark:text-slate-100"
        >
            {{ $nextMonth->format('Y/m') }} ▶
        </a>
    </div>

    {{-- ── 集計カード ── --}}
    <div class="grid grid-cols-1 gap-2 bg-white p-3 sm:grid-cols-3 dark:bg-slate-900">

        {{-- 総労働時間 --}}
        <div class="flex items-center justify-between rounded-xl border-2 border-black bg-emerald-50 px-3 py-2 shadow-[0_3px_0_0_rgba(0,0,0,1)] dark:border-slate-600 dark:bg-emerald-950/30">
            <span class="font-mono text-xs font-black text-slate-700 dark:text-slate-200">Heures ce mois</span>
            <span class="font-mono text-sm font-black text-emerald-900 dark:text-emerald-300">
                {{ $fmtHm($presenceMinutes) }}
            </span>
        </div>

        {{-- 遅刻回数 --}}
        <div class="flex items-center justify-between rounded-xl border-2 border-black bg-amber-50 px-3 py-2 shadow-[0_3px_0_0_rgba(0,0,0,1)] dark:border-slate-600 dark:bg-amber-950/30">
            <span class="font-mono text-xs font-black text-slate-700 dark:text-slate-200">Retards ce mois</span>
            <span class="font-mono text-sm font-black text-amber-900 dark:text-amber-300">
                {{ $presenceLateCount }} <span class="text-xs font-normal">fois</span>
            </span>
        </div>

        {{-- 精勤バッジ / 遅刻あり表示 --}}
        @if ($presenceLateCount === 0)
            <div class="flex items-center justify-center rounded-xl border-2 border-black bg-gradient-to-r from-emerald-100 via-lime-100 to-cyan-100 px-3 py-2 shadow-[0_3px_0_0_rgba(0,0,0,1)] dark:border-slate-600 dark:from-emerald-950/40 dark:via-lime-950/30 dark:to-cyan-950/40">
                <span class="text-center text-xs font-extrabold text-emerald-700 dark:text-emerald-300">
                    🏅 Presence exemplaire&nbsp;!
                </span>
            </div>
        @else
            <div class="flex items-center justify-center rounded-xl border-2 border-black bg-rose-50 px-3 py-2 shadow-[0_3px_0_0_rgba(0,0,0,1)] dark:border-slate-600 dark:bg-rose-950/30">
                <span class="text-center text-xs font-bold text-rose-700 dark:text-rose-300">
                    ⚠️ {{ $presenceLateCount }} retard{{ $presenceLateCount > 1 ? 's' : '' }} enregistre{{ $presenceLateCount > 1 ? 's' : '' }}
                </span>
            </div>
        @endif
    </div>

    {{-- ── 給与見積（時給設定済みのみ） ── --}}
    @if ($presenceEquivPaye !== null)
        @php
            $hw       = (float) $staff->hourly_wage;
            $hwLabel  = number_format($hw, 3, '.', ' ');
            $payLabel = number_format($presenceEquivPaye, 3, '.', ' ');
        @endphp
        <div
            class="relative overflow-hidden border-t-2 border-black bg-gradient-to-b from-sky-400 via-sky-500 to-sky-600 p-4 text-gray-950 dark:border-slate-600 dark:from-slate-800 dark:via-slate-900 dark:to-slate-950 dark:text-white"
            x-data="{ sparkle: false }"
            x-init="setInterval(() => { sparkle = true; setTimeout(() => sparkle = false, 420); }, 5200)"
        >
            <div class="pointer-events-none absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/25 dark:bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-8 left-1/4 h-16 w-32 rounded-full bg-black/10 dark:bg-black/30"></div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 flex-1 items-start gap-3">
                    <div
                        class="flex h-14 w-14 shrink-0 select-none items-center justify-center rounded border-4 border-black bg-[#f7c948] text-2xl font-black text-gray-950 shadow-[inset_0_-4px_0_0_rgba(0,0,0,0.15)] animate-mario-bump dark:bg-[#e6b422]"
                        role="presentation"
                        aria-hidden="true"
                    >?</div>
                    <div class="min-w-0">
                        <div class="mt-0.5 font-mono text-sm font-black leading-snug text-gray-950 drop-shadow-[0_1px_0_rgba(255,255,255,0.35)] dark:text-white dark:drop-shadow-none">
                            Salaire estime (ce mois)
                        </div>
                        <div class="mt-1 font-mono text-xs font-semibold text-gray-900/90 dark:text-slate-200">
                            <span class="whitespace-nowrap">{{ $hwLabel }}&nbsp;DT/h</span>
                            <span class="mx-1 font-black text-gray-950 dark:text-white">×</span>
                            <span class="whitespace-nowrap">{{ $fmtHm($presenceMinutes) }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-3 sm:flex-col sm:items-end sm:gap-1">
                    <span class="inline-flex text-3xl leading-none animate-mario-coin-float motion-reduce:animate-none" role="presentation" aria-hidden="true">🪙</span>
                    <div
                        class="rounded-xl border-4 border-black bg-white px-4 py-2 text-right font-mono text-lg font-black tabular-nums text-emerald-900 shadow-[inset_0_3px_0_0_rgba(0,0,0,0.06)] transition duration-150 dark:bg-amber-100 dark:text-emerald-950"
                        :class="sparkle ? 'ring-4 ring-yellow-300 ring-offset-2 ring-offset-sky-500 scale-[1.02] dark:ring-amber-400 dark:ring-offset-slate-900' : ''"
                    >
                        {{ $payLabel }}&nbsp;<span class="text-base font-black">DT</span>
                    </div>
                </div>
            </div>
           
        </div>
    @endif

</section>
