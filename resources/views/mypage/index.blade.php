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

    <div class="container py-2 py-md-3 mx-auto" style="max-width: 28rem;">
        <nav class="mb-2" aria-label="breadcrumb">
            <ol class="breadcrumb small mb-0 py-1">
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

        <div class="mb-2">
            <form method="get" action="{{ route('mypage.index') }}" class="d-flex flex-wrap align-items-center gap-2">
                <label for="staff_select" class="small text-secondary mb-0">切替</label>
                <select name="staff_id" id="staff_select" class="form-select form-select-sm rounded-3 flex-grow-1" style="max-width: 14rem;" onchange="this.form.submit()">
                    <option value="">— 選択 —</option>
                    @foreach ($staffList as $s)
                        <option value="{{ $s->id }}" @selected($staff && $staff->id === $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary rounded-3"
                    data-bs-toggle="modal"
                    data-bs-target="#mypagePinModal"
                    title="名前を選んでからPINで開く"
                >
                    PIN
                </button>
            </form>
        </div>

        @if (! $staff)
            <div class="border border-2 border-dashed rounded-3 p-3 text-center text-secondary bg-white shadow-sm small">
                <i class="bi bi-keyboard d-block mb-1 opacity-50 fs-4" aria-hidden="true"></i>
                <p class="mb-2">トップの「マイページ」で<strong>名前を選んでからPIN</strong>を入力するか、共有端末では上の「切替」でスタッフを選んでください。</p>
                <p class="mb-0 text-muted" style="font-size: 0.75rem;">PINの確認が必要なときは「PIN」ボタンから入力できます。</p>
            </div>
        @else
            <p class="small text-secondary mb-2 pb-2 border-bottom lh-sm">
                <span class="text-dark fw-semibold">{{ $staff->name }}</span>
                <span class="text-muted">·</span> Lv.{{ $motivationLevel }}
                <span class="text-muted">·</span> 出勤: {{ $todayClockInLabel ?? '未打刻' }}
                <span class="text-muted">·</span> 営業日 <span class="font-monospace text-body">{{ $dateString }}</span>
            </p>

            <form method="post" action="{{ route('mypage.store') }}" id="mypage-routine-form" class="pb-4" style="padding-bottom: 5.5rem;">
                @csrf
                <input type="hidden" name="staff_id" value="{{ $staff->id }}">

                @php
                    $routineCardClass = $routineTasks->isEmpty()
                        ? 'border-secondary border-2 bg-light'
                        : ($routinesAllComplete
                            ? 'border-success border-2 bg-success-subtle'
                            : 'border-danger border-2 bg-danger-subtle');
                @endphp
                <section class="card shadow-sm rounded-3 mb-3 {{ $routineCardClass }}" aria-labelledby="routine-heading">
                    <div class="card-body p-3">
                        <h2 id="routine-heading" class="h6 fw-bold mb-2">
                            <i class="bi bi-check2-square me-1" aria-hidden="true"></i>ルーティン
                        </h2>

                        @if ($routineTasks->isEmpty())
                            <p class="text-secondary small mb-0">
                                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>割り当てはありません。
                            </p>
                        @elseif ($routinesAllComplete)
                            <p class="fw-bold text-success small mb-3 d-flex align-items-center gap-1">
                                <i class="bi bi-stars" aria-hidden="true"></i>
                                <span>全タスク完了</span>
                            </p>
                            <ul class="list-unstyled mb-0">
                                @foreach ($routineTasks as $task)
                                    <li class="mb-2 pb-2 border-bottom border-success border-opacity-25">
                                        <div class="form-check d-flex align-items-start gap-2">
                                            <input
                                                class="form-check-input fs-5 flex-shrink-0 mt-1"
                                                type="checkbox"
                                                name="routine_task[{{ $task->id }}]"
                                                value="1"
                                                id="routine-{{ $task->id }}"
                                                @checked(old('routine_task.'.$task->id, $routineLogIds->contains($task->id)))
                                            >
                                            <label class="form-check-label small" for="routine-{{ $task->id }}">
                                                <span class="d-block text-secondary" style="font-size: 0.7rem;">{{ $task->category }} · {{ $task->timing }}</span>
                                                <span class="fw-semibold">{{ $task->name }}</span>
                                            </label>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="fw-bold text-danger small mb-3 d-flex align-items-center gap-1">
                                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                                <span>未完了あり</span>
                            </p>
                            <ul class="list-unstyled mb-0">
                                @foreach ($routineTasks as $task)
                                    <li class="mb-2 pb-2 border-bottom border-danger border-opacity-25">
                                        <div class="form-check d-flex align-items-start gap-2">
                                            <input
                                                class="form-check-input fs-5 flex-shrink-0 mt-1"
                                                type="checkbox"
                                                name="routine_task[{{ $task->id }}]"
                                                value="1"
                                                id="routine-{{ $task->id }}"
                                                @checked(old('routine_task.'.$task->id, $routineLogIds->contains($task->id)))
                                            >
                                            <label class="form-check-label small" for="routine-{{ $task->id }}">
                                                <span class="d-block text-secondary" style="font-size: 0.7rem;">{{ $task->category }} · {{ $task->timing }}</span>
                                                <span class="fw-semibold">{{ $task->name }}</span>
                                            </label>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($routineTasks->isNotEmpty())
                            <div class="mt-3">
                                <label for="pin_code_routine" class="form-label small fw-semibold mb-1">保存 PIN（4桁）</label>
                                <input
                                    type="password"
                                    name="pin_code"
                                    id="pin_code_routine"
                                    inputmode="numeric"
                                    maxlength="4"
                                    required
                                    class="form-control form-control font-monospace text-center rounded-3 py-2"
                                    placeholder="••••"
                                    autocomplete="one-time-code"
                                >
                            </div>
                        @endif
                    </div>
                </section>

                <section class="mb-1" aria-labelledby="inventory-heading">
                    <h2 id="inventory-heading" class="h6 fw-bold mb-2 px-0">
                        <i class="bi bi-box-seam me-1" aria-hidden="true"></i>棚卸し
                    </h2>

                    @if (empty($inventoryTimingRows))
                        <div class="card border-secondary border-2 bg-light rounded-3 shadow-sm">
                            <div class="card-body p-3 text-center text-secondary small">
                                <i class="bi bi-inbox d-block mb-1 opacity-50 fs-4" aria-hidden="true"></i>
                                割り当てなし
                            </div>
                        </div>
                    @else
                        @foreach ($inventoryTimingRows as $row)
                            @if ($row['complete'])
                                <div class="card border-success border-2 bg-success-subtle rounded-3 shadow-sm mb-2">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-1 mb-2">
                                            <div>
                                                <span class="badge bg-success small rounded-pill">完了</span>
                                                <h3 class="small fw-bold mt-1 mb-0">{{ $row['label'] }}</h3>
                                                <p class="text-secondary small mb-0" style="font-size: 0.7rem;"><span class="font-monospace">{{ $row['timing_key'] }}</span></p>
                                            </div>
                                        </div>
                                        <a href="{{ $row['portal_url'] }}" class="btn btn-outline-success btn-sm w-100 py-2 rounded-3">
                                            確認・修正
                                        </a>
                                    </div>
                                </div>
                            @else
                                <div class="card border-danger border-2 bg-danger-subtle rounded-3 shadow-sm mb-2">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-1 mb-2">
                                            <div>
                                                <span class="badge bg-danger small rounded-pill">未実施</span>
                                                <h3 class="small fw-bold mt-1 mb-0">{{ $row['label'] }}</h3>
                                                <p class="text-secondary small mb-0" style="font-size: 0.7rem;">
                                                    <span class="font-monospace">{{ $row['timing_key'] }}</span>
                                                    · {{ $row['filled'] }}/{{ $row['total'] }}
                                                </p>
                                            </div>
                                        </div>
                                        <a href="{{ $row['portal_url'] }}" class="btn btn-danger btn-sm w-100 py-2 rounded-3">
                                            棚卸しへ
                                        </a>
                                        <a href="{{ route('inventory.index') }}" class="btn btn-link w-100 py-1 text-secondary" style="font-size: 0.75rem;">
                                            一覧
                                        </a>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    @endif
                </section>
            </form>

            @if ($routineTasks->isNotEmpty())
                <div class="position-fixed bottom-0 start-0 end-0 bg-white border-top shadow-sm py-2 px-2" style="z-index: 1020;">
                    <div class="mx-auto" style="max-width: 28rem;">
                        <button type="submit" form="mypage-routine-form" class="btn btn-dark w-100 py-2 rounded-3 fw-semibold">
                            <i class="bi bi-cloud-check-fill me-1" aria-hidden="true"></i>保存
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
