<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Actions\Pos\AddPosOrderFromStaffAction;
use App\Actions\RadTable\RecuPlacedOrdersForSessionAction;
use App\Enums\OrderStatus;
use App\Enums\TableSessionManagementSource;
use App\Exceptions\Pos\SessionManagedByPos2Exception;
use App\Services\Pos\TableSessionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsPosDashboardFixtures;
use Tests\TestCase;

final class TableSessionPos2LegacyIsolationTest extends TestCase
{
    use BuildsPosDashboardFixtures;
    use RefreshDatabase;

    public function test_legacy_add_order_throws_when_active_session_is_pos2(): void
    {
        $shop = $this->makeShop('p2-iso-block');
        $table = $this->makeCustomerTable($shop, 26);
        $session = $this->openActiveSession($shop, $table);
        $session->forceFill([
            'management_source' => TableSessionManagementSource::Pos2,
        ])->save();

        $this->expectException(SessionManagedByPos2Exception::class);

        app(AddPosOrderFromStaffAction::class)->execute(
            (int) $shop->id,
            (int) $table->id,
            1,
            1,
            null,
            [],
            '',
        );
    }

    public function test_pos2_can_add_order_on_legacy_session(): void
    {
        $shop = $this->makeShop('p2-iso-ok');
        $table = $this->makeCustomerTable($shop, 26);
        $session = $this->openActiveSession($shop, $table);
        $session->forceFill([
            'management_source' => TableSessionManagementSource::Legacy,
        ])->save();

        $seed = $this->placeLinedOrder($shop, $session, 1_000, OrderStatus::Confirmed);
        $menuItemId = (int) $seed['line']->menu_item_id;

        $orderId = app(AddPosOrderFromStaffAction::class)->execute(
            (int) $shop->id,
            (int) $table->id,
            $menuItemId,
            1,
            null,
            [],
            '',
            TableSessionManagementSource::Pos2,
        );

        $this->assertGreaterThan(0, $orderId);
    }

    public function test_legacy_recu_throws_on_pos2_session(): void
    {
        $shop = $this->makeShop('p2-recu-block');
        $table = $this->makeCustomerTable($shop, 26);
        $session = $this->openActiveSession($shop, $table);
        $session->forceFill([
            'management_source' => TableSessionManagementSource::Pos2,
        ])->save();

        $this->expectException(SessionManagedByPos2Exception::class);

        app(RecuPlacedOrdersForSessionAction::class)->execute(
            (int) $shop->id,
            (int) $session->id,
            0,
        );
    }

    public function test_get_or_create_legacy_throws_when_pos2_active(): void
    {
        $shop = $this->makeShop('p2-goc');
        $table = $this->makeCustomerTable($shop, 26);
        $session = $this->openActiveSession($shop, $table);
        $session->forceFill([
            'management_source' => TableSessionManagementSource::Pos2,
        ])->save();

        $this->expectException(SessionManagedByPos2Exception::class);

        app(TableSessionLifecycleService::class)->getOrCreateActiveSession(
            $table,
            TableSessionManagementSource::Legacy,
        );
    }
}
