<?php

namespace App\Support;

use App\Models\DailyTip;
use App\Models\DailyTipAudit;
use App\Models\DailyTipDistribution;
use App\Models\StaffTip;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DailyTipAuditLogger
{
    /**
     * @param array<string, mixed> $details
     */
    public static function write(string $action, ?string $targetDate, ?string $shift, array $details = []): void
    {
        $userId = Auth::id();

        DB::afterCommit(function () use ($action, $targetDate, $shift, $details, $userId): void {
            try {
                DailyTipAudit::query()->create([
                    'user_id' => $userId,
                    'action' => $action,
                    'target_date' => $targetDate,
                    'shift' => $shift,
                    'details' => $details,
                ]);
            } catch (Throwable) {
                // 監査ログの失敗で本処理は止めない。
            }
        });
    }

    public static function forDailyTip(string $event, DailyTip $tip): void
    {
        $before = null;
        $after = null;

        if ($event === 'updated') {
            $changes = $tip->getChanges();
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            $before = [];
            $after = [];
            foreach (array_keys($changes) as $field) {
                $before[$field] = $tip->getOriginal($field);
                $after[$field] = $tip->getAttribute($field);
            }
        }

        self::write(
            'daily_tip_'.$event,
            optional($tip->business_date)->toDateString(),
            $tip->shift,
            array_filter([
                'daily_tip_id' => $tip->id,
                'total_amount' => (float) $tip->total_amount,
                'before' => $before,
                'after' => $after,
            ], fn (mixed $value): bool => $value !== null)
        );
    }

    public static function forDistribution(string $event, DailyTipDistribution|StaffTip $distribution): void
    {
        if (DailyTipAuditContext::distributionAuditSuppressed()) {
            return;
        }

        $tip = $distribution->dailyTip()->first();
        $targetDate = optional($tip?->business_date)->toDateString();
        $shift = $tip?->shift;

        $before = null;
        $after = null;

        if ($event === 'updated') {
            $changes = $distribution->getChanges();
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            $before = [];
            $after = [];
            foreach (array_keys($changes) as $field) {
                $before[$field] = $distribution->getOriginal($field);
                $after[$field] = $distribution->getAttribute($field);
            }
        }

        self::write(
            'distribution_'.$event,
            $targetDate,
            $shift,
            array_filter([
                'daily_tip_id' => $distribution->daily_tip_id,
                'distribution_id' => $distribution->id,
                'staff_id' => $distribution->staff_id,
                'amount' => (float) $distribution->amount,
                'weight' => (float) $distribution->weight,
                'before' => $before,
                'after' => $after,
            ], fn (mixed $value): bool => $value !== null)
        );
    }
}
