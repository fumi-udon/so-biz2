<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>完了 — {{ config('app.name', 'Laravel') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light min-vh-100 d-flex flex-column">
    <x-client-nav />

    <div class="flex-grow-1 d-flex flex-column align-items-center justify-content-center px-3 py-5">
    <div class="text-center" style="max-width: 36rem;">
        <p class="display-4 fw-bold text-success mb-3">今日1日お疲れ様でした！ブラボー！</p>
        <p class="display-4 fw-bold text-dark">{{ $closedStaffName }}</p>
    </div>

    <div class="mt-5">
        <a
            href="{{ route('home') }}"
            class="btn btn-outline-secondary rounded-circle d-inline-flex align-items-center justify-content-center text-decoration-none"
            style="width: 4rem; height: 4rem; font-size: 1.75rem; line-height: 1;"
            title="トップへ戻る"
            aria-label="閉じてトップへ戻る"
        >✕</a>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
