<?php

namespace App\Providers;

use App\Models\DailyTip;
use App\Models\DailyTipDistribution;
use App\Models\OrderLine;
use App\Models\Staff;
use App\Models\StaffTip;
use App\Observers\DailyTipDistributionObserver;
use App\Observers\DailyTipObserver;
use App\Observers\OrderLineObserver;
use Filament\Notifications\Livewire\Notifications;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;
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
