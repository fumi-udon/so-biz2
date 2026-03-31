<?php

namespace App\Observers;

use App\Models\DailyTipDistribution;
use App\Models\StaffTip;
use App\Support\DailyTipAuditLogger;

class DailyTipDistributionObserver
{
    public function created(DailyTipDistribution|StaffTip $distribution): void
    {
        DailyTipAuditLogger::forDistribution('created', $distribution);
    }

    public function updated(DailyTipDistribution|StaffTip $distribution): void
    {
        DailyTipAuditLogger::forDistribution('updated', $distribution);
    }

    public function deleted(DailyTipDistribution|StaffTip $distribution): void
    {
        DailyTipAuditLogger::forDistribution('deleted', $distribution);
    }
}
