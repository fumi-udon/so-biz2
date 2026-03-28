<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>クローズチェック — {{ config('app.name', 'Laravel') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body
    class="bg-light"
    id="close-check-body"
    data-close-blocked="{{ count($incompleteLines ?? []) > 0 ? '1' : '0' }}"
>
    <x-client-nav />

    <div class="container py-4 py-md-5">
        <header class="mb-4">
            <h1 class="h2 fw-semibold">クローズチェック</h1>
            <p class="text-secondary small mb-0">閉店前の最終確認です。すべての項目を確認してから責任者承認へ進んでください。</p>
        </header>

        @if (! empty($incompleteLines))
            <div class="alert alert-danger" role="alert">
                <p class="fw-bold mb-2">以下のタスク・棚卸しが未完了です（全員分）</p>
                <ul class="mb-2 small">
                    @foreach ($incompleteLines as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
                <p class="small mb-0">マイページで完了させるまで、閉店の最終承認はできません。</p>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger" role="alert">
                {{ session('error') }}
            </div>
        @endif

        @if ($tasks->isEmpty())
            <p class="border border-2 border-dashed rounded p-5 text-center text-secondary bg-white">有効なタスクが登録されていません。</p>
        @else
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                @foreach ($tasks as $task)
                    <div class="col">
                        <div class="card h-100 shadow-sm">
                            @if ($task->image_path)
                                <img
                                    src="{{ asset('storage/'.$task->image_path) }}"
                                    alt=""
                                    class="card-img-top"
                                    style="height: 200px; object-fit: cover;"
                                >
                            @else
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                    <i class="bi bi-image display-4 text-secondary" aria-hidden="true"></i>
                                </div>
                            @endif
                            <div class="card-body">
                                <h2 class="card-title h5">{{ $task->title }}</h2>
                                @if ($task->description)
                                    <p class="card-text text-secondary small mb-0">{{ $task->description }}</p>
                                @endif
                            </div>
                            <div class="card-footer bg-white border-top-0 pt-0">
                                <div class="form-check d-flex align-items-center gap-2 py-2">
                                    <input
                                        class="form-check-input flex-shrink-0"
                                        type="checkbox"
                                        data-task-check
                                        id="task-check-{{ $task->id }}"
                                        style="width: 1.75rem; height: 1.75rem; margin-top: 0;"
                                        aria-label="{{ $task->title }} を確認した"
                                    >
                                    <label class="form-check-label fw-medium user-select-none" for="task-check-{{ $task->id }}">
                                        確認済み
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 mt-md-5">
                <button
                    type="button"
                    id="open-confirm-btn"
                    class="btn btn-dark btn-lg w-100"
                    disabled
                >
                    確認（Confirm）
                </button>
            </div>

            <div class="modal fade" id="closeApprovalModal" tabindex="-1" aria-labelledby="closeApprovalModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="closeApprovalModalLabel">最終承認</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                        </div>
                        <form action="{{ route('close-check.process') }}" method="post">
                            @csrf
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="staff_id" class="form-label">本日の責任者</label>
                                    <select name="staff_id" id="staff_id" class="form-select" required>
                                        <option value="">選択してください</option>
                                        @foreach ($staffList as $staff)
                                            <option value="{{ $staff->id }}" @selected(old('staff_id') == $staff->id)>{{ $staff->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-0">
                                    <label for="pin_code" class="form-label">PIN（4桁）</label>
                                    <input
                                        type="password"
                                        name="pin_code"
                                        id="pin_code"
                                        class="form-control font-monospace"
                                        inputmode="numeric"
                                        maxlength="4"
                                        pattern="[0-9]{4}"
                                        autocomplete="one-time-code"
                                        required
                                        placeholder="••••"
                                    >
                                </div>
                            </div>
                            <div class="modal-footer flex-column flex-sm-row gap-2">
                                <button type="button" class="btn btn-outline-secondary order-2 order-sm-1 w-100 w-sm-auto" data-bs-dismiss="modal">戻る</button>
                                <button type="submit" class="btn btn-primary order-1 order-sm-2 w-100 w-sm-auto">承認して記録する</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    @if (! $tasks->isEmpty())
        <script>
            (function () {
                var body = document.getElementById('close-check-body');
                var blocked = body && body.getAttribute('data-close-blocked') === '1';
                var checks = document.querySelectorAll('[data-task-check]');
                var openBtn = document.getElementById('open-confirm-btn');
                var modalEl = document.getElementById('closeApprovalModal');
                var modal = modalEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(modalEl) : null;

                function allChecked() {
                    if (!checks.length) return false;
                    for (var i = 0; i < checks.length; i++) {
                        if (!checks[i].checked) return false;
                    }
                    return true;
                }

                function updateConfirmButton() {
                    if (!openBtn) return;
                    if (blocked) {
                        openBtn.disabled = true;
                        return;
                    }
                    openBtn.disabled = !allChecked();
                }

                checks.forEach(function (el) {
                    el.addEventListener('change', updateConfirmButton);
                });
                updateConfirmButton();

                if (openBtn && modal) {
                    openBtn.addEventListener('click', function () {
                        if (blocked || !allChecked()) return;
                        var pin = document.getElementById('pin_code');
                        modal.show();
                        if (pin) {
                            setTimeout(function () { pin.focus(); }, 300);
                        }
                    });
                }

                if (modalEl) {
                    modalEl.addEventListener('hidden.bs.modal', function () {
                        var pin = document.getElementById('pin_code');
                        if (pin) pin.value = '';
                    });
                }
            })();
        </script>
    @endif
</body>
</html>
