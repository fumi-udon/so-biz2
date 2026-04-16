<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <title>{{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    {{--
        Fallback :root tokens (Bistro Nippon defaults).
        MenuPage overrides these via @push('guest-theme').
        html.overflow-hidden is toggled by Alpine when the bottom sheet is open.
    --}}
    <style>
        :root {
            --go-primary: #1e3a8a;
            --go-on-primary: #ffffff;
            --go-accent: #3b82f6;
            --go-danger: #dc2626;
            --go-surface: #f8fafc;
            --go-cart-bg: #0f172a;
            --go-radius-button: 0.75rem;
            --go-radius-card: 1rem;
            --go-font: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
        }
        html.overflow-hidden,
        html.overflow-hidden body {
            overflow: hidden;
        }
    </style>

    @stack('guest-theme')
    @stack('guest-font')
</head>
<body
    class="min-h-screen text-slate-900 antialiased"
    style="background-color: var(--go-surface); font-family: var(--go-font);"
>
    {{ $slot }}

    @livewireScripts
</body>
</html>
