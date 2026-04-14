<?php

namespace App\Filament\Widgets;

use App\Models\Finance;
use App\Support\BusinessDate;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class HeadquartersCloseHistoryWidget extends Widget
{
    protected static string $view = 'filament.widgets.headquarters-close-history';

    protected static ?int $sort = -8;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $u = auth()->user();
        $superAdmin = config('filament-shield.super_admin.name', 'super_admin');

        return $u?->hasRole($superAdmin) === true
            || $u?->hasRole('Owner') === true
            || $u?->isPiloteOnly() === true;
    }

    /**
     * @return array{
     *     financeRows: Collection<int, Finance>,
     *     auditRows: Collection<int, Finance>,
     *     range2Label: string,
     *     range3Label: string,
     * }
     */
    protected function getViewData(): array
    {
        $bd = BusinessDate::current();
        $d0 = $bd->toDateString();
        $d2 = $bd->copy()->subDays(2)->toDateString();
        $d6 = $bd->copy()->subDays(6)->toDateString();

        $financeRows = Finance::query()
            ->whereBetween('business_date', [$d6, $d0])
            ->with(['responsibleStaff', 'creator', 'panelOperator'])
            ->orderByDesc('business_date')
            ->orderBy('shift')
            ->get();

        $auditRows = Finance::query()
            ->whereBetween('business_date', [$d2, $d0])
            ->with(['responsibleStaff', 'panelOperator'])
            ->orderByDesc('business_date')
            ->orderBy('shift')
            ->get();

        return [
            'financeRows' => $financeRows,
            'auditRows' => $auditRows,
            'range2Label' => $d6.' — '.$d0,
            'range3Label' => $d2.' — '.$d0,
        ];
    }
}
