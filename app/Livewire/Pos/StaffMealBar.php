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

    /**
     * @param  array<string, mixed>  $t
     */
    public function tileSurfaceClasses(array $t): string
    {
        $status = (string) ($t['uiStatus'] ?? TableUiStatus::Free->value);

        return match ($status) {
            TableUiStatus::Alert->value => 'bg-red-600 text-white border-red-800 dark:bg-red-500 dark:border-red-300',
            TableUiStatus::Pending->value => 'bg-red-600 text-white border-red-800 dark:bg-red-500 dark:border-red-300',
            TableUiStatus::Active->value => 'bg-sky-400 text-sky-950 border-sky-600 shadow-sm dark:bg-sky-500 dark:text-sky-950 dark:border-sky-300',
            TableUiStatus::Billed->value => 'bg-sky-200 text-sky-950 border-sky-600 dark:bg-sky-700 dark:text-sky-50 dark:border-sky-400',

            default => 'bg-white text-gray-950 border-gray-300 dark:bg-gray-800 dark:text-white dark:border-gray-600',
        };
    }

    public function render()
    {
        return view('livewire.pos.staff-meal-bar');
    }
}
