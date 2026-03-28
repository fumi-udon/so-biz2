<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>タイムカード — {{ config('app.name', 'Laravel') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body class="bg-dark text-light min-vh-100">
    <div class="container py-5 mx-auto" style="max-width: 500px;">
        <header class="text-center mb-4">
            <p class="text-info small text-uppercase mb-1 tracking-wide">タイムカード</p>
            <h1 class="h3 fw-semibold mb-3">打刻</h1>
            <p class="text-secondary small mb-1">
                営業日
                <time class="text-light font-monospace" datetime="{{ $targetBusinessDate->toDateString() }}">
                    {{ $targetBusinessDate->format('Y/m/d') }}
                </time>
            </p>
            <p class="text-secondary mb-0" style="font-size: 0.7rem;">6時前の打刻は前営業日として記録されます（夜勤など）。</p>
        </header>

        @if ($staff->isEmpty())
            @if (session('error') || $errors->any())
                <div class="mb-3">
                    @if (session('error'))
                        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert">
                            <ul class="mb-0 ps-3 small">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif
            <div class="border border-secondary border-2 border-dashed rounded-3 p-5 text-center text-secondary">
                アクティブなスタッフが登録されていません。
            </div>
        @else
            <form action="{{ route('timecard.process') }}" method="post">
                @csrf

                <label for="staff_id" class="form-label small text-secondary">スタッフ</label>
                <select
                    id="staff_id"
                    name="staff_id"
                    required
                    class="form-select form-select-lg mb-3"
                >
                    <option value="" disabled {{ old('staff_id') ? '' : 'selected' }}>選択してください</option>
                    @foreach ($staff as $member)
                        <option value="{{ $member->id }}" @selected((string) old('staff_id') === (string) $member->id)>
                            {{ $member->name }}
                        </option>
                    @endforeach
                </select>

                <label for="pin_code" class="form-label small text-secondary">PIN（4桁）</label>
                <input
                    id="pin_code"
                    name="pin_code"
                    type="password"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="4"
                    pattern="[0-9]{4}"
                    required
                    placeholder="••••"
                    class="form-control form-control-lg text-center font-monospace mb-4"
                />

                @if (session('error') || $errors->any())
                    <div class="mb-4">
                        @if (session('error'))
                            <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
                        @endif
                        @if ($errors->any())
                            <div class="alert alert-danger" role="alert">
                                <ul class="mb-0 ps-3 small">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endif

                <p class="text-center text-secondary small text-uppercase mb-2">ランチ</p>
                <div class="row g-3 mb-4">
                    <div class="col-12 col-sm-6">
                        <button type="submit" name="action" value="lunch_in" class="btn btn-success btn-lg w-100 py-3">
                            ランチ出勤
                        </button>
                    </div>
                    <div class="col-12 col-sm-6">
                        <button type="submit" name="action" value="lunch_out" class="btn btn-outline-success btn-lg w-100 py-3">
                            ランチ退勤
                        </button>
                    </div>
                </div>

                <hr class="border-secondary my-4">

                <p class="text-center text-secondary small text-uppercase mb-2">ディナー</p>
                <div class="row g-3">
                    <div class="col-12 col-sm-6">
                        <button type="submit" name="action" value="dinner_in" class="btn btn-warning btn-lg w-100 py-3">
                            ディナー出勤
                        </button>
                    </div>
                    <div class="col-12 col-sm-6">
                        <button type="submit" name="action" value="dinner_out" class="btn btn-outline-warning btn-lg w-100 py-3">
                            ディナー退勤
                        </button>
                    </div>
                </div>
            </form>
        @endif

        <footer class="mt-5 pt-4 text-center text-secondary small">
            {{ config('app.name') }}
        </footer>
    </div>

    @if (session('success_modal') || session('late_modal'))
        <div
            class="modal fade"
            id="timecardResultModal"
            tabindex="-1"
            aria-labelledby="timecardResultModalLabel"
            aria-hidden="true"
        >
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h2 class="modal-title fs-5" id="timecardResultModalLabel">
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
                            <p class="text-secondary small mb-0">打刻を正常に保存しました。</p>
                        @endif
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-primary w-100" data-bs-dismiss="modal">OK</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    @if (session('success_modal') || session('late_modal'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var el = document.getElementById('timecardResultModal');
                if (el && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getOrCreateInstance(el).show();
                }
            });
        </script>
    @endif
</body>
</html>
