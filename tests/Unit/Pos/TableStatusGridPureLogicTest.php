<?php

namespace Tests\Unit\Pos;

use App\Domains\Pos\Tables\TableCategory;
use App\Domains\Pos\Tables\TableUiStatus;
use App\Livewire\Pos\TableStatusGrid;
use PHPUnit\Framework\TestCase;

/**
 * Phase 2: TableStatusGrid の UI 分割ロジックと色マッピングを、
 * Livewire/DB に依存せず純粋関数として検証する。
 */
class TableStatusGridPureLogicTest extends TestCase
{
    private function tile(int $id, TableCategory $cat, TableUiStatus $status): array
    {
        return [
            'restaurantTableId' => $id,
            'restaurantTableName' => 'T'.$id,
            'activeSessionStaffName' => null,
            'activeTableSessionId' => $status === TableUiStatus::Free ? null : $id * 10,
            'unackedPlacedPosOrderCount' => 0,
            'unackedPlacedLineExists' => false,
            'oldestRelevantPlacedAt' => null,
            'additionOrCheckoutSignalActive' => false,
            'relevantPosOrderCount' => 0,
            'sessionTotalMinor' => 0,
            'category' => $cat->value,
            'uiStatus' => $status->value,
            'lastAdditionPrintedAt' => null,
            'hasOrderAfterAdditionPrinted' => false,
        ];
    }

    public function test_customer_tiles_always_included_regardless_of_status(): void
    {
        $grid = new TableStatusGrid;
        $grid->tiles = [
            $this->tile(10, TableCategory::Customer, TableUiStatus::Free),
            $this->tile(11, TableCategory::Customer, TableUiStatus::Pending),
            $this->tile(12, TableCategory::Customer, TableUiStatus::Active),
            $this->tile(13, TableCategory::Customer, TableUiStatus::Billed),
            $this->tile(14, TableCategory::Customer, TableUiStatus::Alert),
        ];
        $grouped = $grid->getGroupedTilesProperty();
        $this->assertCount(5, $grouped['customer']);
        $this->assertCount(0, $grouped['takeaway']);
        $this->assertCount(0, $grouped['staff']);
    }

    public function test_takeaway_ids_omitted_from_grouped_view_while_takeout_frozen(): void
    {
        $grid = new TableStatusGrid;
        $grid->tiles = [
            $this->tile(200, TableCategory::Takeaway, TableUiStatus::Free),
            $this->tile(201, TableCategory::Takeaway, TableUiStatus::Pending),
            $this->tile(100, TableCategory::Staff, TableUiStatus::Free),
            $this->tile(101, TableCategory::Staff, TableUiStatus::Active),
        ];
        $grouped = $grid->getGroupedTilesProperty();
        $this->assertCount(0, $grouped['customer']);
        $this->assertCount(0, $grouped['takeaway']);
        $this->assertCount(2, $grouped['staff']);
        $this->assertSame([100, 101], array_column($grouped['staff'], 'restaurantTableId'));
    }

    public function test_unknown_category_is_silently_dropped(): void
    {
        $grid = new TableStatusGrid;
        $grid->tiles = [
            array_merge($this->tile(1, TableCategory::Customer, TableUiStatus::Free), [
                'category' => null,
            ]),
            $this->tile(10, TableCategory::Customer, TableUiStatus::Free),
        ];
        $grouped = $grid->getGroupedTilesProperty();
        $this->assertCount(1, $grouped['customer']);
        $this->assertSame(10, $grouped['customer'][0]['restaurantTableId']);
    }

    public function test_buckets_follow_table_id_ranges_not_category_strings(): void
    {
        $grid = new TableStatusGrid;
        $grid->tiles = [
            array_merge($this->tile(16, TableCategory::Customer, TableUiStatus::Free), [
                'category' => TableCategory::Staff->value,
            ]),
            array_merge($this->tile(100, TableCategory::Staff, TableUiStatus::Free), [
                'category' => TableCategory::Customer->value,
            ]),
        ];
        $grouped = $grid->getGroupedTilesProperty();
        $this->assertCount(1, $grouped['customer']);
        $this->assertSame(16, $grouped['customer'][0]['restaurantTableId']);
        $this->assertCount(1, $grouped['staff']);
        $this->assertSame(100, $grouped['staff'][0]['restaurantTableId']);
    }

    public function test_staff_bucket_only_includes_table_ids_100_through_104(): void
    {
        $grid = new TableStatusGrid;
        $grid->tiles = [
            $this->tile(104, TableCategory::Staff, TableUiStatus::Free),
            $this->tile(105, TableCategory::Staff, TableUiStatus::Free),
        ];
        $grouped = $grid->getGroupedTilesProperty();
        $this->assertSame([104], array_column($grouped['staff'], 'restaurantTableId'));
    }

    public function test_surface_classes_map_each_ui_status_to_expected_palette(): void
    {
        $grid = new TableStatusGrid;

        $free = $grid->tileSurfaceClasses($this->tile(10, TableCategory::Customer, TableUiStatus::Free));
        $this->assertStringContainsString('bg-white', $free);

        $pending = $grid->tileSurfaceClasses($this->tile(11, TableCategory::Customer, TableUiStatus::Pending));
        $this->assertStringContainsString('bg-red-600', $pending);

        $active = $grid->tileSurfaceClasses($this->tile(12, TableCategory::Customer, TableUiStatus::Active));
        $this->assertStringContainsString('bg-sky-400', $active);

        $billed = $grid->tileSurfaceClasses($this->tile(13, TableCategory::Customer, TableUiStatus::Billed));
        $this->assertStringContainsString('bg-yellow-300', $billed);

        $alert = $grid->tileSurfaceClasses($this->tile(14, TableCategory::Customer, TableUiStatus::Alert));
        $this->assertStringContainsString('bg-red-600', $alert);
        $this->assertStringContainsString('animate-pulse', $alert);
        $this->assertStringContainsString('text-white', $alert);
    }
}
