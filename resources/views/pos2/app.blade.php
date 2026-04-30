<!DOCTYPE html>
<html lang="ja" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/pos2/main.js'])
    @inertiaHead
</head>
<body class="h-full bg-slate-950 text-slate-100 antialiased">
    @inertia
</body>
</html>
