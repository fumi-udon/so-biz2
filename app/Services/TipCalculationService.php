<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\DailyTip;

class TipCalculationService
{
    /**
     * チップ配分のウェイトを float に正規化する。
     *
     * Livewire のネストした wire:model などでスカラーが 1 要素の配列に包まれると、
     * 生の (float) キャストは PHP の仕様で 1.0 になる（例: (float) [100] === 1.0）。
     * また "1,000.500" のような桁区切り付き文字列は (float) では 1.0 に化ける。
     */
    public static function normalizeWeightScalar(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        if (is_array($value)) {
            if (count($value) === 1) {
                return self::normalizeWeightScalar(reset($value));
            }

            return 0.0;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return 0.0;
            }

            if (is_numeric($trimmed)) {
                return (float) $trimmed;
            }

            // US-style thousands only (e.g. "1,000.500"); do not strip "," used as decimal separator.
            if (preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d+)?$/', $trimmed)) {
                return (float) str_replace(',', '', $trimmed);
            }

            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        return 0.0;
    }

    public function generateInitialDistributions(DailyTip $dailyTip): void
    {
        $dailyTip->loadMissing('distributions');

        $attendances = Attendance::query()
            ->whereDate('date', $dailyTip->business_date)
            ->when(
                $dailyTip->shift === 'lunch',
                fn ($query) => $query->whereNotNull('lunch_in_at'),
                fn ($query) => $query->whereNotNull('dinner_in_at'),
            )
            ->with(['staff' => fn ($query) => $query->withTrashed()->with('jobLevel')])
            ->get()
            ->filter(fn (Attendance $attendance): bool => $attendance->staff !== null)
            ->unique('staff_id')
            ->values();

        $existingStaffIds = $dailyTip->distributions->pluck('staff_id')->all();

        $inserts = [];

        foreach ($attendances as $attendance) {
            if (in_array($attendance->staff_id, $existingStaffIds, true)) {
                continue;
            }

            $rawWeight = $attendance->staff->jobLevel?->default_weight;
            $weight = $rawWeight === null ? 1.0 : self::normalizeWeightScalar($rawWeight);
            $lateMinutes = (int) ($attendance->late_minutes ?? 0);
            $isTardy = $lateMinutes > 0;

            if ((bool) ($attendance->is_edited_by_admin ?? false) && $lateMinutes === 0) {
                $isTardy = false;
            }

            if ($isTardy) {
                $weight = 0.0;
            }

            $inserts[] = [
                'staff_id' => $attendance->staff_id,
                'weight' => $weight,
                'amount' => 0,
                'is_tardy_deprived' => $isTardy,
                'is_manual_added' => false,
                'note' => null,
            ];
        }

        if ($inserts !== []) {
            $dailyTip->distributions()->createMany($inserts);
        }

        $this->recalculateAmounts($dailyTip->fresh('distributions'));
    }

    /**
     * ウェイト配列から各インデックスの分配額を算出（TND・小数第3位・端数は最大剰余法）。
     *
     * @param  array<int, float|int|string|null>  $weights
     * @return array<int, float> 入力と同じ長さ・同じ順序
     */
    public function distributeAmounts(array $weights, float $targetTotal): array
    {
        $targetTotal = round($targetTotal, 3);
        $count = count($weights);

        if ($count === 0) {
            return [];
        }

        $normalized = [];
        foreach ($weights as $i => $w) {
            $normalized[$i] = max(0.0, self::normalizeWeightScalar($w));
        }

        $totalWeight = array_sum($normalized);

        if ($totalWeight <= 0) {
            return array_fill(0, $count, 0.0);
        }

        $draft = [];
        foreach ($normalized as $index => $weight) {
            $raw = ($weight / $totalWeight) * $targetTotal;
            $floor = floor($raw * 1000) / 1000;
            $draft[] = [
                'index' => $index,
                'floor' => $floor,
                'fraction' => $raw - $floor,
            ];
        }

        $flooredTotal = 0.0;
        foreach ($draft as $item) {
            $flooredTotal += $item['floor'];
        }

        $remainderMilli = (int) round(($targetTotal - $flooredTotal) * 1000);

        usort(
            $draft,
            fn (array $a, array $b): int => $b['fraction'] <=> $a['fraction']
        );

        for ($i = 0; $i < $remainderMilli; $i++) {
            if (! isset($draft[$i])) {
                break;
            }

            $draft[$i]['floor'] += 0.001;
        }

        $amounts = array_fill(0, $count, 0.0);
        foreach ($draft as $item) {
            $amounts[$item['index']] = round((float) $item['floor'], 3);
        }

        return $amounts;
    }

    public function recalculateAmounts(DailyTip $dailyTip): void
    {
        /** @var Collection<int, \App\Models\DailyTipDistribution> $rows */
        $rows = $dailyTip->distributions()->get();

        $targetTotal = round((float) $dailyTip->total_amount, 3);

        if ($rows->isEmpty()) {
            return;
        }

        $weights = $rows->map(fn ($row) => max(0, self::normalizeWeightScalar($row->weight)))->values()->all();
        $amounts = $this->distributeAmounts($weights, $targetTotal);

        foreach ($rows->values() as $i => $row) {
            $row->amount = $amounts[$i] ?? 0.0;
            $row->save();
        }
    }
}
