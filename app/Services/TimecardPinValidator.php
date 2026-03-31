<?php

namespace App\Services;

use App\Models\Staff;
use Illuminate\Support\Facades\RateLimiter;

final class TimecardPinValidator
{
    /**
     * PIN を検証する。問題があればエラーメッセージを返し、成功時は null。
     */
    public function validate(Staff $staff, string $pinCode): ?string
    {
        if ($staff->pin_code === null || $staff->pin_code === '') {
            return 'Aucun code PIN défini. Contactez un responsable.';
        }

        $pinKey = 'pin-attempt:'.$staff->id;

        if (RateLimiter::tooManyAttempts($pinKey, 5)) {
            return 'PINの入力を複数回間違えました。1分間お待ちください。';
        }

        if (! hash_equals((string) $staff->pin_code, (string) $pinCode)) {
            RateLimiter::hit($pinKey, 60);

            return 'Code PIN incorrect.';
        }

        RateLimiter::clear($pinKey);

        return null;
    }
}
