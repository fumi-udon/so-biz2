<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>勤怠（マイページ） — {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
    <x-client-nav />

    <div class="container py-3 py-md-4 mx-auto" style="max-width: 48rem;">
        <nav class="mb-3" aria-label="breadcrumb">
            <ol class="breadcrumb small mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-decoration-none">トップ</a></li>
                <li class="breadcrumb-item"><a href="{{ route('mypage.index') }}" class="text-decoration-none">マイページ</a></li>
                <li class="breadcrumb-item active" aria-current="page">勤怠</li>
            </ol>
        </nav>

        @if (session('status'))
            <div class="alert alert-success rounded-4 shadow-sm">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger rounded-4 shadow-sm">{{ session('error') }}</div>
        @endif

        <form method="get" action="{{ route('mypage.attendance') }}" class="mb-4">
            <label for="staff_select" class="form-label fw-semibold">スタッフを選択</label>
            <select name="staff_id" id="staff_select" class="form-select form-select-lg rounded-4" onchange="this.form.submit()">
                <option value="">— 選択 —</option>
                @foreach ($staffList as $s)
                    <option value="{{ $s->id }}" @selected($staff && $staff->id === $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </form>

        @if (! $staff)
            <div class="border border-2 border-dashed rounded-4 p-5 text-center text-secondary bg-white shadow-sm">
                スタッフを選ぶと、月次の勤怠と統計が表示されます。
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

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h1 class="h6 fw-bold mb-0">{{ $monthStart->translatedFormat('Y年n月') }} の勤怠</h1>
                <div class="btn-group">
                    <a href="{{ route('mypage.attendance', ['staff_id' => $staff->id, 'month' => $prevMonth->format('Y-m')]) }}" class="btn btn-outline-secondary btn-sm rounded-start-4">&laquo;</a>
                    <a href="{{ route('mypage.attendance', ['staff_id' => $staff->id, 'month' => now()->format('Y-m')]) }}" class="btn btn-outline-secondary btn-sm">今月</a>
                    <a href="{{ route('mypage.attendance', ['staff_id' => $staff->id, 'month' => $nextMonth->format('Y-m')]) }}" class="btn btn-outline-secondary btn-sm rounded-end-4">&raquo;</a>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-primary-subtle">
                        <div class="card-body">
                            <p class="text-primary small mb-1 fw-semibold">今週の労働時間</p>
                            <p class="h4 mb-0 font-monospace">{{ $fmtHm($weekMinutes) }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-success-subtle">
                        <div class="card-body">
                            <p class="text-success small mb-1 fw-semibold">当月の労働時間</p>
                            <p class="h4 mb-0 font-monospace">{{ $fmtHm($monthMinutes) }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-warning-subtle">
                        <div class="card-body">
                            <p class="text-warning-emphasis small mb-1 fw-semibold">当月の遅刻回数</p>
                            <p class="h4 mb-0 font-monospace">{{ $monthLateCount }} <span class="fs-6">回</span></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive shadow-sm rounded-4 bg-white">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">日付</th>
                            <th scope="col">L 出</th>
                            <th scope="col">L 退</th>
                            <th scope="col">D 出</th>
                            <th scope="col">D 退</th>
                            <th scope="col" class="text-end">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($monthAttendances as $att)
                            @php
                                $d = $att->date;
                                $dateLabel = $d instanceof \Carbon\Carbon ? $d->format('m/d (D)') : \Carbon\Carbon::parse($d)->format('m/d (D)');
                            @endphp
                            <tr>
                                <td class="small fw-semibold">{{ $dateLabel }}</td>
                                <td class="font-monospace small">{{ $att->lunch_in_at?->format('H:i') ?? '—' }}</td>
                                <td class="font-monospace small @if($att->lunch_in_at && ! $att->lunch_out_at) text-danger fw-bold @endif">{{ $att->lunch_out_at?->format('H:i') ?? ($att->lunch_in_at ? '未退勤' : '—') }}</td>
                                <td class="font-monospace small">{{ $att->dinner_in_at?->format('H:i') ?? '—' }}</td>
                                <td class="font-monospace small @if($att->dinner_in_at && ! $att->dinner_out_at) text-danger fw-bold @endif">{{ $att->dinner_out_at?->format('H:i') ?? ($att->dinner_in_at ? '未退勤' : '—') }}</td>
                                <td class="text-end text-nowrap">
                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary btn-sm py-2 px-2"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalClockIn"
                                        data-attendance-id="{{ $att->id }}"
                                        data-staff-id="{{ $staff->id }}"
                                        data-month="{{ $monthStart->format('Y-m') }}"
                                        data-lunch-in="{{ $att->lunch_in_at?->format('H:i') ?? '' }}"
                                        data-dinner-in="{{ $att->dinner_in_at?->format('H:i') ?? '' }}"
                                    >出勤</button>
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary btn-sm py-2 px-2"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalClockOut"
                                        data-attendance-id="{{ $att->id }}"
                                        data-staff-id="{{ $staff->id }}"
                                        data-month="{{ $monthStart->format('Y-m') }}"
                                        data-lunch-out="{{ $att->lunch_out_at?->format('H:i') ?? '' }}"
                                        data-dinner-out="{{ $att->dinner_out_at?->format('H:i') ?? '' }}"
                                    >退勤</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($monthAttendances->isEmpty())
                <p class="text-secondary small mt-3 mb-0">この月の勤怠データはまだありません。</p>
            @endif
        @endif
    </div>

    @if ($staff)
        <div class="modal fade" id="modalClockOut" tabindex="-1" aria-labelledby="modalClockOutLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4">
                    <div class="modal-header border-0">
                        <h2 class="modal-title fs-5" id="modalClockOutLabel">退勤時間の編集</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                    </div>
                    <form method="post" action="{{ route('mypage.attendance.update') }}">
                        @csrf
                        <input type="hidden" name="mode" value="out">
                        <input type="hidden" name="attendance_id" id="out_attendance_id" value="">
                        <input type="hidden" name="staff_id" value="{{ $staff->id }}">
                        <div class="modal-body pt-0">
                            <p class="text-secondary small">本人の PIN のみで保存できます。</p>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">ランチ退勤</label>
                                <input type="time" name="lunch_out" id="out_lunch_out" class="form-control form-control-lg font-monospace text-center rounded-4 py-3">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">ディナー退勤</label>
                                <input type="time" name="dinner_out" id="out_dinner_out" class="form-control form-control-lg font-monospace text-center rounded-4 py-3">
                            </div>
                            <div class="mb-0">
                                <label class="form-label small fw-semibold">本人 PIN（4桁）</label>
                                <input type="password" name="pin_code" inputmode="numeric" maxlength="4" required class="form-control form-control-lg font-monospace text-center rounded-4 py-3" placeholder="••••" autocomplete="one-time-code">
                            </div>
                        </div>
                        <div class="modal-footer border-0 flex-column gap-2">
                            <button type="submit" class="btn btn-primary btn-lg w-100 rounded-4 py-3">保存する</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="modalClockIn" tabindex="-1" aria-labelledby="modalClockInLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4">
                    <div class="modal-header border-0">
                        <h2 class="modal-title fs-5" id="modalClockInLabel">出勤時間の編集</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                    </div>
                    <form method="post" action="{{ route('mypage.attendance.update') }}">
                        @csrf
                        <input type="hidden" name="mode" value="in">
                        <input type="hidden" name="attendance_id" id="in_attendance_id" value="">
                        <input type="hidden" name="staff_id" value="{{ $staff->id }}">
                        <div class="modal-body pt-0">
                            <p class="text-secondary small">出勤の変更は「マネージャー」PIN の承認が必要です。</p>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">ランチ出勤</label>
                                <input type="time" name="lunch_in" id="in_lunch_in" class="form-control form-control-lg font-monospace text-center rounded-4 py-3">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">ディナー出勤</label>
                                <input type="time" name="dinner_in" id="in_dinner_in" class="form-control form-control-lg font-monospace text-center rounded-4 py-3">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">本人 PIN（4桁）</label>
                                <input type="password" name="pin_code" inputmode="numeric" maxlength="4" required class="form-control form-control-lg font-monospace text-center rounded-4 py-3" placeholder="••••" autocomplete="one-time-code">
                            </div>
                            <div class="mb-0">
                                <label class="form-label small fw-semibold">マネージャー PIN（4桁）</label>
                                <input type="password" name="manager_pin" inputmode="numeric" maxlength="4" required class="form-control form-control-lg font-monospace text-center rounded-4 py-3 border-danger" placeholder="••••" autocomplete="one-time-code">
                            </div>
                        </div>
                        <div class="modal-footer border-0 flex-column gap-2">
                            <button type="submit" class="btn btn-primary btn-lg w-100 rounded-4 py-3">保存する</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    @if ($staff)
        <script>
            (function () {
                function bindModal(modalId, hiddenId, fieldMap) {
                    const modal = document.getElementById(modalId);
                    if (!modal) return;
                    modal.addEventListener('show.bs.modal', function (event) {
                        const btn = event.relatedTarget;
                        if (!btn) return;
                        const hid = document.getElementById(hiddenId);
                        if (hid) hid.value = btn.getAttribute('data-attendance-id') || '';
                        Object.keys(fieldMap).forEach(function (inputId) {
                            const inp = document.getElementById(inputId);
                            if (!inp) return;
                            inp.value = btn.getAttribute(fieldMap[inputId]) || '';
                        });
                    });
                }
                bindModal('modalClockOut', 'out_attendance_id', {
                    out_lunch_out: 'data-lunch-out',
                    out_dinner_out: 'data-dinner-out',
                });
                bindModal('modalClockIn', 'in_attendance_id', {
                    in_lunch_in: 'data-lunch-in',
                    in_dinner_in: 'data-dinner-in',
                });
            })();
        </script>
    @endif
</body>
</html>
