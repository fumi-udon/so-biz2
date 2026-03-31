<?php

namespace App\Filament\Resources\DailyTips\Pages;

use App\Filament\Resources\DailyTips\DailyTipResource;
use App\Filament\Resources\Staff\StaffResource;
use App\Models\DailyTip;
use App\Models\DailyTipDistribution;
use App\Support\BusinessDate;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Carbon;

class TipDashboard extends Page
{
    protected static string $resource = DailyTipResource::class;

    protected static string $view = 'filament.resources.daily-tips.pages.tip-dashboard';

    protected static ?string $title = 'チップ支払い';

    protected static ?string $navigationLabel = 'チップ支払い';

    public string $week_start = '';

    /** 月ジャンプ用（input type="month"） */
    public string $month_picker = '';

    /** 週の開始曜日（Carbon: 日=0 … 土=6）。デフォルトは月曜。 */
    public int $startDayOfWeek = Carbon::MONDAY;

    public function mount(): void
    {
        $anchor = BusinessDate::current();
        $this->week_start = $anchor->copy()->startOfWeek($this->startDayOfWeek)->toDateString();
        $this->month_picker = Carbon::parse($this->week_start)->format('Y-m');
    }

    public function previousWeek(): void
    {
        $this->week_start = Carbon::parse($this->week_start)
            ->startOfDay()
            ->subDays(7)
            ->toDateString();
        $this->syncMonthPicker();
    }

    public function nextWeek(): void
    {
        $this->week_start = Carbon::parse($this->week_start)
            ->startOfDay()
            ->addDays(7)
            ->toDateString();
        $this->syncMonthPicker();
    }

    public function updatedMonthPicker(): void
    {
        if ($this->month_picker === '') {
            return;
        }

        [$y, $m] = array_map('intval', explode('-', $this->month_picker, 2));
        $this->week_start = Carbon::create($y, $m, 1)
            ->startOfDay()
            ->startOfWeek($this->startDayOfWeek)
            ->toDateString();
    }

    public function updatedWeekStart(): void
    {
        $this->syncMonthPicker();
    }

    public function updatedStartDayOfWeek(): void
    {
        $dow = max(0, min(6, (int) $this->startDayOfWeek));
        $this->startDayOfWeek = $dow;

        $anchor = Carbon::parse($this->week_start)->startOfDay()->addDays(3);
        $this->week_start = $anchor->copy()->startOfWeek($this->startDayOfWeek)->toDateString();
        $this->syncMonthPicker();
    }

    protected function syncMonthPicker(): void
    {
        $this->month_picker = Carbon::parse($this->week_start)->format('Y-m');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calculate')
                ->label('チップ計算')
                ->icon('heroicon-o-calculator')
                ->url(DailyTipResource::getUrl('calculate')),
            Action::make('list_all')
                ->label('レコード一覧')
                ->icon('heroicon-o-table-cells')
                ->url(DailyTipResource::getUrl('list_all')),
            CreateAction::make(),
        ];
    }

    /**
     * @return array{day_keys: list<string>, day_labels: list<string>, rows: list<array<string, mixed>>, week_total: float}
     */
    public function getWeekMatrixProperty(): array
    {
        $start = Carbon::parse($this->week_start)->startOfDay();
        $endDate = $start->copy()->addDays(6)->toDateString();

        $dayKeys = [];
        $dayLabels = [];
        $wd = ['月', '火', '水', '木', '金', '土', '日'];
        for ($i = 0; $i < 7; $i++) {
            $d = $start->copy()->addDays($i);
            $dayKeys[] = $d->toDateString();
            $dayLabels[] = $d->format('n/j').' '.$wd[$d->dayOfWeekIso - 1];
        }

        $dists = DailyTipDistribution::query()
            ->with(['staff:id,name', 'dailyTip:id,business_date,shift'])
            ->whereHas('dailyTip', fn ($q) => $q->whereBetween('business_date', [$start->toDateString(), $endDate]))
            ->get();

        /** @var array<int, array{staff: \App\Models\Staff, days: array<string, array{amount: float, lunch_amount: float, dinner_amount: float, notes: list<string>, shifts: list<string>}>, week_total: float}> $byStaff */
        $byStaff = [];
        $weekGrand = 0.0;

        foreach ($dists as $dist) {
            $dailyTip = $dist->dailyTip;
            if ($dailyTip === null || $dist->staff === null) {
                continue;
            }

            $dateStr = $dailyTip->business_date->toDateString();
            $sid = (int) $dist->staff_id;

            if (! isset($byStaff[$sid])) {
                $days = [];
                foreach ($dayKeys as $dk) {
                    $days[$dk] = [
                        'amount' => 0.0,
                        'lunch_amount' => 0.0,
                        'dinner_amount' => 0.0,
                        'notes' => [],
                        'shifts' => [],
                    ];
                }
                $byStaff[$sid] = [
                    'staff' => $dist->staff,
                    'days' => $days,
                    'week_total' => 0.0,
                ];
            }

            $amt = (float) $dist->amount;
            $byStaff[$sid]['days'][$dateStr]['amount'] += $amt;

            if ($dailyTip->shift === 'lunch') {
                $byStaff[$sid]['days'][$dateStr]['lunch_amount'] += $amt;
            } elseif ($dailyTip->shift === 'dinner') {
                $byStaff[$sid]['days'][$dateStr]['dinner_amount'] += $amt;
            }

            $byStaff[$sid]['days'][$dateStr]['shifts'][] = (string) $dailyTip->shift;
            if (filled($dist->note)) {
                $byStaff[$sid]['days'][$dateStr]['notes'][] = (string) $dist->note;
            }
            $byStaff[$sid]['week_total'] += $amt;
            $weekGrand += $amt;
        }

        $rows = collect($byStaff)
            ->sortBy(fn (array $r) => $r['staff']->name)
            ->values()
            ->map(function (array $r) use ($dayKeys): array {
                $dayCells = [];
                foreach ($dayKeys as $dk) {
                    $cell = $r['days'][$dk];
                    $dayCells[] = [
                        'date' => $dk,
                        'amount' => round($cell['amount'], 3),
                        'lunch_amount' => round($cell['lunch_amount'], 3),
                        'dinner_amount' => round($cell['dinner_amount'], 3),
                        'note_hint' => $cell['notes'] !== [] ? implode(' · ', array_unique($cell['notes'])) : null,
                        'shift_tags' => array_values(array_unique($cell['shifts'])),
                    ];
                }

                return [
                    'staff_id' => $r['staff']->id,
                    'name' => $r['staff']->name,
                    'days' => $dayCells,
                    'week_total' => round($r['week_total'], 3),
                ];
            })
            ->all();

        return [
            'day_keys' => $dayKeys,
            'day_labels' => $dayLabels,
            'rows' => $rows,
            'week_total' => round($weekGrand, 3),
        ];
    }

    /**
     * @return array{month_label: string, pool: float, staff_bars: list<array{staff_id: int, name: string, total: float, pct: float}>, max: float}
     */
    public function getMonthAnalysisProperty(): array
    {
        $ref = Carbon::parse($this->week_start);
        $monthStart = $ref->copy()->startOfMonth();
        $monthEnd = $ref->copy()->endOfMonth();

        $pool = (float) DailyTip::query()
            ->whereBetween('business_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('total_amount');

        $aggregates = DailyTipDistribution::query()
            ->selectRaw('staff_id, SUM(amount) as total')
            ->whereHas('dailyTip', fn ($q) => $q->whereBetween('business_date', [$monthStart->toDateString(), $monthEnd->toDateString()]))
            ->groupBy('staff_id')
            ->orderByDesc('total')
            ->with('staff:id,name')
            ->get();

        $max = (float) ($aggregates->max('total') ?? 0);
        $max = $max > 0 ? $max : 1.0;

        $staffBars = $aggregates->map(function ($row) use ($max): array {
            $total = (float) $row->total;

            return [
                'staff_id' => (int) $row->staff_id,
                'name' => $row->staff?->name ?? ('#'.$row->staff_id),
                'total' => round($total, 3),
                'pct' => round(($total / $max) * 100, 1),
            ];
        })->values()->all();

        return [
            'month_label' => $ref->format('Y年 n月'),
            'pool' => round($pool, 3),
            'staff_bars' => $staffBars,
            'max' => $max,
        ];
    }

    public function getWeekRangeLabelProperty(): string
    {
        $start = Carbon::parse($this->week_start)->startOfDay();
        $end = $start->copy()->addDays(6);

        return $start->format('Y/m/d').' — '.$end->format('m/d');
    }

    public function staffEditUrl(int $staffId): string
    {
        return StaffResource::getUrl('edit', ['record' => $staffId]);
    }
}
