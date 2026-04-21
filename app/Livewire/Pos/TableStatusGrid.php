<?php

namespace App\Livewire\Pos;

use App\Domains\Pos\Tables\TableUiStatus;
use App\Services\Pos\TableDashboardQueryService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class TableStatusGrid extends Component
{
    #[Locked]
    public int $shopId = 0;

    /**
     * Snapshot-safe rows from {@see TableTileAggregate::toArray()} (one entry per table).
     *
     * @var list<array<string, mixed>>
     */
    public array $tiles = [];

    public bool $isPollingPaused = false;

    public ?int $selectedTableId = null;

    public function mount(int $shopId): void
    {
        $this->shopId = $shopId;
        if ($this->shopId > 0) {
            $this->loadTiles();
        }
    }

    public function takeoutPlus(): void
    {
        // Takeout UI 凍結中 — 成功/案内トーストは出さない（ボタンローディング等のみ）
    }

    public function loadTiles(): void
    {
        if ($this->shopId === 0) {
            $this->tiles = [];

            return;
        }
        $data = app(TableDashboardQueryService::class)->getDashboardData($this->shopId);
        $this->tiles = array_map(
            static fn ($tile) => $tile->toArray(),
            $data->tiles
        );
    }

    public function openTableContext(int $tableId, mixed $sessionId = null): void
    {
        $sid = is_numeric($sessionId) ? (int) $sessionId : null;
        if ($sid !== null && $sid < 1) {
            $sid = null;
        }
        $this->selectedTableId = $tableId;
        $this->dispatch('pos-tile-interaction-started');
        $this->dispatch('pos-action-host-opened', tableId: $tableId, sessionId: $sid);
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
        $this->selectedTableId = null;
    }

    /**
     * Staff meal bar (sibling Livewire) opens a table; mirror selection ring on customer grid.
     */
    #[On('pos-table-selection-sync')]
    public function onTableSelectionSync(int $tableId): void
    {
        $this->selectedTableId = $tableId;
    }

    #[On('pos-refresh-tiles')]
    public function onRefreshTiles(): void
    {
        if ($this->shopId > 0) {
            $this->loadTiles();
        }
    }

    public function render()
    {
        return view('livewire.pos.table-status-grid');
    }

    /**
     * Customer（常時） / Staff（ドア開時）の分割。Takeout（200–219）は UI 凍結のためビューに渡さない。
     *
     * @return array{customer: list<array<string, mixed>>, takeaway: list<array<string, mixed>>, staff: list<array<string, mixed>>}
     */
    public function getGroupedTilesProperty(): array
    {
        $customer = [];
        $staff = [];

        foreach ($this->tiles as $t) {
            $tableId = (int) ($t['restaurantTableId'] ?? 0);
            // Bucket strictly by pinned ID ranges (contract: 10–29 customer, 100–104 staff meal, 200–219 takeaway).
            // Do not trust string `category` alone — mis-serialization must not route e.g. id 16 into staff.
            if ($tableId >= 10 && $tableId <= 29) {
                $customer[] = $t;

                continue;
            }
            if ($tableId >= 30 && $tableId <= 39) {
                $customer[] = $t;

                continue;
            }
            if ($tableId >= 100 && $tableId <= 104) {
                $staff[] = $t;

                continue;
            }
            if ($tableId >= 200 && $tableId <= 219) {
                // Takeout floor UI frozen — data may still exist in $this->tiles / DB; do not render.
                continue;
            }
        }

        return [
            'customer' => $customer,
            'takeaway' => [],
            'staff' => $staff,
        ];
    }

    /**
     * Phase 2: TableUiStatus → Tailwind クラスへの純粋マッピング。
     * UI 層は Enum 値を読むだけ（state less）。
     *
     * @param  array<string, mixed>  $t
     */
    public function tileSurfaceClasses(array $t): string
    {
        $status = (string) ($t['uiStatus'] ?? TableUiStatus::Free->value);

        return match ($status) {
            TableUiStatus::Alert->value => 'bg-red-600 text-white border-2 border-red-800 animate-pulse dark:bg-red-500 dark:border-red-300',
            TableUiStatus::Pending->value => 'bg-red-600 text-white border-2 border-red-800 dark:bg-red-500 dark:text-white dark:border-red-300',
            TableUiStatus::Active->value => 'bg-blue-50 text-gray-950 border border-blue-300 dark:bg-blue-900/40 dark:text-blue-50 dark:border-blue-500',
            TableUiStatus::Billed->value => 'bg-yellow-300 text-yellow-950 border-2 border-yellow-700 dark:bg-yellow-400 dark:text-yellow-950 dark:border-yellow-200',

            default => 'bg-white text-gray-950 border border-gray-300 dark:bg-gray-800 dark:text-white dark:border-gray-600',
        };
    }

    /**
     * @param  array<string, mixed>  $t
     */
    public function formatOldestPlacedAgo(array $t): string
    {
        $iso = $t['oldestRelevantPlacedAt'] ?? null;
        if ($iso === null || $iso === '') {
            return '';
        }

        return Carbon::parse((string) $iso)->diffForHumans();
    }
}
