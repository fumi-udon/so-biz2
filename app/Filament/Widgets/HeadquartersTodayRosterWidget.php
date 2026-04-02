<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Attendances\Widgets\TodayAttendanceRosterWidget;

/**
 * 本部ダッシュボード専用: 本日の出勤簿（30s ポーリング・モバイル最適化ビュー）。
 */
class HeadquartersTodayRosterWidget extends TodayAttendanceRosterWidget
{
    protected static string $view = 'filament.widgets.headquarters-today-roster';

    protected static ?int $sort = -9;

    public static function canView(): bool
    {
        $u = auth()->user();

        return $u?->isAdmin() === true || $u?->isCashier() === true;
    }
}
