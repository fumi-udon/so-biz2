<?php

namespace App\Observers;

use App\Models\DailyTipDistribution;
use App\Support\DailyTipAuditLogger;

class DailyTipDistributionObserver
{
    public function created(DailyTipDistribution $distribution): void
    {
        DailyTipAuditLogger::forDistribution('created', $distribution);
    }

    public function updated(DailyTipDistribution $distribution): void
    {
        DailyTipAuditLogger::forDistribution('updated', $distribution);
    }

    public function deleted(DailyTipDistribution $distribution): void
    {
        DailyTipAuditLogger::forDistribution('deleted', $distribution);
    }
}
