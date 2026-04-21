<?php

namespace App\Domains\Pos\Pricing;

use InvalidArgumentException;

final readonly class PricingInput
{
    /**
     * @param  list<int>  $lineSubtotalsMinor
     * @param  list<int>  $lineDiscountsMinor
     */
    public function __construct(
        public array $lineSubtotalsMinor,
        public array $lineDiscountsMinor,
        public int $orderDiscountMinor,
    ) {
        if (count($this->lineSubtotalsMinor) !== count($this->lineDiscountsMinor)) {
            throw new InvalidArgumentException('line subtotal and discount length mismatch');
        }
        if ($this->orderDiscountMinor < 0) {
            throw new InvalidArgumentException('order discount must be >= 0');
        }
        foreach ($this->lineSubtotalsMinor as $v) {
            if (! is_int($v) || $v < 0) {
                throw new InvalidArgumentException('line subtotal must be integer >= 0');
            }
        }
        foreach ($this->lineDiscountsMinor as $v) {
            if (! is_int($v) || $v < 0) {
                throw new InvalidArgumentException('line discount must be integer >= 0');
            }
        }
    }
}
