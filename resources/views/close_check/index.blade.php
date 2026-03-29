<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>クローズチェック — {{ config('app.name', 'Laravel') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* タブレット〜: 1行4タイル（2×2 で画面に「2列」が並ぶイメージ） */
        .close-check-grid {
            --close-thumb: 4.5rem;
        }
        @media (min-width: 768px) {
            .close-check-grid {
                --close-thumb: 3.75rem;
            }
        }
        .close-task-tile {
            position: relative;
            overflow: hidden;
            transition: background-color 0.4s ease, border-color 0.4s ease, box-shadow 0.4s ease, transform 0.25s ease;
            border-width: 2px !important;
        }
        .close-task-tile:hover:not(.is-checked) {
            transform: translateY(-1px);
            box-shadow: 0 0.35rem 1rem rgba(0, 0, 0, 0.08);
        }
        .close-task-tile.is-checked {
            background-color: rgba(25, 135, 84, 0.14) !important;
            border-color: var(--bs-success) !important;
            box-shadow: 0 0 0 1px rgba(25, 135, 84, 0.25), inset 0 0 2.5rem rgba(25, 135, 84, 0.06);
        }
        .close-task-thumb-wrap {
            width: var(--close-thumb);
            min-width: var(--close-thumb);
            height: var(--close-thumb);
            align-self: flex-start;
        }
        .close-task-thumb {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 0.5rem 0 0 0;
        }
        .close-task-tile .close-task-body {
            border-radius: 0 0.375rem 0 0;
        }
        .close-task-placeholder {
            width: 100%;
            height: 100%;
            min-height: var(--close-thumb);
            border-radius: 0.5rem 0 0 0;
        }
        .close-task-check-row .form-check-input:checked {
            background-color: var(--bs-success);
            border-color: var(--bs-success);
        }
        .close-task-tile.is-checked .check-done-icon {
            opacity: 1;
            transform: scale(1) rotate(0deg);
        }
        .check-done-icon {
            opacity: 0;
            transform: scale(0.5) rotate(-45deg);
            transition: opacity 0.35s ease, transform 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .close-sparkle-burst {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 4;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .close-sparkle {
            position: absolute;
            left: 50%;
            top: 42%;
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 50%;
            background: #fffbe6;
            box-shadow:
                0 0 6px #fff,
                0 0 12px #ffd54f,
                0 0 18px rgba(255, 215, 0, 0.8);
            animation: close-sparkle-fly 0.75s ease-out forwards;
            animation-delay: var(--spark-delay, 0s);
        }
        @keyframes close-sparkle-fly {
            0% {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
            100% {
                opacity: 0;
                transform: translate(
                        calc(-50% + var(--spark-dx)),
                        calc(-50% + var(--spark-dy))
                    )
                    scale(0.15);
            }
        }
        .close-task-tile .form-check-input:focus {
            box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
        }
        .close-task-tile.is-checked .close-task-check-row,
        .close-task-tile.is-checked .close-task-body {
            background-color: transparent !important;
        }
        @media (prefers-reduced-motion: reduce) {
            .close-task-tile,
            .check-done-icon {
                transition: none;
            }
            .close-sparkle {
                animation: none;
                opacity: 0;
            }
        }
    </style>
</head>
<body
    class="bg-light"
    id="close-check-body"
    data-close-blocked="{{ count($incompleteLines ?? []) > 0 ? '1' : '0' }}"
>
    <x-client-nav />

    <div class="container-fluid px-3 px-md-4 py-4 py-md-5 close-check-page" style="max-width: 1200px;">
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
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-3 close-check-grid">
                @foreach ($tasks as $task)
                    <div class="col">
                        <div
                            class="close-task-tile card h-100 shadow-sm border-secondary"
                            data-close-task-tile
                        >
                            <div class="d-flex flex-nowrap align-items-stretch">
                                @if ($task->image_path)
                                    <div class="close-task-thumb-wrap flex-shrink-0 bg-light">
                                        <img
                                            src="{{ asset('storage/'.$task->image_path) }}"
                                            alt=""
                                            class="close-task-thumb"
                                        >
                                    </div>
                                @else
                                    <div class="close-task-thumb-wrap flex-shrink-0">
                                        <div class="close-task-placeholder bg-light d-flex align-items-center justify-content-center">
                                            <i class="bi bi-image text-secondary opacity-50" style="font-size: 1.35rem;" aria-hidden="true"></i>
                                        </div>
                                    </div>
                                @endif
                                <div class="close-task-body flex-grow-1 min-w-0 p-2 ps-2 d-flex flex-column bg-white">
                                    <h2 class="h6 fw-semibold mb-1 lh-sm text-break">{{ $task->title }}</h2>
                                    @if ($task->description)
                                        <p class="text-secondary small mb-0 lh-sm" style="display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden;">{{ $task->description }}</p>
                                    @endif
                                </div>
                            </div>
                            <div class="close-task-check-row border-top px-2 py-2 bg-white">
                                <div class="form-check d-flex align-items-center gap-2 mb-0">
                                    <input
                                        class="form-check-input flex-shrink-0"
                                        type="checkbox"
                                        data-task-check
                                        id="task-check-{{ $task->id }}"
                                        style="width: 1.5rem; height: 1.5rem; margin-top: 0;"
                                        aria-label="{{ $task->title }} を確認した"
                                    >
                                    <i class="bi bi-check-circle-fill text-success check-done-icon flex-shrink-0" aria-hidden="true"></i>
                                    <label class="form-check-label fw-medium user-select-none small mb-0 flex-grow-1" for="task-check-{{ $task->id }}">
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

                function spawnSparkles(tile) {
                    if (!tile) return;
                    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                        return;
                    }
                    var burst = document.createElement('div');
                    burst.className = 'close-sparkle-burst';
                    burst.setAttribute('aria-hidden', 'true');
                    var n = 14;
                    for (var i = 0; i < n; i++) {
                        var s = document.createElement('span');
                        s.className = 'close-sparkle';
                        var angle = (i / n) * Math.PI * 2 + Math.random() * 0.4;
                        var dist = 28 + Math.random() * 42;
                        s.style.setProperty('--spark-dx', Math.cos(angle) * dist + 'px');
                        s.style.setProperty('--spark-dy', Math.sin(angle) * dist + 'px');
                        s.style.setProperty('--spark-delay', (i * 0.02) + 's');
                        burst.appendChild(s);
                    }
                    tile.appendChild(burst);
                    window.setTimeout(function () {
                        if (burst.parentNode) burst.parentNode.removeChild(burst);
                    }, 900);
                }

                function syncTileState(checkbox) {
                    var tile = checkbox.closest('[data-close-task-tile]');
                    if (!tile) return;
                    if (checkbox.checked) {
                        tile.classList.add('is-checked');
                    } else {
                        tile.classList.remove('is-checked');
                    }
                }

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
                    el.addEventListener('change', function () {
                        if (el.checked) {
                            spawnSparkles(el.closest('[data-close-task-tile]'));
                        }
                        syncTileState(el);
                        updateConfirmButton();
                    });
                    syncTileState(el);
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
