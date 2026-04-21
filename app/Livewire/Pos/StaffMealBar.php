<?php

namespace App\Livewire\Pos;

use App\Domains\Pos\Tables\TableUiStatus;
use App\Services\Pos\TableDashboardQueryService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/**
 * Staff meal (賄い) tables 100–104: compact footer buttons, outside the customer floor grid.
 */
class StaffMealBar extends Component
{
    #[Locked]
    public int $shopId = 0;

    #[Reactive]
    public bool $staffDoorOpen = false;

    /**
     * When true, render as a compact strip beside Change Table / Logout (same footer band).
     */
    public bool $inlineInFooter = false;

    public bool $isPollingPaused = false;

    /**
     * @var list<array<string, mixed>>
     */
    public array $staffTiles = [];

    public function mount(int $shopId, bool $inlineInFooter = false): void
    {
        $this->shopId = $shopId;
        $this->inlineInFooter = $inlineInFooter;
        if ($this->shopId > 0) {
            $this->loadStaffTiles();
        }
    }

    #[On('pos-tile-interaction-started')]
    public function onTileInteractionStarted(): void
    {
        $this->isPollingPaused = true;
    }

    #[On('pos-tile-interaction-ended')]
    public function onTileInteractionEnded(): void
    {
        $this->isPollingPaused = false;
    }

    #[On('pos-refresh-tiles')]
    public function onRefreshTiles(): void
    {
        if ($this->shopId > 0) {
            $this->loadStaffTiles();
        }
    }

    public function loadStaffTiles(): void
    {
        if ($this->shopId === 0) {
            $this->staffTiles = [];

            return;
        }
        $data = app(TableDashboardQueryService::class)->getDashboardData($this->shopId);
        $out = [];
        foreach ($data->tiles as $tile) {
            $a = $tile->toArray();
            $id = (int) ($a['restaurantTableId'] ?? 0);
            if ($id >= 100 && $id <= 104) {
                $out[] = $a;
            }
        }
        usort($out, static fn (array $x, array $y): int => ($x['restaurantTableId'] ?? 0) <=> ($y['restaurantTableId'] ?? 0));
        $this->staffTiles = array_slice($out, 0, 5);
    }

    /**
     * ハートで開く / スタッフ卓に白以外（待機中以外）の状態があれば常に表示。
     */
    #[Computed]
    public function showStaffMealBar(): bool
    {
        if ($this->shopId <= 0) {
            return false;
        }
        if ($this->staffDoorOpen) {
            return true;
        }
        foreach ($this->staffTiles as $t) {
            if ((string) ($t['uiStatus'] ?? TableUiStatus::Free->value) !== TableUiStatus::Free->value) {
                return true;
            }
        }

        return false;
    }

    public function openTableContext(int $tableId, mixed $sessionId = null): void
    {
        $sid = is_numeric($sessionId) ? (int) $sessionId : null;
        if ($sid !== null && $sid < 1) {
            $sid = null;
        }
        $this->dispatch('pos-tile-interaction-started');
        $this->dispatch('pos-action-host-opened', tableId: $tableId, sessionId: $sid);
        $this->dispatch('pos-table-selection-sync', tableId: $tableId);
    }

    /**
     * @param  array<string, mixed>  $t
     */
    public function tileSurfaceClasses(array $t): string
    {
        $status = (string) ($t['uiStatus'] ?? TableUiStatus::Free->value);

        return match ($status) {
            TableUiStatus::Alert->value => 'bg-red-600 text-white border-red-800 dark:bg-red-500 dark:border-red-300',
            TableUiStatus::Pending->value => 'bg-red-600 text-white border-red-800 dark:bg-red-500 dark:border-red-300',
            TableUiStatus::Active->value => 'bg-blue-50 text-gray-950 border-blue-300 dark:bg-blue-900/40 dark:text-blue-50 dark:border-blue-500',
            TableUiStatus::Billed->value => 'bg-yellow-300 text-yellow-950 border-yellow-700 dark:bg-yellow-400 dark:text-yellow-950 dark:border-yellow-200',

            default => 'bg-white text-gray-950 border-gray-300 dark:bg-gray-800 dark:text-white dark:border-gray-600',
        };
    }

    public function render()
    {
        return view('livewire.pos.staff-meal-bar');
    }
}
