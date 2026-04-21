<?php

namespace App\Exceptions\Pos;

use App\Services\StaffPinAuthenticationService;
use RuntimeException;

/**
 * Thrown when the PIN verification step of any Record*DiscountAction fails
 * (wrong PIN, rate-limited, staff disabled). The message is already
 * user-safe/localised by {@see StaffPinAuthenticationService}.
 */
final class DiscountPinRejectedException extends RuntimeException {}
