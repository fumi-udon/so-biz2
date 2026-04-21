<?php

namespace App\Domains\Pos\Pricing;

final class PricingEngine
{
    public const int BILL_FLOOR_STEP_MINOR = 100;

    public function calculate(PricingInput $input): PricingResult
    {
        $orderSubtotal = 0;

        foreach ($input->lineSubtotalsMinor as $idx => $lineSubtotal) {
            $lineDiscount = $input->lineDiscountsMinor[$idx] ?? 0;
            $lineDiscountApplied = min($lineSubtotal, max(0, $lineDiscount));
            $orderSubtotal += ($lineSubtotal - $lineDiscountApplied);
        }

        $orderDiscountApplied = min($orderSubtotal, $input->orderDiscountMinor);
        $totalBeforeRounding = $orderSubtotal - $orderDiscountApplied;

        $finalTotal = intdiv($totalBeforeRounding, self::BILL_FLOOR_STEP_MINOR) * self::BILL_FLOOR_STEP_MINOR;
        $roundingAdjustment = $totalBeforeRounding - $finalTotal;

        return new PricingResult(
            orderSubtotalMinor: $orderSubtotal,
            orderDiscountAppliedMinor: $orderDiscountApplied,
            totalBeforeRoundingMinor: $totalBeforeRounding,
            roundingAdjustmentMinor: $roundingAdjustment,
            finalTotalMinor: $finalTotal,
        );
    }
}
