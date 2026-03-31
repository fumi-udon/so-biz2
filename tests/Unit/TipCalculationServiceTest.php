<?php

namespace Tests\Unit;

use App\Services\TipCalculationService;
use PHPUnit\Framework\TestCase;

class TipCalculationServiceTest extends TestCase
{
    public function test_normalize_weight_scalar_preserves_plain_numeric(): void
    {
        $this->assertSame(100.0, TipCalculationService::normalizeWeightScalar(100));
        $this->assertSame(100.0, TipCalculationService::normalizeWeightScalar(100.0));
        $this->assertSame(100.0, TipCalculationService::normalizeWeightScalar('100.000'));
    }

    public function test_normalize_weight_scalar_unwraps_single_element_arrays(): void
    {
        $this->assertSame(100.0, TipCalculationService::normalizeWeightScalar([100]));
        $this->assertSame(100.0, TipCalculationService::normalizeWeightScalar([100.0]));
        $this->assertSame(100.0, TipCalculationService::normalizeWeightScalar(['100.000']));
    }

    public function test_normalize_weight_scalar_strips_thousands_separators_in_strings(): void
    {
        $this->assertSame(1000.5, TipCalculationService::normalizeWeightScalar('1,000.500'));
    }

    public function test_normalize_weight_scalar_empty_array_is_zero(): void
    {
        $this->assertSame(0.0, TipCalculationService::normalizeWeightScalar([]));
    }
}
