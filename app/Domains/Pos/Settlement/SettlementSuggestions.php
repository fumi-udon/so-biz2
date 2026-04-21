<?php

namespace App\Domains\Pos\Settlement;

use InvalidArgumentException;

/**
 * Result DTO for {@see SettlementSuggestionService}.
 *
 * `justeMinor` is the exact bill amount the customer owes.
 * `procheMinor` is a strictly-ascending, de-duplicated list of "convenient round
 * tenders" (in minor) that a Tunisian customer is likely to hand over,
 * computed from the 5/10/20/50 TND denominations. Every entry is strictly
 * greater than `justeMinor`, so the cashier can never be shown a duplicate
 * suggestion (e.g. when the bill is already exactly 20 TND).
 */
final readonly class SettlementSuggestions
{
    /**
     * @param  list<int>  $procheMinor
     */
    public function __construct(
        public int $justeMinor,
        public array $procheMinor,
    ) {
        if ($this->justeMinor < 0) {
            throw new InvalidArgumentException('justeMinor must be >= 0');
        }

        $prev = null;
        foreach ($this->procheMinor as $v) {
            if (! is_int($v) || $v <= $this->justeMinor) {
                throw new InvalidArgumentException('procheMinor values must be integers strictly greater than justeMinor');
            }
            if ($prev !== null && $v <= $prev) {
                throw new InvalidArgumentException('procheMinor must be strictly ascending without duplicates');
            }
            $prev = $v;
        }
    }

    /**
     * @return array{juste: int, proche: list<int>}
     */
    public function toArray(): array
    {
        return [
            'juste' => $this->justeMinor,
            'proche' => $this->procheMinor,
        ];
    }
}
