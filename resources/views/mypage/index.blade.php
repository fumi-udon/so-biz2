<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>マイページ — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/mypage.css') }}">
</head>
<body
    class="mypage-body min-h-screen bg-linear-to-br from-pink-100 via-yellow-100 to-cyan-100 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950"
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
                'late' => "{$p} ▶ {$icon} 未出勤",
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

    <nav class="mypage-nav">
        <a href="{{ url('/') }}" class="mypage-nav-link">TOP</a>
        <a href="{{ route('timecard.index') }}" class="mypage-nav-link">Timecard</a>
        <a href="{{ route('mypage.index', ['staff_id' => $staff?->id]) }}" class="mypage-nav-link is-current">MyPage</a>
        <a href="{{ route('timecard.index') }}" class="mypage-nav-link is-logout">Logout</a>
    </nav>

    <main class="mypage-shell mx-auto w-full max-w-3xl px-2 py-2 sm:px-3">
        @if (session('status'))
            <div class="mb-2 rounded-xl border border-emerald-300 bg-emerald-50 px-2 py-1 text-xs text-emerald-700 dark:border-emerald-600/30 dark:bg-emerald-900/20 dark:text-emerald-300">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-2 rounded-xl border border-rose-300 bg-rose-50 px-2 py-1 text-xs text-rose-700 dark:border-rose-600/30 dark:bg-rose-900/20 dark:text-rose-300">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-2 rounded-xl border border-rose-300 bg-rose-50 px-2 py-1 text-xs text-rose-700 dark:border-rose-600/30 dark:bg-rose-900/20 dark:text-rose-300">{{ $errors->first() }}</div>
        @endif

        @php
            $tipComboCount = (int) ($monthTipWinCount ?? 0);
            $damageCount = (int) ($monthDamageCount ?? 0);
            $tickerMessage = match (true) {
                ($lunchStatus ?? 'none') === 'late' => 'ALERT: ランチ打刻が遅延中。至急チェックイン！',
                ($dinnerStatus ?? 'none') === 'late' => 'ALERT: ディナー打刻が遅延中。至急チェックイン！',
                ($lunchStatus ?? 'none') === 'future' => '本日ランチ: チップ申請待ち / 開始待機中',
                ($dinnerStatus ?? 'none') === 'future' => '本日ディナー: チップ申請待ち / 開始待機中',
                default => 'TIP SYSTEM PHASE 1 ONLINE - COMBOを積み上げろ！',
            };
        @endphp
        <section class="mb-2 rounded-2xl border border-amber-300/80 bg-linear-to-r from-red-500 via-amber-400 to-yellow-300 p-px shadow-sm shadow-amber-300/50 dark:border-amber-400/30 dark:from-red-900/70 dark:via-amber-700/60 dark:to-yellow-700/60">
            <div class="rounded-[15px] bg-black/85 px-2 py-2 text-white">
                <div class="mb-1 flex items-center justify-between gap-2 text-[10px] font-extrabold tracking-wide">
                    <span class="rounded bg-red-600/90 px-1.5 py-0.5 text-yellow-200 ring-1 ring-red-300/50">ROUND 1</span>
                    <span class="truncate text-right text-yellow-200">MYPAGE BATTLE HUD</span>
                </div>
                <div class="mb-1 overflow-hidden rounded border border-yellow-300/40 bg-black/60 px-2 py-1">
                    <p class="ticker-text whitespace-nowrap text-[11px] font-extrabold text-yellow-200">
                        {{ $tickerMessage }}
                    </p>
                </div>
                <div class="grid grid-cols-1 gap-1 text-[11px] font-black sm:grid-cols-2">
                    <div class="rounded border border-yellow-300/50 bg-linear-to-r from-amber-500/30 to-yellow-400/30 px-2 py-1 text-yellow-100">
                        🔥 TIP COMBO: <span class="text-base text-yellow-200">{{ $tipComboCount }}</span>回
                    </div>
                    <div class="rounded border border-red-300/60 bg-linear-to-r from-rose-600/35 to-red-500/30 px-2 py-1 text-red-100">
                        💀 LATE (DAMAGE): <span class="text-base text-red-200">{{ $damageCount }}</span>回
                    </div>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <article class="mypage-card profile-card rounded-2xl border border-fuchsia-300/80 bg-linear-to-br from-violet-100 via-fuchsia-100 to-sky-100 p-2 text-gray-900 shadow-sm shadow-fuchsia-200/60 dark:border-fuchsia-500/30 dark:from-gray-900 dark:via-purple-950/60 dark:to-slate-900 dark:text-gray-100">
                <div class="mb-1 flex items-center justify-between">
                    <h1 class="text-sm font-bold text-violet-700 dark:text-violet-200">👑 Profile</h1>
                    <span class="text-[10px] font-semibold text-violet-700 dark:text-violet-200">本日: {{ $businessDate->format('Y-m-d') }}</span>
                    <span class="inline-flex items-center gap-1 rounded-full bg-white/80 px-1.5 py-0.5 text-[10px] font-semibold text-violet-700 ring-1 ring-violet-300/70 dark:bg-white/10 dark:text-violet-200 dark:ring-violet-400/30">
                        <span class="h-1.5 w-1.5 rounded-full bg-cyan-500 animate-pulse"></span>
                        <span id="idle-timer">180s</span>
                    </span>
                </div>
                @if ($staff)
                    <div class="mx-1 rounded-xl border border-violet-300/70 bg-white/70 p-2 backdrop-blur-sm dark:border-violet-400/30 dark:bg-white/5">
                        <div class="flex items-center gap-2 text-xs">
                            <span class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm font-extrabold {{ $roleChipClass }}">
                                <span>{{ $roleIcon }}</span>
                                <span>{{ \Illuminate\Support\Str::limit($roleLabel ?? 'Other', 5, '') }}</span>
                            </span>
                            <span class="truncate text-base font-extrabold tracking-wide text-violet-800 dark:text-violet-100">{{ $staff->name }}</span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-linear-to-r from-amber-300 via-orange-300 to-pink-300 px-2 py-0.5 text-[11px] font-extrabold text-gray-900 ring-1 ring-amber-200/70 animate-pulse">
                                <span class="animate-bounce">{{ $levelIcon }}</span>
                                <span>Lv.{{ $motivationLevel }}</span>
                            </span>
                        </div>
                    </div>
                    <div class="attendance-card mt-1 rounded-lg border border-emerald-300/70 bg-white/70 p-1.5 dark:border-emerald-400/20 dark:bg-white/5">
                        <div class="mb-1 text-[10px] font-extrabold text-emerald-700 dark:text-emerald-200">⏰ Attendance</div>
                        <div class="space-y-1 text-[10px] leading-tight">
                            <span class="incident-trigger block rounded border border-amber-300 bg-amber-100 px-1.5 py-1 font-extrabold text-amber-800 dark:border-amber-500/30 dark:bg-amber-900/30 dark:text-amber-200">
                                当月の遅刻: {{ (int) ($monthLateCount ?? 0) }}回
                                <span class="incident-tooltip" role="tooltip">
                                    <strong>今月の遅刻日</strong>
                                    @forelse (($monthLateDates ?? collect()) as $line)
                                        <span>{{ $line }}</span>
                                    @empty
                                        <span>発生なし</span>
                                    @endforelse
                                </span>
                            </span>
                            <span class="incident-trigger block rounded border border-slate-300 bg-slate-100 px-1.5 py-1 font-extrabold text-slate-700 dark:border-slate-500/30 dark:bg-slate-800/40 dark:text-slate-200">
                                当月の欠勤: {{ (int) ($monthAbsentCount ?? 0) }}回
                                <span class="incident-tooltip" role="tooltip">
                                    <strong>今月の欠勤日</strong>
                                    @forelse (($monthAbsentDates ?? collect()) as $line)
                                        <span>{{ $line }}</span>
                                    @empty
                                        <span>発生なし</span>
                                    @endforelse
                                </span>
                            </span>
                            @if (($monthLateCount ?? 0) === 0 && ($monthAbsentCount ?? 0) === 0)
                                <div class="rounded-md border border-emerald-300 bg-linear-to-r from-emerald-100 via-lime-100 to-cyan-100 px-1.5 py-1 text-[10px] font-extrabold text-emerald-700 animate-pulse dark:border-emerald-500/30 dark:from-emerald-900/30 dark:via-lime-900/20 dark:to-cyan-900/30 dark:text-emerald-200">
                                    🏅 勤怠優秀 Bravo!
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="tip-history-panel mt-1 rounded-lg border border-violet-300/70 bg-white/70 p-1 dark:border-violet-400/20 dark:bg-white/5">
                        <div class="tip-history-title mb-1 text-[10px] font-extrabold text-violet-700 dark:text-violet-200">
                            <span>💠</span>
                            <span>過去チップ</span>
                            <span class="tip-history-sub">Last 3</span>
                        </div>
                        <table class="tip-history-table w-full text-[10px]">
                            <thead>
                                <tr class="border-b border-violet-200/80 dark:border-violet-500/20">
                                    <th class="px-1 py-0.5 text-left font-semibold">📅 日付</th>
                                    <th class="px-1 py-0.5 text-right font-semibold">☀️ Lunch</th>
                                    <th class="px-1 py-0.5 text-right font-semibold">🌙 Dinner</th>
                                    <th class="px-1 py-0.5 text-right font-semibold">💎 Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (($tipRecentNonZero3 ?? collect()) as $d)
                                    <tr class="tip-row border-b border-violet-100/90 dark:border-violet-500/10 last:border-b-0">
                                        <td class="px-1 py-0.5 font-mono">{{ $d['date'] }}</td>
                                        <td class="px-1 py-0.5 text-right font-mono">{{ number_format((float) ($d['lunch'] ?? 0), 1) }}</td>
                                        <td class="px-1 py-0.5 text-right font-mono">{{ number_format((float) ($d['dinner'] ?? 0), 1) }}</td>
                                        <td class="px-1 py-0.5 text-right font-mono font-extrabold">{{ number_format((float) ($d['total'] ?? 0), 1) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-1 py-1 text-center font-semibold text-violet-700/80 dark:text-violet-200/80">non</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-1 flex items-center justify-end text-[10px]">
                        <a href="{{ route('timecard.index') }}" class="rounded bg-white/80 px-2 py-1 font-semibold text-violet-700 ring-1 ring-violet-300/60 dark:bg-white/10 dark:text-violet-200">戻る</a>
                    </div>
                @else
                    <p class="text-xs text-gray-600 dark:text-gray-300">PIN認証が必要です。トップから再度マイページを開いてください。</p>
                @endif
            </article>

            <article class="mypage-card task-card sm:col-span-2 rounded-2xl border border-rose-300/80 bg-linear-to-br from-rose-100 via-orange-100 to-indigo-100 p-3 text-gray-900 shadow-sm shadow-rose-200/40 dark:border-rose-500/30 dark:from-gray-900 dark:via-rose-950/40 dark:to-indigo-950/40 dark:text-gray-100">
                <h2 class="mb-2 text-sm font-bold text-rose-700 dark:text-rose-200">📋 Task（最重要）</h2>
                @if ($staff)
                    <div class="task-table-wrap overflow-x-auto rounded-lg border-2 border-rose-300/80 bg-rose-50/80 ring-2 ring-rose-300/50 dark:border-rose-500/30 dark:bg-rose-950/20 dark:ring-rose-500/20">
                        <table class="task-table w-full min-w-[16rem] text-[10px]">
                            <thead>
                                <tr class="border-b border-rose-200 bg-rose-100/80 dark:border-rose-500/20 dark:bg-rose-900/30">
                                    <th class="px-1.5 py-0.5 text-left font-semibold">種別</th>
                                    <th class="px-1.5 py-0.5 text-left font-semibold">項目</th>
                                    <th class="px-1.5 py-0.5 text-center font-semibold">進捗</th>
                                    <th class="px-1.5 py-0.5 text-center font-semibold">状態</th>
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
                                            <span class="{{ $done ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300 animate-pulse font-extrabold' }}">{{ $done ? '完了' : '未完了' }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-1.5 py-1 text-center text-gray-600 dark:text-gray-300">ルーティン割り当てなし</td></tr>
                                @endforelse

                                @forelse ($inventoryTimingRows as $row)
                                    <tr class="border-b border-rose-100 dark:border-rose-500/10 last:border-b-0">
                                        <td class="px-1.5 py-0.5">📦 Inventory</td>
                                        <td class="px-1.5 py-0.5 truncate max-w-36">{{ $row['label'] }}</td>
                                        <td class="px-1.5 py-0.5 text-center">{{ $row['filled'] }}/{{ $row['total'] }}</td>
                                        <td class="px-1.5 py-0.5 text-center">
                                            <span class="{{ $row['complete'] ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-500 animate-pulse font-extrabold' }}">{{ $row['complete'] ? '完了' : '未実施' }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-1.5 py-1 text-center text-gray-600 dark:text-gray-300">棚卸し割り当てなし</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-1 grid grid-cols-2 gap-1 text-[10px]">
                        <span class="{{ $routinesPendingCount > 0 ? 'text-rose-600 dark:text-rose-300 animate-pulse' : 'text-emerald-600 dark:text-emerald-300' }}">
                            ルーティン: {{ $routinesPendingCount > 0 ? '未完了あり' : '全完了' }}
                        </span>
                        <span class="{{ $inventoryIncomplete ? 'text-rose-500 animate-pulse' : 'text-emerald-600 dark:text-emerald-300' }}">
                            棚卸し: {{ $inventoryIncomplete ? '未実施あり' : '完了' }}
                        </span>
                    </div>
                @else
                    <p class="text-[10px] text-gray-600 dark:text-gray-300">スタッフを選択すると業務一覧を表示します。</p>
                @endif
            </article>
        </section>
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

            reset();
            applyTipRowVisibility();
            ticking = setInterval(tick, 1000);
        })();
    </script>
</body>
</html>
