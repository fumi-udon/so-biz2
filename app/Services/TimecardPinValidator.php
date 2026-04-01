<?php

namespace App\Services;

use App\Models\Staff;
use Illuminate\Support\Facades\RateLimiter;

final class TimecardPinValidator
{
    /**
     * Validate PIN and return an error message on failure.
     */
    public function validate(Staff $staff, string $pinCode): ?string
    {
        if ($staff->pin_code === null || $staff->pin_code === '') {
            return 'Aucun code PIN défini. Contactez un responsable.';
        }

        $pinKey = 'pin-attempt:staff:'.$staff->id.':'.(request()->ip() ?? 'unknown');

        if (RateLimiter::tooManyAttempts($pinKey, 5)) {
            return 'Trop de tentatives PIN incorrectes. Veuillez patienter 1 minute.';
        }

        if (! hash_equals((string) $staff->pin_code, (string) $pinCode)) {
            RateLimiter::hit($pinKey, 60);

            return 'Code PIN incorrect.';
        }

        RateLimiter::clear($pinKey);

        return null;
    }
}
