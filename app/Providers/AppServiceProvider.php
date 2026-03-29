<?php

namespace App\Providers;

use App\Models\Staff;
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
