<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Clôture caisse — {{ config('app.name', 'Laravel') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
    @livewireStyles
</head>
<body class="min-h-full bg-gray-50 text-gray-900 antialiased transition-colors duration-300 dark:bg-gray-900 dark:text-gray-100">
    <header class="border-b border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-y-2 px-3 py-3 sm:px-4">
            <div class="flex min-w-0 flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-3">
                <a href="{{ route('home') }}" class="truncate text-sm font-semibold text-gray-800 dark:text-gray-100">{{ config('app.name', 'SOYA') }}</a>
                <span class="font-['Press_Start_2P'] text-[8px] leading-tight tracking-tight text-indigo-600 dark:text-indigo-400 sm:text-[9px]" title="Mode arcade">CLOSE</span>
            </div>
            <nav class="flex flex-wrap items-center gap-2 text-sm">
                <a href="{{ route('home') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Accueil</a>
                <a href="{{ route('timecard.index') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Pointage</a>
            </nav>
        </div>
    </header>
    <main class="mx-auto w-full max-w-6xl px-3 py-4 sm:px-4">
        {{ $slot }}
    </main>
    <footer class="py-3 text-center text-xs text-gray-500 dark:text-gray-400">
        {{ config('app.name') }}
    </footer>
    @livewireScripts
</body>
</html>
