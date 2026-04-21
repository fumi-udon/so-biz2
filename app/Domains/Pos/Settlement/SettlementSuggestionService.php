<?php

namespace App\Domains\Pos\Settlement;

use InvalidArgumentException;

/**
 * Computes "Juste" (exact) and "Proche" (convenient round-up) tender suggestions
 * from a bill amount, using Tunisian banknote/coin denominations of
 * 5, 10, 20 and 50 TND.
 *
 * Algorithm (deterministic, pure, no I/O):
 *   juste  = finalTotalMinor
 *   proche = sort(unique(ceil(finalTotalMinor / D) * D
 *                        for D in [5_000, 10_000, 20_000, 50_000]))
 *   proche = [x for x in proche if x > juste]      // strictly greater only
 *
 * This guarantees:
 *  - When finalTotal is already a whole multiple of every denomination (e.g.
 *    20.000 TND), none of the generated values exceed it, so `proche` is empty
 *    for the 20.000 case except for the 50.000 candidate. In particular we
 *    never emit a duplicate of juste.
 *  - Numbers stay in integer minor; no float arithmetic is performed.
 */
final class SettlementSuggestionService
{
    /**
     * Denominations in integer minor (1 TND = 1000 minor).
     *
     * @var list<int>
     */
    private const DENOMINATIONS_MINOR = [5_000, 10_000, 20_000, 50_000];

    public function suggest(int $finalTotalMinor): SettlementSuggestions
    {
        if ($finalTotalMinor < 0) {
            throw new InvalidArgumentException('finalTotalMinor must be >= 0');
        }

        $candidates = [];
        foreach (self::DENOMINATIONS_MINOR as $denom) {
            $candidates[] = $this->ceilToMultiple($finalTotalMinor, $denom);
        }

        $unique = array_values(array_unique($candidates, SORT_NUMERIC));
        sort($unique, SORT_NUMERIC);

        $proche = array_values(array_filter(
            $unique,
            static fn (int $v): bool => $v > $finalTotalMinor,
        ));

        return new SettlementSuggestions(
            justeMinor: $finalTotalMinor,
            procheMinor: $proche,
        );
    }

    /**
     * Smallest multiple of $denom that is >= $value. Both inputs must be
     * non-negative integers. For $value == 0, returns 0 (no round-up needed).
     */
    private function ceilToMultiple(int $value, int $denom): int
    {
        if ($denom <= 0) {
            throw new InvalidArgumentException('denom must be > 0');
        }

        return intdiv($value + $denom - 1, $denom) * $denom;
    }
}
