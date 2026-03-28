<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-md navbar-dark bg-dark border-bottom">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="{{ route('home') }}">{{ config('app.name', 'Laravel') }}</a>
            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#welcomeNav"
                aria-controls="welcomeNav"
                aria-expanded="false"
                aria-label="メニューを開く"
            >
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="welcomeNav">
                <ul class="navbar-nav ms-auto gap-md-2">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="{{ route('home') }}">トップ</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('timecard.index') }}">タイムカード</a>
                    </li>
                    @if (Route::has('login'))
                        @auth
                            <li class="nav-item">
                                <a class="nav-link" href="{{ url('/dashboard') }}">ダッシュボード</a>
                            </li>
                        @else
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('login') }}">ログイン</a>
                            </li>
                        @endauth
                    @endif
                </ul>
            </div>
        </div>
    </nav>

    <main class="container py-4 py-md-5">
        <div class="mb-4">
            <h1 class="h3 fw-bold text-dark">業務メニュー</h1>
            <p class="text-secondary mb-0 small">一覧から機能を開いてください。</p>
        </div>

        <div class="card shadow-sm border">
            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th scope="col" class="text-nowrap">メニュー</th>
                            <th scope="col">説明</th>
                            <th scope="col" class="text-end text-nowrap" style="width: 7rem;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-semibold text-dark">タイムカード</td>
                            <td class="text-secondary small">PIN でランチ・ディナーの出退を記録します（スタッフ向け）。</td>
                            <td class="text-end">
                                <a class="btn btn-primary btn-sm" href="{{ route('timecard.index') }}">開く</a>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-dark">（予定）ダッシュボード</td>
                            <td class="text-secondary small">集計などの画面を今後ここに追加します。</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm" disabled>準備中</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-dark">（予定）その他</td>
                            <td class="text-secondary small">追加機能用の行です。</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm" disabled>準備中</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <footer class="border-top bg-white mt-auto py-4">
        <div class="container text-center small text-secondary">
            {{ config('app.name') }}
        </div>
    </footer>

    <a
        href="{{ url('/admin') }}"
        class="position-fixed bottom-0 end-0 p-3 small text-secondary text-decoration-none opacity-50"
        style="font-size: 0.65rem; z-index: 1050;"
        title="管理者向け"
    >
        管理
    </a>

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"
    ></script>
</body>
</html>
