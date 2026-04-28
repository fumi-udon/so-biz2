<?php

namespace App\Filament\Widgets;

use App\Models\Shop;
use App\Models\TableSessionSettlement;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class SalesDashboardStatsWidget extends StatsOverviewWidget
{
    protected ?string $heading = '売上サマリー';

    protected function getColumns(): int
    {
        return 3;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $shopId = $this->resolveCurrentShopId();
        if ($shopId < 1) {
            return [
                Stat::make('月累計', '0 DT')->description('1日04:00〜')->color('gray'),
                Stat::make('週累計', '0 DT')->description('月曜04:00〜')->color('gray'),
                Stat::make('90日平均', '0 DT')->description('直近90営業日')->color('gray'),
            ];
        }

        $tz = (string) config('app.timezone', 'UTC');
        $nowLocal = Carbon::now($tz);
        $currentBusinessStart = $this->currentBusinessStart($nowLocal);
        $thisMonthStartLocal = $currentBusinessStart->copy()->startOfMonth()->setTime(4, 0, 0);
        $thisWeekStartLocal = $currentBusinessStart->copy()->startOfWeek(Carbon::MONDAY)->setTime(4, 0, 0);
        $window90StartLocal = $currentBusinessStart->copy()->subDays(89)->setTime(4, 0, 0);

        $toLocal = $nowLocal;
        $monthlyMinor = $this->sumMinor($shopId, $thisMonthStartLocal, $toLocal);
        $weeklyMinor = $this->sumMinor($shopId, $thisWeekStartLocal, $toLocal);
        $window90Minor = $this->sumMinor($shopId, $window90StartLocal, $toLocal);
        $average90Minor = (int) floor($window90Minor / 90);

        return [
            Stat::make('月累計', $this->formatMinor($monthlyMinor))
                ->description('1日04:00〜現在')
                ->icon('heroicon-o-calendar-days')
                ->color('success'),
            Stat::make('週累計', $this->formatMinor($weeklyMinor))
                ->description('月曜04:00〜現在')
                ->icon('heroicon-o-calendar')
                ->color('info'),
            Stat::make('90日平均', $this->formatMinor($average90Minor))
                ->description('直近90営業日')
                ->icon('heroicon-o-chart-bar')
                ->color('warning'),
        ];
    }

    private function sumMinor(int $shopId, Carbon $fromLocal, Carbon $toLocal): int
    {
        return (int) TableSessionSettlement::query()
            ->where('shop_id', $shopId)
            ->whereBetween('settled_at', [$fromLocal, $toLocal])
            ->sum('final_total_minor');
    }

    private function currentBusinessStart(Carbon $nowLocal): Carbon
    {
        $businessStart = $nowLocal->copy()->setTime(4, 0, 0);
        if ($nowLocal->lt($businessStart)) {
            $businessStart->subDay();
        }

        return $businessStart;
    }

    private function resolveCurrentShopId(): int
    {
        $preferredShopId = (int) config('pos.default_shop_id', 3);
        $shop = null;

        if ($preferredShopId > 0) {
            $shop = Shop::query()
                ->whereKey($preferredShopId)
                ->where('is_active', true)
                ->first();
        }

        if ($shop === null) {
            $shop = Shop::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->first();
        }

        return (int) ($shop?->id ?? 0);
    }

    private function formatMinor(int $minor): string
    {
        $formatted = number_format($minor / 1000, 1, '.', ' ');
        $formatted = str_ends_with($formatted, '.0')
            ? substr($formatted, 0, -2)
            : $formatted;

        return $formatted.' DT';
    }
}
