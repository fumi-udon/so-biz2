<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use App\Filament\Resources\Attendances\Widgets\TodayAttendanceWidget;
use Illuminate\Support\Facades\Auth;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // Filament v3 では Panel に ->echo() は無く、レイアウトは config('filament.broadcasting.echo') を参照する。
        // 以下はご指定の Pusher（Echo）設定と同等（認可付き通知用に auth 等は従来どおり維持）。
        config([
            'filament.broadcasting.echo' => [
                'broadcaster' => 'pusher',
                'key' => env('VITE_PUSHER_APP_KEY'),
                'cluster' => env('VITE_PUSHER_APP_CLUSTER'),
                'wsHost' => env('VITE_PUSHER_HOST', 'ws-' . env('VITE_PUSHER_APP_CLUSTER') . '.pusher.com'),
                'wsPort' => env('VITE_PUSHER_PORT', 443),
                'wssPort' => env('VITE_PUSHER_PORT', 443),
                'forceTLS' => true,
                'authEndpoint' => '/broadcasting/auth',
                'disableStats' => true,
                'encrypted' => true,
            ],
        ]);

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->homeUrl(function (): string {
                if (Auth::user()?->isCashier()) {
                    return route('filament.admin.pages.daily-close-check');
                }

                return url('/admin');
            })
            // エクスポート完了時の「ダウンロード」リンクは DB 通知（ベルアイコン）に表示される
            ->databaseNotifications()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                TodayAttendanceWidget::class,
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                function (): string {
                    if (! request()->routeIs('filament.admin.resources.attendances.index')) {
                        return '';
                    }

                    return '<link rel="stylesheet" href="'.asset('css/filament-attendances-layout.css').'" />';
                },
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                function (): string {
                    if (! request()->routeIs('filament.admin.pages.daily-close-check')) {
                        return '';
                    }

                    return Blade::render('@vite([\'resources/css/app.css\'])');
                },
            );
    }
}
