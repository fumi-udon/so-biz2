<?php

namespace App\Support;

final class DailyTipAuditContext
{
    private static bool $suppressDistributionAudit = false;

    public static function suppressDistributionAudit(bool $suppress): void
    {
        self::$suppressDistributionAudit = $suppress;
    }

    public static function distributionAuditSuppressed(): bool
    {
        return self::$suppressDistributionAudit;
    }
}
