<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>POS V2 — @hasSection('bridge-title') @yield('bridge-title') @else Bridge @endif</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @filamentStyles
</head>
<body class="min-h-screen bg-white text-gray-950 dark:bg-slate-950 dark:text-white">
    @yield('bridge-body')

    @livewireScripts
    @filamentScripts(withCore: true)

    {{-- 戻る誤操作で Filament に落ちるのを防ぎ POS V2 へ誘導（receipt-preview-page と同様） --}}
    <script>
        (function () {
            const escapeUrl = @json($escapeUrl ?? route('pos2.index'));
            if (escapeUrl) {
                history.pushState(null, '', window.location.href);
                window.addEventListener('popstate', function () {
                    window.location.replace(escapeUrl);
                });
            }
        })();
    </script>
    @stack('bridge-scripts')
</body>
</html>
