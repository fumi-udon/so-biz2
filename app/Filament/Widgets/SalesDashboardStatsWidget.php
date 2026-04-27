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
        return 2;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $shopId = $this->resolveCurrentShopId();
        if ($shopId < 1) {
            return [
                Stat::make('今月売上', '0 DT')->color('gray'),
                Stat::make('先月売上', '0 DT')->color('gray'),
            ];
        }

        $tz = (string) config('app.timezone', 'UTC');
        $nowLocal = Carbon::now($tz);
        $currentBusinessStart = $this->currentBusinessStart($nowLocal);
        $thisMonthStartLocal = $currentBusinessStart->copy()->startOfMonth()->setTime(4, 0, 0);
        $lastMonthStartLocal = $thisMonthStartLocal->copy()->subMonthNoOverflow()->startOfMonth()->setTime(4, 0, 0);
        $thisMonthEndLocal = $thisMonthStartLocal->copy()->addMonthNoOverflow();

        return [
            Stat::make('今月売上', $this->formatMinor($this->sumMinor(
                $shopId,
                $thisMonthStartLocal->copy()->utc(),
                $nowLocal->copy()->utc()
            )))
                ->description('当月1日 04:00〜現在')
                ->icon('heroicon-o-calendar-days')
                ->color('success'),
            Stat::make('先月売上', $this->formatMinor($this->sumMinor(
                $shopId,
                $lastMonthStartLocal->copy()->utc(),
                $thisMonthEndLocal->copy()->utc()
            )))
                ->description('先月1日 04:00〜当月1日 03:59')
                ->icon('heroicon-o-clock')
                ->color('warning'),
        ];
    }

    private function sumMinor(int $shopId, Carbon $fromUtc, Carbon $toUtc): int
    {
        return (int) TableSessionSettlement::query()
            ->where('shop_id', $shopId)
            ->whereBetween('settled_at', [$fromUtc, $toUtc])
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
