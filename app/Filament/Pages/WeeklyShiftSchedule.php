<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminOnlyPage;
use App\Services\WeeklyShiftGridService;

class WeeklyShiftSchedule extends AdminOnlyPage
{
    protected static function piloteCanAccessThisPage(): bool
    {
        return true;
    }

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = '店舗・勤怠管理';

    protected static ?string $title = '週間シフト表 (Horaires Hebdomadaires)';

    protected static ?string $navigationLabel = '週間シフト表';

    protected static string $view = 'filament.pages.weekly-shift-schedule';

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
