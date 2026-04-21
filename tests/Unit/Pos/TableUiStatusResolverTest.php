<?php

namespace Tests\Unit\Pos;

use App\Domains\Pos\Tables\TableUiStatus;
use App\Domains\Pos\Tables\TableUiStatusInput;
use App\Domains\Pos\Tables\TableUiStatusResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TableUiStatusResolverTest extends TestCase
{
    #[Test]
    public function it_returns_free_when_no_active_session(): void
    {
        $resolver = new TableUiStatusResolver;
        $status = $resolver->resolve(new TableUiStatusInput(
            hasActiveSession: false,
            hasUnackedPlaced: false,
            lastAdditionPrintedAt: null,
            hasOrdersAfterLastAdditionPrintedAt: false,
        ));

        $this->assertSame(TableUiStatus::Free, $status);
    }

    #[Test]
    public function it_returns_pending_when_active_and_unacked(): void
    {
        $resolver = new TableUiStatusResolver;
        $status = $resolver->resolve(new TableUiStatusInput(
            hasActiveSession: true,
            hasUnackedPlaced: true,
            lastAdditionPrintedAt: null,
            hasOrdersAfterLastAdditionPrintedAt: false,
        ));

        $this->assertSame(TableUiStatus::Pending, $status);
    }

    #[Test]
    public function it_returns_billed_when_printed_and_no_new_orders(): void
    {
        $resolver = new TableUiStatusResolver;
        $status = $resolver->resolve(new TableUiStatusInput(
            hasActiveSession: true,
            hasUnackedPlaced: false,
            lastAdditionPrintedAt: now(),
            hasOrdersAfterLastAdditionPrintedAt: false,
        ));

        $this->assertSame(TableUiStatus::Billed, $status);
    }

    #[Test]
    public function it_returns_alert_only_when_new_orders_after_print_are_still_unacked(): void
    {
        $resolver = new TableUiStatusResolver;
        $status = $resolver->resolve(new TableUiStatusInput(
            hasActiveSession: true,
            hasUnackedPlaced: true,
            lastAdditionPrintedAt: now(),
            hasOrdersAfterLastAdditionPrintedAt: true,
        ));

        $this->assertSame(TableUiStatus::Alert, $status);
    }

    #[Test]
    public function it_returns_active_when_post_print_new_orders_are_already_acknowledged(): void
    {
        $resolver = new TableUiStatusResolver;
        $status = $resolver->resolve(new TableUiStatusInput(
            hasActiveSession: true,
            hasUnackedPlaced: false,
            lastAdditionPrintedAt: now(),
            hasOrdersAfterLastAdditionPrintedAt: true,
        ));

        $this->assertSame(TableUiStatus::Active, $status);
    }
}
