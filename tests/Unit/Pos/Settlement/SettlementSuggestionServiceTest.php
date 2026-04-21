<?php

namespace Tests\Unit\Pos\Settlement;

use App\Domains\Pos\Settlement\SettlementSuggestionService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SettlementSuggestionServiceTest extends TestCase
{
    private SettlementSuggestionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SettlementSuggestionService;
    }

    public function test_bill_of_34_5_tnd_returns_35_40_50_proche(): void
    {
        $result = $this->service->suggest(34_500);

        $this->assertSame(34_500, $result->justeMinor);
        $this->assertSame([35_000, 40_000, 50_000], $result->procheMinor);
    }

    public function test_bill_of_exactly_20_tnd_does_not_duplicate_juste_in_proche(): void
    {
        $result = $this->service->suggest(20_000);

        $this->assertSame(20_000, $result->justeMinor);
        $this->assertNotContains(20_000, $result->procheMinor);
        $this->assertSame([50_000], $result->procheMinor);
    }

    public function test_bill_of_exactly_50_tnd_still_yields_60_from_20_denom(): void
    {
        // 50 TND is a multiple of 5, 10 and 50 but NOT of 20 (needs 3*20=60).
        // So the 20 TND denomination still generates a strictly-greater candidate.
        $result = $this->service->suggest(50_000);

        $this->assertSame(50_000, $result->justeMinor);
        $this->assertSame([60_000], $result->procheMinor);
    }

    public function test_bill_of_exactly_5_tnd_returns_10_20_50_proche(): void
    {
        $result = $this->service->suggest(5_000);

        $this->assertSame(5_000, $result->justeMinor);
        $this->assertSame([10_000, 20_000, 50_000], $result->procheMinor);
    }

    public function test_bill_of_12_3_tnd_returns_15_20_40_50_proche(): void
    {
        // 12.3 TND -> 15 (ceil/5), 20 (ceil/10), 20 (ceil/20 -> 2*20=... actually 12.3/20=1 → 20), 50
        // But unique ascending → [15, 20, 50]  (because ceil(12_300/20_000)=1 → 20_000 already in set)
        $result = $this->service->suggest(12_300);

        $this->assertSame(12_300, $result->justeMinor);
        $this->assertSame([15_000, 20_000, 50_000], $result->procheMinor);
    }

    public function test_bill_of_99_9_tnd_converges_all_denominations_to_100(): void
    {
        // 99.9 TND: all of ceil(99900/{5k,10k,20k,50k}) produce 100000.
        // Since our algorithm emits one ceil per denomination, they collapse to
        // a single candidate. This is correct: a customer with 99.9 TND to pay
        // would hand over a 100 TND note. Further multiples (120, 150) are not
        // in the natural "smallest convenient note" set this service models.
        $result = $this->service->suggest(99_900);

        $this->assertSame(99_900, $result->justeMinor);
        $this->assertSame([100_000], $result->procheMinor);
    }

    public function test_bill_of_zero_has_empty_proche(): void
    {
        // A zero-total session (theoretical: opened and cancelled) has nothing
        // to tender against. We do not suggest phantom denominations.
        $result = $this->service->suggest(0);

        $this->assertSame(0, $result->justeMinor);
        $this->assertSame([], $result->procheMinor);
    }

    public function test_bill_larger_than_all_denominations_climbs_up(): void
    {
        // 63 TND → 65 (5), 70 (10), 80 (20), 100 (50)
        $result = $this->service->suggest(63_000);

        $this->assertSame(63_000, $result->justeMinor);
        $this->assertSame([65_000, 70_000, 80_000, 100_000], $result->procheMinor);
    }

    public function test_bill_of_0_1_tnd_returns_5_10_20_50_proche(): void
    {
        $result = $this->service->suggest(100);

        $this->assertSame(100, $result->justeMinor);
        $this->assertSame([5_000, 10_000, 20_000, 50_000], $result->procheMinor);
    }

    public function test_proche_is_strictly_ascending_without_duplicates(): void
    {
        foreach ([100, 1_000, 4_999, 5_000, 5_100, 10_000, 19_999, 20_000, 34_500, 49_999, 50_000, 50_100, 99_900, 123_456] as $v) {
            $result = $this->service->suggest($v);

            $prev = null;
            foreach ($result->procheMinor as $p) {
                $this->assertIsInt($p);
                $this->assertGreaterThan($v, $p, "proche must be strictly greater than juste ({$v})");
                if ($prev !== null) {
                    $this->assertGreaterThan($prev, $p, 'proche must strictly ascend');
                }
                $prev = $p;
            }
        }
    }

    public function test_negative_bill_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->suggest(-1);
    }

    public function test_rounded_amounts_always_multiples_of_100_minor_when_input_is(): void
    {
        // PricingEngine floor-rounds to 100 minor, so inputs end in 00.
        // Check that suggestions remain multiples of 100 minor (actually 5000 by construction).
        foreach ([100, 2_500, 5_000, 12_300, 34_500, 99_900] as $v) {
            $result = $this->service->suggest($v);
            foreach ($result->procheMinor as $p) {
                $this->assertSame(0, $p % 5_000, 'proche values must be multiples of 5000 minor');
            }
        }
    }
}
