<?php

declare(strict_types=1);

namespace Tests\Feature\Pos2;

use App\Enums\TableSessionManagementSource;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsPosDashboardFixtures;
use Tests\TestCase;

final class Pos2TableMoveTest extends TestCase
{
    use BuildsPosDashboardFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_move_table_updates_session_table_and_revision(): void
    {
        $shop = $this->makeShop('pos2-move-ok');
        $tA = $this->makeCustomerTable($shop, 16);
        $tB = $this->makeCustomerTable($shop, 17);
        $session = $this->openActiveSession($shop, $tA);
        $session->forceFill([
            'management_source' => TableSessionManagementSource::Pos2,
            'session_revision' => 0,
        ])->save();

        $this->assertSame((int) $tA->id, (int) $session->restaurant_table_id);

        $response = $this->withSession([
            'pos2_authenticated' => true,
            'pos2.active_shop_id' => (int) $shop->id,
        ])->postJson('/pos2/tables/move', [
            'source_table_session_id' => (int) $session->id,
            'dest_table_id' => (int) $tB->id,
            'expected_session_revision' => 0,
        ]);

        $response->assertOk();
        $response->assertJsonPath('restaurant_table_id', (int) $tB->id);
        $response->assertJsonPath('session_revision', 1);

        $session->refresh();
        $this->assertSame((int) $tB->id, (int) $session->restaurant_table_id);
        $this->assertSame(1, (int) $session->session_revision);
    }

    public function test_move_table_returns_409_on_revision_mismatch(): void
    {
        $shop = $this->makeShop('pos2-move-rev');
        $tA = $this->makeCustomerTable($shop, 18);
        $tB = $this->makeCustomerTable($shop, 19);
        $session = $this->openActiveSession($shop, $tA);
        $session->forceFill([
            'session_revision' => 2,
        ])->save();

        $response = $this->withSession([
            'pos2_authenticated' => true,
            'pos2.active_shop_id' => (int) $shop->id,
        ])->postJson('/pos2/tables/move', [
            'source_table_session_id' => (int) $session->id,
            'dest_table_id' => (int) $tB->id,
            'expected_session_revision' => 0,
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('code', 'REVISION_CONFLICT');
    }

    public function test_move_table_returns_409_when_destination_occupied(): void
    {
        $shop = $this->makeShop('pos2-move-occ');
        $tA = $this->makeCustomerTable($shop, 20);
        $tB = $this->makeCustomerTable($shop, 21);
        $sessionA = $this->openActiveSession($shop, $tA);
        $sessionB = $this->openActiveSession($shop, $tB);
        $sessionA->forceFill(['session_revision' => 0])->save();
        $sessionB->forceFill(['session_revision' => 0])->save();

        $response = $this->withSession([
            'pos2_authenticated' => true,
            'pos2.active_shop_id' => (int) $shop->id,
        ])->postJson('/pos2/tables/move', [
            'source_table_session_id' => (int) $sessionA->id,
            'dest_table_id' => (int) $tB->id,
            'expected_session_revision' => 0,
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('code', 'TABLE_OCCUPIED');
    }

    public function test_move_table_legacy_session_allowed_from_pos2_api(): void
    {
        $shop = $this->makeShop('pos2-move-legacy');
        $tA = $this->makeCustomerTable($shop, 22);
        $tB = $this->makeCustomerTable($shop, 23);
        $session = $this->openActiveSession($shop, $tA);
        $session->forceFill([
            'management_source' => TableSessionManagementSource::Legacy,
            'session_revision' => 0,
        ])->save();

        $response = $this->withSession([
            'pos2_authenticated' => true,
            'pos2.active_shop_id' => (int) $shop->id,
        ])->postJson('/pos2/tables/move', [
            'source_table_session_id' => (int) $session->id,
            'dest_table_id' => (int) $tB->id,
            'expected_session_revision' => 0,
        ]);

        $response->assertOk();
        $session->refresh();
        $this->assertSame((int) $tB->id, (int) $session->restaurant_table_id);
    }
}
