<?php

namespace App\Services;

use App\Models\Staff;
use Illuminate\Support\Facades\RateLimiter;

final class StaffPinAuthenticationService
{
    /**
     * Verify a staff PIN with rate limiting.
     *
     * Returns null on success, or a French error message on failure.
     * On repeated failures the rate limiter is incremented; on success it is cleared.
     */
    public function verify(
        Staff $staff,
        string $pin,
        string $context,
        int $maxAttempts,
        int $decaySeconds,
    ): ?string {
        $key = "{$context}:staff:{$staff->id}:".(request()->ip() ?? 'unknown');

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return 'Trop de tentatives PIN incorrectes. Veuillez patienter.';
        }

        if ($staff->pin_code === null || $staff->pin_code === '') {
            return 'Aucun code PIN défini. Contactez un responsable.';
        }

        if (! hash_equals((string) $staff->pin_code, (string) $pin)) {
            RateLimiter::hit($key, $decaySeconds);

            return 'Code PIN incorrect.';
        }

        RateLimiter::clear($key);

        return null;
    }
}
