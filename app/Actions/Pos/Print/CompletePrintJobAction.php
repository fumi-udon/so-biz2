<?php

namespace App\Actions\Pos\Print;

use App\Enums\PrintJobStatus;
use App\Models\PrintJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Terminal-state transition for a PrintJob based on the browser-side ack.
 *
 * - success()  : status pending|dispatched|failed → succeeded
 * - failure()  : status pending|dispatched        → failed (retryable)
 * - dispatched(): status pending                  → dispatched (in-flight)
 *
 * The same ack may be delivered twice by a flaky network; this action is
 * idempotent: transitioning succeeded → succeeded is a no-op, but any
 * attempt to leave a terminal state produces a RuntimeException so we
 * never overwrite history.
 */
final class CompletePrintJobAction
{
    public function markDispatched(int $printJobId): PrintJob
    {
        return $this->transition($printJobId, function (PrintJob $job): void {
            if ($job->status === PrintJobStatus::Dispatched) {
                return;
            }
            if ($job->status !== PrintJobStatus::Pending) {
                throw new RuntimeException('print_job cannot move to dispatched from '.$job->status->value);
            }
            $job->forceFill([
                'status' => PrintJobStatus::Dispatched,
                'attempt_count' => (int) $job->attempt_count + 1,
                'dispatched_at' => $job->dispatched_at ?? Carbon::now(),
            ])->save();
        });
    }

    public function markSucceeded(int $printJobId): PrintJob
    {
        return $this->transition($printJobId, function (PrintJob $job): void {
            if ($job->status === PrintJobStatus::Succeeded) {
                return;
            }
            if ($job->status === PrintJobStatus::Bypassed) {
                throw new RuntimeException('print_job is already terminal ('.$job->status->value.')');
            }
            $job->forceFill([
                'status' => PrintJobStatus::Succeeded,
                'completed_at' => Carbon::now(),
                'last_error_code' => null,
                'last_error_message' => null,
            ])->save();
        });
    }

    public function markFailed(int $printJobId, ?string $errorCode, ?string $errorMessage): PrintJob
    {
        return $this->transition($printJobId, function (PrintJob $job) use ($errorCode, $errorMessage): void {
            if ($job->status === PrintJobStatus::Failed) {
                // refresh error context on retry failures
                $job->forceFill([
                    'last_error_code' => $errorCode !== null ? mb_substr($errorCode, 0, 64) : null,
                    'last_error_message' => $errorMessage !== null ? mb_substr($errorMessage, 0, 500) : null,
                ])->save();

                return;
            }
            if ($job->status === PrintJobStatus::Succeeded || $job->status === PrintJobStatus::Bypassed) {
                throw new RuntimeException('print_job is already terminal ('.$job->status->value.')');
            }
            $job->forceFill([
                'status' => PrintJobStatus::Failed,
                'completed_at' => Carbon::now(),
                'last_error_code' => $errorCode !== null ? mb_substr($errorCode, 0, 64) : null,
                'last_error_message' => $errorMessage !== null ? mb_substr($errorMessage, 0, 500) : null,
            ])->save();
        });
    }

    private function transition(int $printJobId, callable $mutator): PrintJob
    {
        return DB::transaction(function () use ($printJobId, $mutator): PrintJob {
            $job = PrintJob::query()->whereKey($printJobId)->lockForUpdate()->first();
            if ($job === null) {
                throw new RuntimeException('print_job not found: '.$printJobId);
            }
            $mutator($job);

            return $job->refresh();
        });
    }
}
