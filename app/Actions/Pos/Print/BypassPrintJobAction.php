<?php

namespace App\Actions\Pos\Print;

use App\Enums\PrintJobStatus;
use App\Exceptions\Pos\DiscountPinRejectedException;
use App\Models\PrintJob;
use App\Models\Staff;
use App\Services\StaffPinAuthenticationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Forces a non-terminal print_job into the `bypassed` state after Manager
 * PIN verification (Job Level ≥ 4). Used when hardware is unreachable and
 * the cashier must close the session anyway.
 *
 * Emits idempotent behaviour: bypassing an already-bypassed job is a no-op
 * (same actor+reason). Cannot bypass a succeeded job (no reason to).
 */
final class BypassPrintJobAction
{
    private const int MAX_ATTEMPTS = 5;

    private const int DECAY_SECONDS = 60;

    private const int REQUIRED_LEVEL = 4;

    public function __construct(private readonly StaffPinAuthenticationService $pinService) {}

    public function execute(
        int $printJobId,
        int $operatorUserId,
        int $approverStaffId,
        string $approverPin,
        string $reason,
    ): PrintJob {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('bypass reason is required');
        }

        $staff = Staff::query()->with('jobLevel')->whereKey($approverStaffId)->first();
        if ($staff === null) {
            throw new DiscountPinRejectedException(__('pos.discount_approver_not_found'));
        }

        $error = $this->pinService->verify(
            staff: $staff,
            pin: $approverPin,
            context: 'pos-print-bypass',
            maxAttempts: self::MAX_ATTEMPTS,
            decaySeconds: self::DECAY_SECONDS,
        );
        if ($error !== null) {
            throw new DiscountPinRejectedException($error);
        }

        $level = (int) ($staff->jobLevel?->level ?? 0);
        if ($level < self::REQUIRED_LEVEL) {
            throw new RuntimeException('Manager-level (≥'.self::REQUIRED_LEVEL.') PIN required for print bypass');
        }

        return DB::transaction(function () use ($printJobId, $operatorUserId, $reason): PrintJob {
            $job = PrintJob::query()->whereKey($printJobId)->lockForUpdate()->first();
            if ($job === null) {
                throw new RuntimeException('print_job not found: '.$printJobId);
            }

            if ($job->status === PrintJobStatus::Bypassed) {
                return $job;
            }

            if ($job->status === PrintJobStatus::Succeeded) {
                throw new RuntimeException('cannot bypass a succeeded print_job');
            }

            $job->forceFill([
                'status' => PrintJobStatus::Bypassed,
                'bypassed_at' => Carbon::now(),
                'bypassed_by_user_id' => $operatorUserId,
                'bypass_reason' => mb_substr($reason, 0, 255),
                'completed_at' => Carbon::now(),
            ])->save();

            return $job->refresh();
        });
    }
}
