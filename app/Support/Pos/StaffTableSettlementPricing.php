<?php

namespace App\Support\Pos;

use App\Domains\Pos\Pricing\PricingEngine;
use App\Domains\Pos\Pricing\PricingInput;
use App\Domains\Pos\Pricing\PricingResult;
use App\Domains\Pos\Tables\TableCategory;
use App\Models\OrderLine;
use App\Models\PosOrder;
use Illuminate\Support\Collection;

/**
 * Single source for staff-meal (賄い) tables 100–104: 50% off net subtotal after line
 * discounts, without mutating order line / order discount DB columns.
 *
 * {@see ApplyStaffDiscount::STAFF_DISCOUNT_BP} と同じ 5000bp（50%）。
 */
final class StaffTableSettlementPricing
{
    public const int STAFF_DISCOUNT_BP = 5000;

    public const int STAFF_MEAL_TABLE_MIN_ID = 100;

    public const int STAFF_MEAL_TABLE_MAX_ID = 104;

    public static function isStaffMealTableId(int $restaurantTableId): bool
    {
        $slot = TableCategory::canonicalSlot($restaurantTableId);

        return $slot >= self::STAFF_MEAL_TABLE_MIN_ID
            && $slot <= self::STAFF_MEAL_TABLE_MAX_ID;
    }

    /**
     * @param  int  $orderSubtotalAfterLines  Sum of (line_total_minor − line_discount_minor) for all non-voided lines
     * @param  int  $dbOrderDiscountSum  Sum of pos_orders.order_discount_minor (DB)
     */
    public static function effectiveOrderDiscountMinor(
        int $orderSubtotalAfterLines,
        int $dbOrderDiscountSum,
        ?int $restaurantTableId,
    ): int {
        if ($restaurantTableId === null || ! self::isStaffMealTableId($restaurantTableId)) {
            return $dbOrderDiscountSum;
        }

        $halfOffDiscount = $orderSubtotalAfterLines - intdiv($orderSubtotalAfterLines * self::STAFF_DISCOUNT_BP, 10000);

        return max(0, $halfOffDiscount);
    }

    /**
     * Canonical session pricing for POS footer, cloture modal, settlement, receipt.
     *
     * @param  Collection<int, PosOrder>  $orders  Non-voided pos orders (lines optional if eager-loaded)
     */
    public static function calculateFromPosOrders(
        Collection $orders,
        int $restaurantTableId,
        ?PricingEngine $pricingEngine = null,
    ): PricingResult {
        $engine = $pricingEngine ?? app(PricingEngine::class);

        $lineSubtotals = [];
        $lineDiscounts = [];
        $netAfterLines = 0;
        $orderDiscountSum = 0;

        foreach ($orders as $order) {
            $lines = $order->relationLoaded('lines')
                ? $order->lines->sortBy('id')->values()
                : OrderLine::query()->where('order_id', $order->id)->orderBy('id')->get();

            foreach ($lines as $line) {
                $lineSubtotals[] = (int) $line->line_total_minor;
                $ld = (int) ($line->line_discount_minor ?? 0);
                $lineDiscounts[] = $ld;
                $netAfterLines += max(0, (int) $line->line_total_minor - $ld);
            }

            $orderDiscountSum += (int) ($order->order_discount_minor ?? 0);
        }

        $effectiveOrderDiscount = self::effectiveOrderDiscountMinor(
            $netAfterLines,
            $orderDiscountSum,
            $restaurantTableId,
        );

        return $engine->calculate(new PricingInput(
            lineSubtotalsMinor: $lineSubtotals,
            lineDiscountsMinor: $lineDiscounts,
            orderDiscountMinor: $effectiveOrderDiscount,
        ));
    }
}
