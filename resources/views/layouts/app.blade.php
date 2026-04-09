<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>
        @hasSection('title')
            @yield('title')
        @else
            {{ $layoutTitle ?? config('app.name') }}
        @endif
    </title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
    @livewireStyles
</head>
<body class="min-h-full bg-gray-50 text-gray-900 antialiased dark:bg-gray-900 dark:text-gray-100">
    @isset($slot)
        {{ $slot }}
    @else
        @yield('content')
    @endisset
    @stack('scripts')
    @livewireScripts
</body>
</html>
