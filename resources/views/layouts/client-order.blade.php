<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('オンライン注文') }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased [font-family:system-ui,-apple-system,'Segoe UI',sans-serif]">
    <div class="mx-auto max-w-lg px-4 py-10">
        {{ $slot }}
    </div>
    @livewireScripts
</body>
</html>
