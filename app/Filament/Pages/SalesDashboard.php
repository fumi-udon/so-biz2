<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminOnlyPage;
use App\Filament\Widgets\SalesDashboardStatsWidget;
use App\Models\Shop;
use App\Models\TableSessionSettlement;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SalesDashboard extends AdminOnlyPage implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Sales（売上報告）';

    protected static ?string $title = 'Sales（売上報告）';

    protected static ?string $navigationGroup = 'Menu & orders';

    protected static ?int $navigationSort = 17;

    protected static string $view = 'filament.pages.sales-dashboard';

    public int $shopId = 0;

    public function mount(): void
    {
        $this->shopId = $this->resolveCurrentShopId();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SalesDashboardStatsWidget::class,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->dailyAggregateQuery())
            ->defaultSort('business_date', 'desc')
            ->paginated([30, 60, 90])
            ->defaultPaginationPageOption(30)
            ->columns([
                TextColumn::make('business_date')
                    ->label('営業日')
                    ->date('Y-m-d'),
                TextColumn::make('lunch_total_minor')
                    ->label('ランチ合計')
                    ->alignEnd()
                    ->formatStateUsing(fn (mixed $state): string => $this->formatMinor((int) $state)),
                TextColumn::make('dinner_total_minor')
                    ->label('ディナー合計')
                    ->alignEnd()
                    ->formatStateUsing(fn (mixed $state): string => $this->formatMinor((int) $state)),
                TextColumn::make('day_total_minor')
                    ->label('一日合計')
                    ->alignEnd()
                    ->weight('bold')
                    ->formatStateUsing(fn (mixed $state): string => $this->formatMinor((int) $state)),
            ]);
    }

    private function dailyAggregateQuery(): Builder
    {
        $tz = (string) config('app.timezone', 'UTC');
        $nowLocal = Carbon::now($tz);
        $currentBusinessStart = $this->currentBusinessStart($nowLocal);

        $fromLocal = $currentBusinessStart->copy()->subDays(89);
        $fromUtc = $fromLocal->copy()->utc();
        $toUtc = $nowLocal->copy()->utc();

        $offsetMinutes = (int) $nowLocal->utcOffset();
        $localExpr = "DATE_ADD(settled_at, INTERVAL {$offsetMinutes} MINUTE)";
        $businessDateExpr = "DATE(DATE_SUB({$localExpr}, INTERVAL 4 HOUR))";
        $lunchCondition = "TIME({$localExpr}) >= '04:00:00' AND TIME({$localExpr}) < '17:00:00'";

        return TableSessionSettlement::query()
            ->where('shop_id', $this->shopId)
            ->whereBetween('settled_at', [$fromUtc, $toUtc])
            ->selectRaw('MIN(id) as id')
            ->selectRaw("{$businessDateExpr} as business_date")
            ->selectRaw("SUM(CASE WHEN {$lunchCondition} THEN final_total_minor ELSE 0 END) as lunch_total_minor")
            ->selectRaw("SUM(CASE WHEN NOT ({$lunchCondition}) THEN final_total_minor ELSE 0 END) as dinner_total_minor")
            ->selectRaw('SUM(final_total_minor) as day_total_minor')
            ->groupByRaw($businessDateExpr);
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
