<?php

namespace App\Support\Pos;

use Illuminate\Support\Facades\Log;

/**
 * SPEED_TEST-only probe logger for temporary POS investigations.
 *
 * Remove this file and its call sites once root cause is fixed.
 */
final class SpeedTestProbe
{
    public static function enabled(): bool
    {
        return (bool) config('app.speed_test', false);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function log(string $tag, array $context = []): void
    {
        if (! self::enabled()) {
            return;
        }

        Log::info('POS_SPEED_PROBE '.$tag, $context);
    }
}
