<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>完了 — {{ config('app.name', 'Laravel') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important;}</style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <x-client-nav />

    <div class="mx-auto flex min-h-[70vh] w-full max-w-3xl flex-col items-center justify-center px-4 py-8">
        <div class="w-full rounded-2xl border-2 border-black bg-white p-6 text-center shadow-[0_8px_0_0_rgba(0,0,0,1)]">
            <p class="mb-3 text-3xl font-black text-emerald-600">今日1日お疲れ様でした！ブラボー！</p>
            <p class="text-4xl font-black text-slate-900">{{ $closedStaffName }}</p>
            <a
                href="{{ route('home') }}"
                class="mt-6 inline-flex items-center justify-center rounded-full border-2 border-black bg-slate-100 px-5 py-2 text-base font-bold text-slate-700 hover:bg-slate-200"
                title="トップへ戻る"
                aria-label="閉じてトップへ戻る"
            >トップへ戻る</a>
        </div>
    </div>
</body>
</html>
