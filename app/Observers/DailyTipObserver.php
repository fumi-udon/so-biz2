<?php

namespace App\Observers;

use App\Models\DailyTip;
use App\Support\DailyTipAuditLogger;

class DailyTipObserver
{
    public function created(DailyTip $dailyTip): void
    {
        DailyTipAuditLogger::forDailyTip('created', $dailyTip);
    }

    public function updated(DailyTip $dailyTip): void
    {
        DailyTipAuditLogger::forDailyTip('updated', $dailyTip);
    }

    public function deleted(DailyTip $dailyTip): void
    {
        DailyTipAuditLogger::forDailyTip('deleted', $dailyTip);
    }
}
