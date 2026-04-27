<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>POS Receipt Preview</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @filamentStyles
</head>
<body class="min-h-screen bg-white text-gray-950 dark:bg-slate-950 dark:text-white">
    <livewire:pos.receipt-preview
        :shop-id="$shopId"
        :table-session-id="$tableSessionId"
        :intent="$intent"
        :expected-session-revision="$expectedRevision"
        :key="'pos-receipt-preview-tab-'.$shopId.'-'.$tableSessionId.'-'.$intent.'-'.$expectedRevision"
    />

    @livewireScripts
    @filamentScripts(withCore: true)

    {{-- スタンドアロンタブ専用: 戻るで Filament 管理画面へ落ちる事故を防ぎ POS へ誘導（モーダル埋め込みでは本レイアウトは使わない） --}}
    <script>
        (function () {
            const posUrl = @json($posMainEscapeUrl ?? '');

            if (!posUrl) {
                return;
            }

            history.pushState(null, null, window.location.href);

            window.addEventListener('popstate', function () {
                window.location.replace(posUrl);
            });
        })();
    </script>
</body>
</html>
