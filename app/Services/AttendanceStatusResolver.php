<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class AttendanceStatusResolver
{
    private const GRACE_MINUTES_AFTER_PLANNED_START = 10;

    /** 深夜営業の日付またぎ: 0〜5 時台は翌暦日の締切として扱う（startOfDay だけだと前日扱いになるのを防ぐ） */
    private const EARLY_MORNING_HOUR_MAX = 5;

    /**
     * clocked | extra | late | future | none
     */
    public function resolveMealStatus(Carbon $businessDate, ?string $scheduledStart, ?Carbon $inAt): string
    {
        $hasScheduledShift = filled($scheduledStart);

        if ($inAt && (! $hasScheduledShift)) {
            return 'extra';
        }

        if ($inAt && $hasScheduledShift) {
            return 'clocked';
        }

        if (! $hasScheduledShift) {
            return 'none';
        }

        if (! $scheduledStart) {
            return 'future';
        }

        try {
            [$h, $m] = array_map('intval', explode(':', trim($scheduledStart), 2));
            $deadline = $businessDate->copy()->startOfDay()->setTime($h, $m, 0);
            if ($h >= 0 && $h <= self::EARLY_MORNING_HOUR_MAX) {
                $deadline->addDay();
            }
            $deadline->addMinutes(self::GRACE_MINUTES_AFTER_PLANNED_START);
        } catch (\Throwable) {
            return 'future';
        }

        return now()->greaterThan($deadline) ? 'late' : 'future';
    }

    public function icon(string $status): string
    {
        return match ($status) {
            'clocked' => '🟢',
            'extra' => '🆘',
            'late' => '🔴',
            'future' => '⚪',
            default => '—',
        };
    }
}
