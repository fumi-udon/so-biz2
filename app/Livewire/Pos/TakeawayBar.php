<?php

namespace App\Livewire\Pos;

use App\Actions\Pos\Takeaway\StartTakeawayGuestFlowAction;
use App\Domains\Pos\Tables\TableCategory;
use App\Domains\Pos\Tables\TableUiStatus;
use App\Services\Pos\TableDashboardQueryService;
use Filament\Notifications\Notification;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Takeaway 専用タイル帯。表示は canonical slot 200–205 のみ（データ上は 219 まであり得る）。
 */
class TakeawayBar extends Component
{
    #[Locked]
    public int $shopId = 0;

    /**
     * @var list<array<string, mixed>>
     */
    public array $takeawayTiles = [];

    public bool $modalOpen = false;

    public ?int $selectedTableId = null;

    public string $customerName = '';

    public string $customerPhone = '';

    public bool $isPollingPaused = false;

    /** 右ペインで開いている Takeaway 卓（リング表示用）。モーダル有無に関わらず維持する。 */
    public ?int $floorSelectedTakeawayTableId = null;

    public function mount(int $shopId): void
    {
        $this->shopId = $shopId;
        if ($this->shopId > 0) {
            $this->loadTakeawayTiles();
        }
    }

    #[On('pos-tile-interaction-ended')]
    public function onTileInteractionEnded(): void
    {
        $this->isPollingPaused = false;
        $this->floorSelectedTakeawayTableId = null;
    }

    /**
     * 他コンポーネントから卓ホストが開いたときも、Takeaway なら床の選択を同期する。
     */
    #[On('pos-action-host-opened')]
    public function onPosActionHostOpened(mixed $tableId = null, mixed $sessionId = null): void
    {
        $tid = is_numeric($tableId) ? (int) $tableId : 0;
        if ($tid < 1 || TableCategory::tryResolveFromId($tid) !== TableCategory::Takeaway) {
            $this->floorSelectedTakeawayTableId = null;

            return;
        }
        $this->floorSelectedTakeawayTableId = $tid;
    }

    #[On('pos-refresh-tiles')]
    public function onRefreshTiles(): void
    {
        if ($this->shopId > 0) {
            $this->loadTakeawayTiles();
        }
    }

    /**
     * 通常卓／賄いから別卓が選ばれたとき、テイクアウトのゲスト入力 UI だけを閉じる（pos-tile-interaction-ended は出さない）。
     */
    #[On('pos-takeaway-bar-clear-ui')]
    public function onTakeawayBarClearUi(): void
    {
        if (! $this->modalOpen) {
            return;
        }
        $this->modalOpen = false;
        $this->selectedTableId = null;
        $this->customerName = '';
        $this->customerPhone = '';
        $this->floorSelectedTakeawayTableId = null;
    }

    public function loadTakeawayTiles(): void
    {
        if ($this->shopId === 0) {
            $this->takeawayTiles = [];

            return;
        }
        $data = app(TableDashboardQueryService::class)->getDashboardData($this->shopId);
        $out = [];
        foreach ($data->tiles as $tile) {
            $a = $tile->toArray();
            $id = (int) ($a['restaurantTableId'] ?? 0);
            if (TableCategory::tryResolveFromId($id) !== TableCategory::Takeaway) {
                continue;
            }
            $slot = TableCategory::canonicalSlot($id);
            // UI は 200–205 の 6 卓のみ（DB / Action は 200–219 まである）。
            if ($slot < 200 || $slot > 205) {
                continue;
            }
            $out[] = $a;
        }
        usort($out, static fn (array $x, array $y): int => ($x['restaurantTableId'] ?? 0) <=> ($y['restaurantTableId'] ?? 0));
        $this->takeawayTiles = $out;
    }

    public function openModalForTable(int $tableId): void
    {
        if (TableCategory::tryResolveFromId($tableId) !== TableCategory::Takeaway) {
            return;
        }

        $this->dispatch('pos-customer-grid-clear-selection');

        $tile = $this->findTakeawayTile($tableId);
        if ($tile === null) {
            $this->loadTakeawayTiles();
            $tile = $this->findTakeawayTile($tableId);
        }
        if ($tile === null) {
            return;
        }

        $uiStatus = (string) ($tile['uiStatus'] ?? TableUiStatus::Free->value);
        $rawSid = $tile['activeTableSessionId'] ?? null;
        $sessionId = is_numeric($rawSid) ? (int) $rawSid : null;
        if ($sessionId !== null && $sessionId < 1) {
            $sessionId = null;
        }

        $isFree = $uiStatus === TableUiStatus::Free->value;
        $hasActiveSession = $sessionId !== null;

        if (! $hasActiveSession && $isFree) {
            $this->isPollingPaused = true;
            $this->dispatch('pos-tile-interaction-started');
            $this->floorSelectedTakeawayTableId = $tableId;
            $this->selectedTableId = $tableId;
            $this->customerName = '';
            $this->customerPhone = '';
            $this->modalOpen = true;

            return;
        }

        $this->floorSelectedTakeawayTableId = $tableId;
        $this->isPollingPaused = true;
        $this->dispatch('pos-tile-interaction-started');
        $this->dispatch('pos-action-host-opened', tableId: $tableId, sessionId: $sessionId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findTakeawayTile(int $tableId): ?array
    {
        foreach ($this->takeawayTiles as $t) {
            if ((int) ($t['restaurantTableId'] ?? 0) === $tableId) {
                return $t;
            }
        }

        return null;
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->selectedTableId = null;
        $this->customerName = '';
        $this->customerPhone = '';
        $this->floorSelectedTakeawayTableId = null;
        $this->dispatch('pos-tile-interaction-ended');
    }

    public function confirmTakeawayGuest(): void
    {
        $this->validate([
            'customerName' => 'required|string|max:120',
            'customerPhone' => 'required|string|max:40',
        ], [], [
            'customerName' => __('pos.takeaway_customer_name'),
            'customerPhone' => __('pos.takeaway_customer_phone'),
        ]);

        if ($this->shopId === 0 || $this->selectedTableId === null) {
            return;
        }

        try {
            $url = app(StartTakeawayGuestFlowAction::class)->execute(
                $this->shopId,
                $this->selectedTableId,
                $this->customerName,
                $this->customerPhone,
            );
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('pos.action_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->modalOpen = false;
        $this->selectedTableId = null;
        $this->customerName = '';
        $this->customerPhone = '';
        $this->floorSelectedTakeawayTableId = null;
        $this->loadTakeawayTiles();
        $this->dispatch('pos-refresh-tiles');
        $this->dispatch('pos-tile-interaction-ended');

        $this->js('window.open('.json_encode($url).', "_blank", "noopener,noreferrer")');
    }

    /**
     * @param  array<string, mixed>  $t
     */
    public function tileSurfaceClasses(array $t): string
    {
        $status = (string) ($t['uiStatus'] ?? TableUiStatus::Free->value);

        return match ($status) {
            TableUiStatus::Alert->value => 'bg-red-600 text-white border-2 border-red-800 animate-pulse dark:bg-red-500 dark:border-red-300',
            TableUiStatus::Pending->value => 'bg-red-600 text-white border-2 border-red-800 dark:bg-red-500 dark:text-white dark:border-red-300',
            TableUiStatus::Active->value => 'bg-sky-400 text-sky-950 border-2 border-sky-600 shadow-sm dark:bg-sky-500 dark:text-sky-950 dark:border-sky-300',
            TableUiStatus::Billed->value => 'bg-sky-200 text-sky-950 border-2 border-sky-600 dark:bg-sky-700 dark:text-sky-50 dark:border-sky-400',

            default => 'bg-yellow-50 text-yellow-950 border border-yellow-300 dark:bg-yellow-900/30 dark:text-yellow-100 dark:border-yellow-600',
        };
    }

    /**
     * @param  array<string, mixed>  $tile
     */
    public function tileLabel(array $tile): string
    {
        $cn = $tile['activeSessionCustomerName'] ?? null;
        if (is_string($cn) && trim($cn) !== '') {
            return trim($cn);
        }

        return '#'.(int) ($tile['restaurantTableId'] ?? 0);
    }

    public function render()
    {
        return view('livewire.pos.takeaway-bar');
    }
}
