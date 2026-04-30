<!DOCTYPE html>
<html lang="ja" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="kds-shop-id" content="{{ session('kds.active_shop_id', 0) }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>KDS V2</title>
    @vite(['resources/css/app.css', 'resources/js/kds2/main.js'])
</head>
<body class="min-h-full bg-gray-950 text-gray-100 antialiased">
    <div id="kds2-app"></div>
</body>
</html>
