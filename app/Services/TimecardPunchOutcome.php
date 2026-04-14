<?php

namespace App\Services;

final class TimecardPunchOutcome
{
    /**
     * @param  'none'|'mypage_late'|'mypage_success'|'timecard_success'  $postFlow
     */
    public function __construct(
        public bool $ok,
        public ?string $errorMessage = null,
        public string $postFlow = 'none',
        public ?int $lateMinutes = null,
        public bool $tipAutoApplied = false,
    ) {}
}
