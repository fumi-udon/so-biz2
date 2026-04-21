<?php

namespace App\Actions\Pos\Discount;

use App\Domains\Pos\Discount\AuthorizationContext;
use App\Domains\Pos\Discount\DiscountPolicy;
use App\Domains\Pos\Discount\DiscountType;
use App\Exceptions\Pos\DiscountPinRejectedException;
use App\Models\Staff;
use App\Services\StaffPinAuthenticationService;

/**
 * Shared collaborator for every Record*DiscountAction: verifies PIN,
 * resolves the approver's Staff record (+ Job Level), and produces an
 * {@see AuthorizationContext} that the domain-level Apply*Discount class
 * accepts. Keeps PIN rate-limit + audit semantics in one place.
 *
 * Rate-limit policy (aligned with close-check + timecard use-cases in the
 * project): 5 attempts per 60 seconds per staff/IP.
 */
final class DiscountAuthorizer
{
    private const int MAX_ATTEMPTS = 5;

    private const int DECAY_SECONDS = 60;

    public function __construct(
        private readonly StaffPinAuthenticationService $pinService,
        private readonly DiscountPolicy $policy,
    ) {}

    public function verifyAndBuildContext(RecordDiscountRequest $req, DiscountType $type): AuthorizationContext
    {
        $staff = Staff::query()
            ->where('shop_id', $req->shopId)
            ->whereKey($req->approverStaffId)
            ->first();

        if ($staff === null) {
            throw new DiscountPinRejectedException(__('pos.discount_approver_not_found'));
        }

        $error = $this->pinService->verify(
            staff: $staff,
            pin: $req->approverPin,
            context: 'pos-discount',
            maxAttempts: self::MAX_ATTEMPTS,
            decaySeconds: self::DECAY_SECONDS,
        );

        if ($error !== null) {
            throw new DiscountPinRejectedException($error);
        }

        $jobLevel = (int) ($staff->jobLevel?->level ?? 0);

        $ctx = new AuthorizationContext(
            actorUserId: $req->operatorUserId,
            actorJobLevel: $jobLevel,
            reason: $req->reason,
        );

        $this->policy->assertAuthorized($ctx, $type);

        return $ctx;
    }
}
