<?php

namespace App\Providers;

use App\Models\DailyTip;
use App\Models\DailyTipDistribution;
use App\Models\Staff;
use App\Observers\DailyTipDistributionObserver;
use App\Observers\DailyTipObserver;
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
        DailyTip::observe(DailyTipObserver::class);
        DailyTipDistribution::observe(DailyTipDistributionObserver::class);

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
