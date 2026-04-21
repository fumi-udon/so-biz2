<?php

namespace Tests\Unit\Pos;

use App\Domains\Pos\Tables\TableCategory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TableCategoryTest extends TestCase
{
    #[Test]
    public function it_resolves_known_ranges(): void
    {
        $this->assertSame(TableCategory::Customer, TableCategory::tryResolveFromId(15));
        $this->assertSame(TableCategory::Staff, TableCategory::tryResolveFromId(101));
        $this->assertSame(TableCategory::Takeaway, TableCategory::tryResolveFromId(201));
    }

    #[Test]
    public function it_returns_null_for_unknown_range(): void
    {
        $this->assertNull(TableCategory::tryResolveFromId(30));
    }

    #[Test]
    public function it_throws_for_unknown_range_on_or_fail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TableCategory::resolveFromIdOrFail(999);
    }
}
