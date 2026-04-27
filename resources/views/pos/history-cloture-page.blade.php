<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>History Cloture</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @filamentStyles
</head>
<body class="min-h-screen bg-white text-gray-950 dark:bg-gray-950 dark:text-white">
    <livewire:pos.cloture-history-page />

    @livewireScripts
    @filamentScripts(withCore: true)
</body>
</html>
