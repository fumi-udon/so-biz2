<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>業務ポータル — {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light d-flex flex-column min-vh-100">
    <x-client-nav />

    <main class="container py-3 py-md-5 flex-grow-1" style="max-width: 800px;">
        @if (session('error'))
            <div class="alert alert-danger shadow-sm rounded-4 py-2 small mb-3" role="alert">{{ session('error') }}</div>
        @endif
        <div class="mb-3 mb-md-4 text-center text-md-start">
            <h1 class="h3 fw-bold text-dark">業務メニュー</h1>
            <p class="text-secondary mb-0 small">担当の業務を選択してください。</p>
        </div>

        <div class="row row-cols-1 row-cols-md-2 g-3 g-md-4">
            <div class="col">
                <a href="{{ route('timecard.index') }}" class="text-decoration-none">
                    <div class="card h-100 shadow-sm border-0 bg-white hover-shadow transition">
                        <div class="card-body text-center p-4">
                            <div class="display-4 text-primary mb-3"><i class="bi bi-clock-history"></i></div>
                            <h2 class="h5 fw-bold text-dark mb-2">タイムカード</h2>
                            <p class="text-secondary small mb-0">出勤・退勤の打刻を行います。</p>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col">
                <button
                    type="button"
                    class="w-100 text-start border-0 bg-transparent p-0 rounded-3 hover-shadow transition"
                    data-bs-toggle="modal"
                    data-bs-target="#mypagePinModal"
                >
                    <div class="card h-100 shadow-sm border-0 bg-white">
                        <div class="card-body text-center p-3 p-md-4">
                            <div class="display-4 text-success mb-2 mb-md-3"><i class="bi bi-clipboard2-check"></i></div>
                            <h2 class="h5 fw-bold text-dark mb-1 mb-md-2">マイページ</h2>
                            <p class="text-secondary small mb-0">名前とPIN · タスク・棚卸し</p>
                        </div>
                    </div>
                </button>
            </div>

            <div class="col">
                <a href="{{ route('inventory.index') }}" class="text-decoration-none">
                    <div class="card h-100 shadow-sm border-0 bg-white hover-shadow transition">
                        <div class="card-body text-center p-4">
                            <div class="display-4 text-info mb-3"><i class="bi bi-box-seam"></i></div>
                            <h2 class="h5 fw-bold text-dark mb-2">棚卸し</h2>
                            <p class="text-secondary small mb-0">タイミング別の進捗を確認し、入力します。</p>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col">
                <a href="{{ route('close-check.index') }}" class="text-decoration-none">
                    <div class="card h-100 shadow-sm border-0 bg-white hover-shadow transition">
                        <div class="card-body text-center p-4">
                            <div class="display-4 text-danger mb-3"><i class="bi bi-lock-fill"></i></div>
                            <h2 class="h5 fw-bold text-dark mb-2">クローズチェック</h2>
                            <p class="text-secondary small mb-0">閉店前の最終確認とレジ締めを行います。</p>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col">
                <a href="{{ url('/admin') }}" class="text-decoration-none">
                    <div class="card h-100 shadow-sm border-0 bg-dark text-white hover-shadow transition">
                        <div class="card-body text-center p-4">
                            <div class="display-4 text-light mb-3"><i class="bi bi-gear-fill"></i></div>
                            <h2 class="h5 fw-bold text-white mb-2">本部管理 (Admin)</h2>
                            <p class="text-white-50 small mb-0">マスターデータの設定や各種記録を確認します。</p>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </main>

    <footer class="bg-white border-top py-4 mt-auto">
        <div class="container text-center small text-secondary">
            &copy; {{ date('Y') }} {{ config('app.name') }} System.
        </div>
    </footer>

    <style>
        .hover-shadow:hover {
            transform: translateY(-3px);
            box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
        }
        .transition {
            transition: all 0.2s ease-in-out;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
