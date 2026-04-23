<?php

namespace Tests\Feature\Livewire\Pos;

use App\Enums\OrderLineStatus;
use App\Enums\OrderStatus;
use App\Livewire\Pos\TableActionHost;
use App\Models\OrderLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsPosDashboardFixtures;
use Tests\TestCase;

class TableActionHostAddonGroupingTest extends TestCase
{
    use BuildsPosDashboardFixtures;
    use RefreshDatabase;

    public function test_unsent_lines_are_grouped_above_sent_lines(): void
    {
        $shop = $this->makeShop('addon-grouping');
        $table = $this->makeCustomerTable($shop, 10);
        $session = $this->openActiveSession($shop, $table);
        ['line' => $unsent] = $this->placeLinedOrder($shop, $session, 1000, OrderStatus::Placed);
        ['line' => $sent] = $this->placeLinedOrder($shop, $session, 1200, OrderStatus::Confirmed);

        OrderLine::query()->whereKey($unsent->id)->update([
            'snapshot_name' => 'UNSENT ITEM',
            'snapshot_kitchen_name' => 'UNSENT ITEM',
            'status' => OrderLineStatus::Placed,
        ]);
        OrderLine::query()->whereKey($sent->id)->update([
            'snapshot_name' => 'SENT ITEM',
            'snapshot_kitchen_name' => 'SENT ITEM',
            'status' => OrderLineStatus::Confirmed,
        ]);

        Livewire::test(TableActionHost::class, ['shopId' => $shop->id])
            ->call('onActionHostOpened', $table->id, $session->id)
            ->assertSee('UNSENT ITEM')
            ->assertSee('SENT ITEM');

        $html = Livewire::test(TableActionHost::class, ['shopId' => $shop->id])
            ->call('onActionHostOpened', $table->id, $session->id)
            ->html();

        $unsentPos = strpos($html, 'UNSENT ITEM');
        self::assertNotFalse($unsentPos);
        $sentItemPos = strpos($html, 'SENT ITEM');
        self::assertNotFalse($sentItemPos);
        self::assertTrue($unsentPos < $sentItemPos, 'Unsent row must render above sent row.');
        self::assertStringContainsString('bg-rose-50', $html);
        self::assertStringContainsString('bg-slate-100', $html);
    }
}
