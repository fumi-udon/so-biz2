<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminOnlyPage;
use App\Services\WeeklyShiftGridService;
use Illuminate\Contracts\Support\Htmlable;

class WeeklyShiftSchedule extends AdminOnlyPage
{
    protected static function piloteCanAccessThisPage(): bool
    {
        return true;
    }

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static string $view = 'filament.pages.weekly-shift-schedule';

    public static function getNavigationGroup(): ?string
    {
        return __('hq.nav_group_store', [], 'fr');
    }

    public static function getNavigationLabel(): string
    {
        return __('hq.nav_weekly_planning', [], 'fr');
    }

    public function getTitle(): string|Htmlable
    {
        return __('hq.page_weekly_shift_title', [], 'fr');
    }

    protected function getViewData(): array
    {
        return app(WeeklyShiftGridService::class)->build();
    }

    /**
     * Blade 用: ステータス → 絵文字インジケーター（後方互換・実体は {@see WeeklyShiftGridService::liveStatusIcon}）。
     */
    public static function liveStatusIcon(string $status): string
    {
        return WeeklyShiftGridService::liveStatusIcon($status);
    }
}
