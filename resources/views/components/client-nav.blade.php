@props([
    'variant' => 'light',
])

@php
    $isDark = $variant === 'dark';
    $navClasses = $isDark
        ? 'navbar-dark bg-dark border-bottom border-secondary'
        : 'navbar-light bg-white border-bottom shadow-sm';
@endphp

<nav class="navbar navbar-expand-lg {{ $navClasses }} py-0">
    <div class="container-fluid px-2 px-sm-3" style="max-width: 1100px;">
        <a class="navbar-brand py-1 small fw-semibold" href="{{ route('home') }}">
            <i class="bi bi-house-door-fill me-1" aria-hidden="true"></i>{{ config('app.name', 'Menu') }}
        </a>
        <button
            class="navbar-toggler py-1 px-2"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#clientMainNav"
            aria-controls="clientMainNav"
            aria-expanded="false"
            aria-label="メニューを開く"
        >
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="clientMainNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link py-1 small" href="{{ route('home') }}">トップ</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-1 small" href="{{ route('timecard.index') }}">タイムカード</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-1 small" href="{{ route('mypage.index') }}">マイページ</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-1 small" href="{{ route('inventory.index') }}">棚卸し</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-1 small" href="{{ route('close-check.index') }}">クローズ</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
