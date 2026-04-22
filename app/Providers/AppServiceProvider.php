<?php

namespace App\Providers;

use App\Models\DailyTip;
use App\Models\DailyTipDistribution;
use App\Models\OrderLine;
use App\Models\Staff;
use App\Models\StaffTip;
use App\Models\User;
use App\Observers\DailyTipDistributionObserver;
use App\Observers\DailyTipObserver;
use App\Observers\OrderLineObserver;
use Filament\Notifications\Livewire\Notifications;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Owner / super_admin: Filament Shield の個別 permission や define_via_gate 設定に依存せず
        // 全 Gate チェックを通す（Role ナビ、メニュー商品 Create 等）。
        Gate::before(function ($user, string $ability) {
            if (! $user instanceof User) {
                return null;
            }

            return $user->hasFullFilamentAccess() ? true : null;
        });

        // キオスク等: 右上トーストがエスケープ等を隠さないよう、重要通知は画面中央に集約
        Notifications::alignment(Alignment::Center);
        Notifications::verticalAlignment(VerticalAlignment::Center);

        DailyTip::observe(DailyTipObserver::class);
        DailyTipDistribution::observe(DailyTipDistributionObserver::class);
        StaffTip::observe(DailyTipDistributionObserver::class);
        OrderLine::observe(OrderLineObserver::class);

        View::composer('components.mypage-entry-modal', function ($view): void {
            $view->with(
                'mypageStaffList',
                Staff::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name']),
            );
        });
    }
}
