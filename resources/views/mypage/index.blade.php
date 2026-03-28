<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>マイページ（タスク・棚卸し） — {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
    <x-client-nav />

    <div class="container py-3 py-md-4 mx-auto" style="max-width: 28rem;">
        <nav class="mb-3" aria-label="breadcrumb">
            <ol class="breadcrumb small mb-0">
                <li class="breadcrumb-item">
                    <a href="{{ route('home') }}" class="text-decoration-none">
                        <i class="bi bi-house-door-fill me-1" aria-hidden="true"></i>トップ
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">マイページ</li>
            </ol>
        </nav>

        @if (session('status'))
            <div class="alert alert-success shadow-sm rounded-4" role="alert">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger shadow-sm rounded-4" role="alert">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger shadow-sm rounded-4" role="alert">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="get" action="{{ route('mypage.index') }}" class="mb-4">
            <label for="staff_select" class="form-label fw-semibold">スタッフを選択</label>
            <select name="staff_id" id="staff_select" class="form-select form-select-lg rounded-4 shadow-sm" onchange="this.form.submit()">
                <option value="">— 選択 —</option>
                @foreach ($staffList as $s)
                    <option value="{{ $s->id }}" @selected($staff && $staff->id === $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </form>

        @if (! $staff)
            <div class="border border-2 border-dashed rounded-4 p-5 text-center text-secondary bg-white shadow-sm">
                <i class="bi bi-person-badge fs-1 d-block mb-2 opacity-50" aria-hidden="true"></i>
                <p class="mb-0">スタッフを選ぶと、本日のタスクと棚卸しの状況が表示されます。</p>
            </div>
        @else
            <div class="card bg-dark text-white mb-4 shadow-sm rounded-4 border-0">
                <div class="card-body p-4">
                    <p class="text-white-50 small text-uppercase mb-1 letter-spacing">マイページ</p>
                    <h2 class="h3 fw-bold mb-3">お疲れ様です、{{ $staff->name }}さん！</h2>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="badge bg-warning text-dark fs-6 px-3 py-2 rounded-pill shadow-sm">
                            <i class="bi bi-trophy-fill me-1" aria-hidden="true"></i>Lv. {{ $motivationLevel }}
                        </span>
                        @if ($todayClockInLabel)
                            <span class="badge bg-info text-dark fs-6 px-3 py-2 rounded-pill shadow-sm">
                                <i class="bi bi-clock-history me-1" aria-hidden="true"></i>出勤: {{ $todayClockInLabel }}
                            </span>
                        @else
                            <span class="badge bg-secondary fs-6 px-3 py-2 rounded-pill shadow-sm">
                                <i class="bi bi-clock me-1" aria-hidden="true"></i>出勤: 未打刻
                            </span>
                        @endif
                    </div>
                    <p class="text-white-50 small mb-0 mt-3">営業日 <span class="font-monospace text-white">{{ $dateString }}</span></p>
                </div>
            </div>

            <form method="post" action="{{ route('mypage.store') }}" id="mypage-routine-form" class="pb-5" style="padding-bottom: 8rem;">
                @csrf
                <input type="hidden" name="staff_id" value="{{ $staff->id }}">

                @php
                    $routineCardClass = $routineTasks->isEmpty()
                        ? 'border-secondary border-2 bg-light'
                        : ($routinesAllComplete
                            ? 'border-success border-2 bg-success-subtle'
                            : 'border-danger border-2 bg-danger-subtle');
                @endphp
                <section class="card shadow-sm rounded-4 mb-4 {{ $routineCardClass }}" aria-labelledby="routine-heading">
                    <div class="card-body p-4">
                        <h2 id="routine-heading" class="h5 fw-bold mb-3">
                            <i class="bi bi-check2-square me-2" aria-hidden="true"></i>ルーティンタスク
                        </h2>

                        @if ($routineTasks->isEmpty())
                            <p class="text-secondary mb-0">
                                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>割り当てられたタスクはありません。
                            </p>
                        @elseif ($routinesAllComplete)
                            <p class="fw-bold text-success fs-5 mb-4 d-flex align-items-center gap-2">
                                <i class="bi bi-stars" aria-hidden="true"></i>
                                <span>✨ Bravo! 全てのタスクが完了しました！</span>
                            </p>
                            <ul class="list-unstyled mb-0">
                                @foreach ($routineTasks as $task)
                                    <li class="mb-3 pb-3 border-bottom border-success border-opacity-25">
                                        <div class="form-check d-flex align-items-start gap-3">
                                            <input
                                                class="form-check-input fs-3 flex-shrink-0 mt-1"
                                                type="checkbox"
                                                name="routine_task[{{ $task->id }}]"
                                                value="1"
                                                id="routine-{{ $task->id }}"
                                                @checked(old('routine_task.'.$task->id, $routineLogIds->contains($task->id)))
                                            >
                                            <label class="form-check-label fs-6" for="routine-{{ $task->id }}">
                                                <span class="d-block text-secondary small">{{ $task->category }} · {{ $task->timing }}</span>
                                                <span class="fw-semibold">{{ $task->name }}</span>
                                            </label>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="fw-bold text-danger fs-5 mb-4 d-flex align-items-center gap-2">
                                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                                <span>⚠️ 未完了のタスクがあります！</span>
                            </p>
                            <ul class="list-unstyled mb-0">
                                @foreach ($routineTasks as $task)
                                    <li class="mb-3 pb-3 border-bottom border-danger border-opacity-25">
                                        <div class="form-check d-flex align-items-start gap-3">
                                            <input
                                                class="form-check-input fs-3 flex-shrink-0 mt-1"
                                                type="checkbox"
                                                name="routine_task[{{ $task->id }}]"
                                                value="1"
                                                id="routine-{{ $task->id }}"
                                                @checked(old('routine_task.'.$task->id, $routineLogIds->contains($task->id)))
                                            >
                                            <label class="form-check-label fs-6" for="routine-{{ $task->id }}">
                                                <span class="d-block text-secondary small">{{ $task->category }} · {{ $task->timing }}</span>
                                                <span class="fw-semibold">{{ $task->name }}</span>
                                            </label>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($routineTasks->isNotEmpty())
                            <div class="mt-4">
                                <label for="pin_code_routine" class="form-label fw-semibold">保存用 PIN（4桁）</label>
                                <input
                                    type="password"
                                    name="pin_code"
                                    id="pin_code_routine"
                                    inputmode="numeric"
                                    maxlength="4"
                                    required
                                    class="form-control form-control-lg font-monospace text-center rounded-4 py-3 fs-4"
                                    placeholder="••••"
                                    autocomplete="one-time-code"
                                >
                            </div>
                        @endif
                    </div>
                </section>

                <section class="mb-2" aria-labelledby="inventory-heading">
                    <h2 id="inventory-heading" class="h5 fw-bold mb-3 px-1">
                        <i class="bi bi-box-seam me-2" aria-hidden="true"></i>棚卸しスケジュール
                    </h2>

                    @if (empty($inventoryTimingRows))
                        <div class="card border-secondary border-2 bg-light rounded-4 shadow-sm">
                            <div class="card-body p-4 text-center text-secondary">
                                <i class="bi bi-inbox fs-2 d-block mb-2 opacity-50" aria-hidden="true"></i>
                                割り当てられた棚卸しはありません。
                            </div>
                        </div>
                    @else
                        @foreach ($inventoryTimingRows as $row)
                            @if ($row['complete'])
                                <div class="card border-success border-2 bg-success-subtle rounded-4 shadow-sm mb-3">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                            <div>
                                                <span class="badge bg-success fs-6 px-3 py-2 rounded-pill">完了</span>
                                                <h3 class="h6 fw-bold mt-2 mb-0">{{ $row['label'] }}</h3>
                                                <p class="text-secondary small mb-0 mt-1">タイミング: <span class="font-monospace">{{ $row['timing_key'] }}</span></p>
                                            </div>
                                        </div>
                                        <p class="fw-bold text-success fs-5 mb-3 d-flex align-items-center gap-2">
                                            <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
                                            <span>🌿 Merci! 完了済</span>
                                        </p>
                                        <a href="{{ $row['portal_url'] }}" class="btn btn-outline-success btn-lg w-100 py-3 fs-5 rounded-4">
                                            <i class="bi bi-arrow-up-right-circle me-2" aria-hidden="true"></i>棚卸し内容を確認（Retry）
                                        </a>
                                    </div>
                                </div>
                            @else
                                <div class="card border-danger border-2 bg-danger-subtle rounded-4 shadow-sm mb-3">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                            <div>
                                                <span class="badge bg-danger fs-6 px-3 py-2 rounded-pill">未実施</span>
                                                <h3 class="h6 fw-bold mt-2 mb-0">{{ $row['label'] }}</h3>
                                                <p class="text-secondary small mb-0 mt-1">
                                                    タイミング: <span class="font-monospace">{{ $row['timing_key'] }}</span>
                                                    · {{ $row['filled'] }}/{{ $row['total'] }} 入力済
                                                </p>
                                            </div>
                                        </div>
                                        <a href="{{ $row['portal_url'] }}" class="btn btn-danger w-100 py-3 fs-5 rounded-4 shadow-sm">
                                            <span class="me-1" aria-hidden="true">🚨</span>棚卸しを実施する（棚卸しポータルへ）
                                        </a>
                                        <a href="{{ route('inventory.index') }}" class="btn btn-link w-100 mt-2 text-secondary small">
                                            全タイミング一覧を見る
                                        </a>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    @endif
                </section>
            </form>

            @if ($routineTasks->isNotEmpty())
                <div class="position-fixed bottom-0 start-0 end-0 bg-white border-top shadow py-3 px-3" style="z-index: 1020;">
                    <div class="mx-auto" style="max-width: 28rem;">
                        <button type="submit" form="mypage-routine-form" class="btn btn-dark btn-lg w-100 py-3 fs-4 rounded-4 shadow-sm">
                            <i class="bi bi-cloud-check-fill me-2" aria-hidden="true"></i>完了して保存
                        </button>
                    </div>
                </div>
            @endif
        @endif
    </div>

    @if (session('success_modal') || session('late_modal'))
        <div
            class="modal fade"
            id="mypageTimecardModal"
            tabindex="-1"
            aria-labelledby="mypageTimecardModalLabel"
            aria-hidden="true"
        >
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content text-dark rounded-4 shadow">
                    <div class="modal-header border-0 pb-0">
                        <h2 class="modal-title fs-5" id="mypageTimecardModalLabel">
                            @if (session('late_modal'))
                                遅刻として記録されました
                            @else
                                打刻が完了しました
                            @endif
                        </h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                    </div>
                    <div class="modal-body pt-2">
                        @if (session('late_modal'))
                            <p class="text-secondary small mb-2">予定時刻（10分の猶予を除く）より遅い打刻として記録されています。</p>
                            @if (session('late_minutes'))
                                <p class="mb-0 fw-medium">
                                    遅刻: <span class="font-monospace">{{ session('late_minutes') }}</span> 分
                                </p>
                            @endif
                        @else
                            <p class="text-secondary small mb-0">出勤打刻を保存しました。今日のタスクを進めましょう。</p>
                        @endif
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-primary w-100 py-3 rounded-4" data-bs-dismiss="modal">OK</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    @if (session('success_modal') || session('late_modal'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var el = document.getElementById('mypageTimecardModal');
                if (el && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getOrCreateInstance(el).show();
                }
            });
        </script>
    @endif
</body>
</html>
