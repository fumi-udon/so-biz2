<?php

namespace App\Livewire\Pos;

use App\Actions\Pos\AddPosOrderFromStaffAction;
use App\Actions\Pos\DeleteOrderLineWithPolicyAction;
use App\Actions\RadTable\RecuPlacedOrdersForSessionAction;
use App\Domains\Pos\Pricing\PricingEngine;
use App\Domains\Pos\Pricing\PricingInput;
use App\Domains\Pos\Pricing\PricingResult;
use App\Domains\Pos\Tables\TableCategory;
use App\Enums\OrderLineStatus;
use App\Enums\OrderStatus;
use App\Enums\PrintIntent;
use App\Enums\TableSessionStatus;
use App\Exceptions\RevisionConflictException;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OrderLine;
use App\Models\PosOrder;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Services\Pos\TableSessionLifecycleService;
use App\Services\StaffDirectoryService;
use App\Services\StaffPinAuthenticationService;
use App\Support\MenuItemMoney;
use App\Support\Pos\Receipt\PosOrderReceiptLineEnricher;
use App\Support\Pos\Receipt\ReceiptTaxMath;
use App\Support\Pos\StaffTableSettlementPricing;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Js;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

class TableActionHost extends Component
{
    private ?PricingResult $memoSessionPricing = null;

    /** @var array{ht_minor: int, vat_minor: int}|null */
    private ?array $memoStaffMealPreDiscountTaxSum = null;

    #[Locked]
    public int $shopId = 0;

    public ?int $activeRestaurantTableId = null;

    public ?int $activeTableSessionId = null;

    public bool $isOrdersLoaded = false;

    public string $activeRestaurantTableName = '';

    public bool $addModalOpen = false;

    /** @var 'list'|'config' */
    public string $addModalStep = 'list';

    /**
     * カタログ: list<array{ id: int, name: string, items: list<array<string, mixed>> }>
     *
     * @var list<array<string, mixed>>
     */
    public array $addCatalog = [];

    public ?int $addConfigMenuItemId = null;

    public int $addQty = 1;

    public ?string $addStyleId = null;

    /** @var list<string> */
    public array $addToppings = [];

    public string $addNote = '';

    /**
     * UI state machine (technical_contract_v4.md §6).
     *
     * @var 'idle'|'in_flight'|'success'|'failed'|'unknown'
     */
    public string $uiState = 'idle';

    public int $expectedSessionRevision = 0;

    public bool $pendingEchoReload = false;

    public bool $removeAuthPanelOpen = false;

    public ?int $removeAuthLineId = null;

    public ?int $removeApproverStaffId = null;

    public string $removeApproverPin = '';

    /** @var 'open'|'pin'|'cache' */
    public string $removeDecisionMode = 'open';

    /** Staff meal (賄い): Mon Espace 型 PIN — session に staff_name が無いとき必須 */
    public ?int $staffMealAuthStaffId = null;

    public string $staffMealAuthPin = '';

    /**
     * Confirm 成功直後に true。オーバーレイを確実に外し POS 本体を見せる（Livewire の再計算タイミング差の吸収）。
     */
    public bool $staffMealAuthModalDismissed = false;

    public bool $showReceiptPreview = false;

    public string $previewIntent = 'addition';

    public int $previewSessionId = 0;

    public function mount(int $shopId): void
    {
        $this->shopId = $shopId;
        $this->isOrdersLoaded = false;
    }

    /**
     * Drop memoized #[Computed] session / posOrders so the next render hits the DB again.
     */
    private function forgetSessionOrderComputed(): void
    {
        unset($this->session, $this->posOrders);
    }

    #[Computed]
    public function session(): ?TableSession
    {
        if ($this->shopId < 1 || $this->activeTableSessionId === null || $this->activeTableSessionId < 1) {
            return null;
        }

        return TableSession::query()
            ->where('shop_id', $this->shopId)
            ->whereKey($this->activeTableSessionId)
            ->with([
                'restaurantTable' => static fn ($q) => $q->select(['id', 'shop_id', 'name']),
            ])
            ->first();
    }

    /**
     * @return Collection<int, PosOrder>
     */
    #[Computed]
    public function posOrders(): Collection
    {
        if ($this->shopId < 1 || ! $this->isOrdersLoaded) {
            return collect();
        }
        if ($this->activeTableSessionId === null || $this->activeTableSessionId < 1) {
            return collect();
        }
        if ($this->session === null) {
            return collect();
        }

        return PosOrder::query()
            ->where('shop_id', $this->shopId)
            ->where('table_session_id', $this->activeTableSessionId)
            ->where('status', '!=', OrderStatus::Voided)
            ->with([
                'lines' => static fn ($q) => $q->orderBy('id'),
            ])
            ->orderBy('id')
            ->get();
    }

    #[On('pos-action-host-opened')]
    public function onActionHostOpened(mixed $tableId = null, mixed $sessionId = null, mixed $tableName = null): void
    {
        $tid = is_numeric($tableId) ? (int) $tableId : null;
        if ($sessionId === null || $sessionId === 'null' || $sessionId === '') {
            $sid = null;
        } else {
            $sid = is_numeric($sessionId) ? (int) $sessionId : null;
        }
        if ($tid === null || $tid < 1) {
            return;
        }

        $this->staffMealAuthModalDismissed = false;
        $this->staffMealAuthStaffId = null;
        $this->staffMealAuthPin = '';

        $this->activeRestaurantTableId = $tid;
        $incomingTableName = is_string($tableName) ? trim($tableName) : '';
        $this->activeRestaurantTableName = $incomingTableName !== ''
            ? $incomingTableName
            : (string) (RestaurantTable::query()
                ->where('shop_id', $this->shopId)
                ->whereKey($tid)
                ->value('name') ?? '');
        $this->memoSessionPricing = null;
        $this->memoStaffMealPreDiscountTaxSum = null;
        $this->forgetSessionOrderComputed();
        $this->activeTableSessionId = $sid;
        $this->expectedSessionRevision = 0;
        $this->isOrdersLoaded = false;

        // Empty table (no active session): details request is intentionally skipped,
        // so mark load complete here to avoid sticky "working" state.
        if ($sid === null) {
            $this->isOrdersLoaded = true;
            $this->dispatchBrowserPeerSyncForOpenedTable($tid);

            return;
        }

        // 卓にセッションがある場合: リビジョン同期と注文明細を同一 Livewire 往復で完結（ブラウザ発の details 往復を廃止）。
        if ($this->shopId > 0 && $sid !== null && $sid > 0) {
            $this->loadOrderDetails($sid);
        }
        $this->dispatchBrowserPeerSyncForOpenedTable($tid);
        // 賄い卓: セッションは PIN 成功時（confirmStaffMealAuth）まで作らない。ここで ensure しないと Leave 後に空セッションが残りタイルが Active になる。
    }

    /**
     * Sync takeaway/staff floor rings without bundling extra Livewire components:
     * fires browser CustomEvents consumed by Alpine on the POS bars.
     */
    private function dispatchBrowserPeerSyncForOpenedTable(int $tableId): void
    {
        $takeawayTid = TableCategory::tryResolveFromId($tableId) === TableCategory::Takeaway ? $tableId : null;
        $staffTid = TableCategory::tryResolveFromId($tableId) === TableCategory::Staff ? $tableId : null;
        $detail = Js::from([
            'takeawayFloorTid' => $takeawayTid,
            'staffFloorTid' => $staffTid,
        ])->toHtml();
        $this->js(
            'window.dispatchEvent(new CustomEvent("pos-floor-peer-sync", { bubbles: true, detail: '.$detail.' }));'
            ."window.dispatchEvent(new CustomEvent('pos-action-host-opened', { bubbles: true }));"
        );
    }

    private function dispatchBrowserFloorClear(): void
    {
        $this->js(
            'window.dispatchEvent(new CustomEvent("pos-floor-peer-sync", { bubbles: true, detail: { takeawayFloorTid: null, staffFloorTid: null } }));'
        );
    }

    public function loadSessionData(?int $sessionId): void
    {
        $this->loadOrderDetails($sessionId);
    }

    public function loadOrderDetails(?int $sessionId): void
    {
        $this->memoSessionPricing = null;
        $this->memoStaffMealPreDiscountTaxSum = null;
        $this->forgetSessionOrderComputed();
        $this->activeTableSessionId = $sessionId;
        $this->expectedSessionRevision = 0;
        $this->isOrdersLoaded = false;
        if ($this->shopId === 0) {
            $this->isOrdersLoaded = true;

            return;
        }
        if ($sessionId === null || $sessionId < 1) {
            $this->isOrdersLoaded = true;

            return;
        }
        $existing = TableSession::query()
            ->where('shop_id', $this->shopId)
            ->whereKey($sessionId)
            ->first();
        if ($existing === null) {
            $this->isOrdersLoaded = true;

            return;
        }
        $this->expectedSessionRevision = (int) $existing->session_revision;
        $this->isOrdersLoaded = true;
        $this->forgetSessionOrderComputed();
    }

    /**
     * 賄い卓: セッション未作成、または staff_name 未設定のとき PIN が必要。セッション作成は confirmStaffMealAuth まで遅延する。
     */
    public function getRequiresStaffMealAuthProperty(): bool
    {
        $tid = (int) ($this->session?->restaurant_table_id ?? $this->activeRestaurantTableId ?? 0);
        if (! StaffTableSettlementPricing::isStaffMealTableId($tid) || $this->shopId === 0) {
            return false;
        }
        if ($this->activeRestaurantTableId === null || $this->activeRestaurantTableId < 1) {
            return false;
        }
        if ($this->activeTableSessionId === null) {
            return true;
        }
        if ($this->session === null) {
            return true;
        }

        $sn = $this->session->staff_name;

        return ! is_string($sn) || trim($sn) === '';
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    public function getStaffMealAuthOptionsProperty(): array
    {
        return app(StaffDirectoryService::class)->activeOptions($this->shopId);
    }

    public function confirmStaffMealAuth(): void
    {
        if ($this->shopId === 0) {
            return;
        }

        $tidEarly = (int) ($this->session?->restaurant_table_id ?? $this->activeRestaurantTableId ?? 0);
        if (StaffTableSettlementPricing::isStaffMealTableId($tidEarly)
            && $this->activeTableSessionId === null
            && $this->activeRestaurantTableId !== null
            && $this->activeRestaurantTableId > 0) {
            $this->ensureTableSession();
        }

        if ($this->activeTableSessionId === null) {
            return;
        }

        // Livewire の往復で Eloquent が未復元のときがあり、先に requires を読むと誤って no-op になる
        if ($this->session === null) {
            $this->loadSessionData((int) $this->activeTableSessionId);
        }
        if ($this->session === null) {
            return;
        }

        $tid = (int) ($this->session->restaurant_table_id ?? $this->activeRestaurantTableId ?? 0);
        if (! StaffTableSettlementPricing::isStaffMealTableId($tid)) {
            return;
        }

        if (! $this->requiresStaffMealAuth) {
            return;
        }

        if ($this->staffMealAuthStaffId === null || $this->staffMealAuthStaffId < 1) {
            Notification::make()
                ->title(__('pos.action_failed'))
                ->body(__('pos.staff_meal_auth_pick_staff'))
                ->warning()
                ->send();

            return;
        }
        $pin = trim($this->staffMealAuthPin);
        if ($pin === '') {
            Notification::make()
                ->title(__('pos.action_failed'))
                ->body(__('pos.staff_meal_auth_pin_required'))
                ->warning()
                ->send();

            return;
        }

        $staff = app(StaffDirectoryService::class)->findActiveById((int) $this->staffMealAuthStaffId, $this->shopId);
        if ($staff === null) {
            Notification::make()
                ->title(__('pos.action_failed'))
                ->body(__('pos.staff_meal_auth_invalid_staff'))
                ->danger()
                ->send();

            return;
        }

        $err = app(StaffPinAuthenticationService::class)->verify(
            $staff,
            $pin,
            'staff_meal',
            5,
            60,
        );
        if ($err !== null) {
            Notification::make()
                ->title($err)
                ->danger()
                ->send();

            return;
        }

        try {
            DB::transaction(function () use ($staff): void {
                $sid = (int) $this->activeTableSessionId;
                /** @var TableSession|null $session */
                $session = TableSession::query()
                    ->where('shop_id', $this->shopId)
                    ->whereKey($sid)
                    ->lockForUpdate()
                    ->first();
                if ($session === null) {
                    throw new \RuntimeException(__('rad_table.active_session_not_found'));
                }
                if (! StaffTableSettlementPricing::isStaffMealTableId((int) $session->restaurant_table_id)) {
                    throw new \RuntimeException(__('pos.staff_meal_auth_invalid_table'));
                }
                $session->staff_name = (string) $staff->name;
                $session->save();
            });
        } catch (Throwable $e) {
            Notification::make()
                ->title(__('pos.action_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->staffMealAuthModalDismissed = true;
        $this->staffMealAuthPin = '';
        $this->staffMealAuthStaffId = null;
        $this->loadSessionData((int) $this->activeTableSessionId);
        $sessionModel = $this->session;
        if ($sessionModel !== null && trim((string) ($sessionModel->staff_name ?? '')) === '') {
            $sessionModel = $sessionModel->fresh();
            if ($sessionModel !== null && trim((string) ($sessionModel->staff_name ?? '')) === '') {
                $sessionModel->staff_name = (string) $staff->name;
                $sessionModel->save();
            }
        }
        $this->forgetSessionOrderComputed();
        $this->dispatch('pos-refresh-tiles');
    }

    /**
     * 賄い PIN をキャンセルして卓ホストを閉じる（テーブル選択に戻る）。
     */
    public function cancelStaffMealAuth(): void
    {
        $this->staffMealAuthPin = '';
        $this->staffMealAuthStaffId = null;
        $this->staffMealAuthModalDismissed = false;
        $this->closeHost();
    }

    public function closeHost(): void
    {
        $this->memoSessionPricing = null;
        $this->memoStaffMealPreDiscountTaxSum = null;
        $this->activeRestaurantTableId = null;
        $this->activeTableSessionId = null;
        $this->activeRestaurantTableName = '';
        $this->forgetSessionOrderComputed();
        $this->isOrdersLoaded = false;
        $this->uiState = 'idle';
        $this->pendingEchoReload = false;
        $this->expectedSessionRevision = 0;
        $this->removeAuthPanelOpen = false;
        $this->removeAuthLineId = null;
        $this->removeApproverStaffId = null;
        $this->removeApproverPin = '';
        $this->removeDecisionMode = 'open';
        $this->staffMealAuthStaffId = null;
        $this->staffMealAuthPin = '';
        $this->staffMealAuthModalDismissed = false;
        $this->showReceiptPreview = false;
        $this->previewIntent = PrintIntent::Addition->value;
        $this->previewSessionId = 0;
        $this->dispatch('pos-tile-interaction-ended');
        $this->dispatchBrowserFloorClear();
    }

    #[On('pos-echo-order-placed')]
    public function onPosEchoOrderPlaced(mixed $shop_id = null, mixed $table_session_id = null): void
    {
        $sid = is_numeric($table_session_id) ? (int) $table_session_id : 0;
        if ($sid < 1 || (int) $shop_id !== $this->shopId) {
            $this->dispatch('pos-refresh-tiles');

            return;
        }
        if ($this->activeTableSessionId !== $sid) {
            $this->dispatch('pos-refresh-tiles');

            return;
        }
        if ($this->uiState === 'in_flight') {
            $this->pendingEchoReload = true;

            return;
        }
        $this->loadSessionData($sid);
        $this->dispatch('pos-refresh-tiles');
    }

    private function applyPendingEchoReloadIfAny(): void
    {
        if (! $this->pendingEchoReload) {
            return;
        }
        $this->pendingEchoReload = false;
        if ($this->activeTableSessionId === null) {
            return;
        }
        $this->loadSessionData((int) $this->activeTableSessionId);
        $this->dispatch('pos-refresh-tiles');
    }

    public function ajouter(): void
    {
        if ($this->activeRestaurantTableId === null || $this->activeRestaurantTableId < 1) {
            Notification::make()
                ->title(__('pos.add_pick_table'))
                ->warning()
                ->send();

            return;
        }

        if ($this->activeTableSessionId === null) {
            $this->ensureTableSession();
        }
        if ($this->activeTableSessionId === null) {
            Notification::make()
                ->title(__('pos.could_not_start_session'))
                ->danger()
                ->send();

            return;
        }
        if ($this->requiresStaffMealAuth) {
            return;
        }

        $this->addModalStep = 'list';
        $this->addConfigMenuItemId = null;
        $this->resetAddLineForm();
        $this->loadAddCatalog();
        $this->addModalOpen = true;
    }

    public function closeAddModal(): void
    {
        $this->addModalOpen = false;
        $this->addModalStep = 'list';
        $this->addConfigMenuItemId = null;
        $this->resetAddLineForm();
    }

    private function refocusAjouterButtonIfTakeaway(): void
    {
        if (! $this->isTakeawayTable) {
            return;
        }
        $this->js(<<<'JS'
            queueMicrotask(() => {
                const el = document.querySelector('[data-pos-ajouter-primary]');
                if (el && typeof el.focus === 'function') {
                    el.focus({ preventScroll: true });
                }
            });
        JS);
    }

    public function backToAddList(): void
    {
        $this->addModalStep = 'list';
        $this->addConfigMenuItemId = null;
        $this->resetAddLineForm();
    }

    public function beginConfigureItem(int $menuItemId): void
    {
        $ok = MenuItem::query()
            ->where('shop_id', $this->shopId)
            ->whereKey($menuItemId)
            ->where('is_active', true)
            ->exists();
        if (! $ok) {
            Notification::make()
                ->title(__('pos.menu_item_inactive'))
                ->danger()
                ->send();

            return;
        }
        $this->addConfigMenuItemId = $menuItemId;
        $this->addModalStep = 'config';
        $this->addQty = 1;
        $this->addStyleId = null;
        $this->addToppings = [];
        $this->addNote = '';
    }

    public function toggleAddTopping(string $toppingId): void
    {
        $i = array_search($toppingId, $this->addToppings, true);
        if ($i !== false) {
            unset($this->addToppings[$i]);
            $this->addToppings = array_values($this->addToppings);
        } else {
            $this->addToppings[] = $toppingId;
        }
    }

    public function submitAddLine(): void
    {
        if ($this->uiState === 'in_flight') {
            return;
        }
        if ($this->activeRestaurantTableId === null || $this->addConfigMenuItemId === null) {
            return;
        }
        if ($this->activeTableSessionId === null) {
            $this->ensureTableSession();
        }
        if ($this->activeTableSessionId === null) {
            return;
        }
        if ($this->requiresStaffMealAuth) {
            return;
        }

        $style = (is_string($this->addStyleId) && trim($this->addStyleId) !== '') ? $this->addStyleId : null;
        if ($this->isStyleRequiredFor($this->addItemForConfig) && $style === null) {
            Notification::make()
                ->title(__('pos.style_required'))
                ->warning()
                ->send();

            return;
        }

        $qty = max(1, min(200, (int) $this->addQty));
        $this->addQty = $qty;

        try {
            app(AddPosOrderFromStaffAction::class)->execute(
                $this->shopId,
                (int) $this->activeRestaurantTableId,
                (int) $this->addConfigMenuItemId,
                $qty,
                $style,
                $this->addToppings,
                (string) $this->addNote
            );
            // 明示: 商品追加後も卓コンテキストを維持するため closeHost / pos-tile-interaction-ended を呼ばない。
            $this->loadSessionData((int) $this->activeTableSessionId);
            $this->dispatch('pos-refresh-tiles');
            $this->closeAddModal();
            $this->refocusAjouterButtonIfTakeaway();
        } catch (Throwable $e) {
            Notification::make()
                ->title(__('pos.action_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getAddItemForConfigProperty(): ?MenuItem
    {
        if ($this->addConfigMenuItemId === null || $this->shopId === 0) {
            return null;
        }

        return MenuItem::query()
            ->where('shop_id', $this->shopId)
            ->whereKey($this->addConfigMenuItemId)
            ->first();
    }

    private function ensureTableSession(): void
    {
        if ($this->activeRestaurantTableId === null || $this->shopId === 0) {
            return;
        }
        $sessionId = (int) DB::transaction(function (): int {
            $table = RestaurantTable::query()
                ->where('shop_id', $this->shopId)
                ->whereKey((int) $this->activeRestaurantTableId)
                ->lockForUpdate()
                ->first();
            if ($table === null) {
                return 0;
            }
            $s = app(TableSessionLifecycleService::class)->getOrCreateActiveSession($table);

            return (int) $s->id;
        });
        if ($sessionId > 0) {
            $this->loadSessionData($sessionId);
        }
        $this->dispatch('pos-refresh-tiles');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getStylesListForItem(MenuItem $item): array
    {
        $payload = is_array($item->options_payload) ? $item->options_payload : [];
        $styles = is_array($payload['styles'] ?? null) ? $payload['styles'] : [];
        $out = [];
        foreach ($styles as $r) {
            if (! is_array($r) || (string) ($r['id'] ?? '') === '') {
                continue;
            }
            $m = (int) ($r['price_minor'] ?? 0);
            $out[] = [
                'id' => (string) $r['id'],
                'name' => (string) ($r['name'] ?? $r['id']),
                'price_label' => MenuItemMoney::formatMinorForDisplay(max(0, $m)),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getToppingsListForItem(MenuItem $item): array
    {
        $payload = is_array($item->options_payload) ? $item->options_payload : [];
        $toppings = is_array($payload['toppings'] ?? null) ? $payload['toppings'] : [];
        $out = [];
        foreach ($toppings as $r) {
            if (! is_array($r) || (string) ($r['id'] ?? '') === '') {
                continue;
            }
            $m = (int) ($r['price_delta_minor'] ?? 0);
            $out[] = [
                'id' => (string) $r['id'],
                'name' => (string) ($r['name'] ?? $r['id']),
                'price_label' => MenuItemMoney::formatMinorForDisplay(max(0, $m)),
            ];
        }

        return $out;
    }

    public function isStyleRequiredFor(?MenuItem $item): bool
    {
        if ($item === null) {
            return false;
        }
        $p = is_array($item->options_payload) ? $item->options_payload : [];
        $r = is_array($p['rules'] ?? null) ? $p['rules'] : [];

        return (bool) ($r['style_required'] ?? false);
    }

    private function resetAddLineForm(): void
    {
        $this->addQty = 1;
        $this->addStyleId = null;
        $this->addToppings = [];
        $this->addNote = '';
    }

    private function loadAddCatalog(): void
    {
        $shopId = $this->shopId;
        if ($shopId === 0) {
            $this->addCatalog = [];

            return;
        }

        $this->addCatalog = cache()->remember(
            "pos.catalog.shop.{$shopId}",
            now()->addMinutes(10),
            function () use ($shopId): array {
                $blocks = [];
                $categories = MenuCategory::query()
                    ->where('shop_id', $shopId)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->with([
                        'menuItems' => static fn ($q) => $q
                            ->where('shop_id', $shopId)
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->orderBy('id')
                            ->select(['id', 'name', 'from_price_minor', 'options_payload', 'menu_category_id']),
                    ])
                    ->get();

                foreach ($categories as $cat) {
                    $rows = [];
                    foreach ($cat->menuItems as $m) {
                        $rows[] = [
                            'id' => (int) $m->id,
                            'name' => (string) $m->name,
                            'from_label' => MenuItemMoney::formatMinorForDisplay((int) $m->from_price_minor),
                        ];
                    }
                    if (count($rows) > 0) {
                        $blocks[] = [
                            'id' => (int) $cat->id,
                            'name' => (string) $cat->name,
                            'items' => $rows,
                        ];
                    }
                }

                return $blocks;
            }
        );
    }

    /**
     * Legacy entrypoint kept for button compatibility: now opens the receipt preview.
     */
    public function printAddition(): void
    {
        $this->openReceiptPreview(PrintIntent::Addition->value);
    }

    public function openReceiptPreview(string $intent = 'addition'): void
    {
        if ($this->requiresStaffMealAuth) {
            return;
        }
        if ($this->activeTableSessionId === null || $this->session === null || $this->uiState === 'in_flight') {
            return;
        }

        $resolved = PrintIntent::tryFrom($intent) ?? PrintIntent::Addition;
        if ($resolved === PrintIntent::Addition && ! $this->canImprimerAddition) {
            return;
        }

        $this->previewIntent = $resolved->value;
        $this->previewSessionId = (int) $this->activeTableSessionId;
        $this->showReceiptPreview = true;
    }

    #[On('close-receipt')]
    public function closeReceiptPreview(): void
    {
        $this->showReceiptPreview = false;
        $this->previewIntent = PrintIntent::Addition->value;
        $this->previewSessionId = 0;
        $this->closeHostIfSessionClosed();
    }

    #[On('receipt-preview-printed')]
    public function onReceiptPreviewPrinted(mixed $table_session_id = null, mixed $intent = null): void
    {
        $sid = is_numeric($table_session_id) ? (int) $table_session_id : 0;
        if ($sid < 1 || $this->activeTableSessionId !== $sid) {
            return;
        }

        $this->showReceiptPreview = false;
        $this->previewIntent = PrintIntent::Addition->value;
        $this->previewSessionId = 0;
        $this->loadSessionData($sid);
        $this->dispatch('pos-refresh-tiles');
        $this->closeHostIfSessionClosed();
    }

    private function closeHostIfSessionClosed(): void
    {
        if ($this->session?->status === TableSessionStatus::Closed) {
            $this->closeHost();
        }
    }

    /**
     * Confirm Placed → Confirmed (Recu staff).
     */
    public function confirmOrders(): void
    {
        if ($this->requiresStaffMealAuth) {
            return;
        }
        if ($this->uiState === 'in_flight') {
            return;
        }
        if ($this->activeTableSessionId === null || $this->session === null) {
            Notification::make()
                ->title(__('pos.detail_pick_table'))
                ->warning()
                ->send();

            return;
        }
        if (! $this->hasUnackedPlaced) {
            return;
        }
        $this->uiState = 'in_flight';
        try {
            app(RecuPlacedOrdersForSessionAction::class)->execute(
                $this->shopId,
                (int) $this->activeTableSessionId,
                $this->expectedSessionRevision
            );
            $this->uiState = 'success';
            // 明示: KDS 送信後も卓コンテキストを維持するため closeHost / pos-tile-interaction-ended を呼ばない。
            $this->loadSessionData((int) $this->activeTableSessionId);
            $this->dispatch('pos-refresh-tiles');
            $this->refocusAjouterButtonIfTakeaway();
        } catch (RevisionConflictException $e) {
            $this->uiState = 'failed';
            Notification::make()
                ->title(__('pos.data_stale_title'))
                ->body(__('pos.revision_conflict_reload'))
                ->warning()
                ->send();
            $this->loadSessionData((int) $this->activeTableSessionId);
            $this->dispatch('pos-refresh-tiles');
            $this->refocusAjouterButtonIfTakeaway();
        } catch (Throwable $e) {
            $this->uiState = 'failed';
            Notification::make()
                ->title(__('pos.action_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->applyPendingEchoReloadIfAny();
            $this->uiState = 'idle';
        }
    }

    /**
     * Cloture entry point. Instead of running the settlement inline (the
     * pre-Phase-3 behaviour that directly called CheckoutTableSessionAction),
     * we hand off to the ClotureModal Livewire component, which owns the
     * Juste/Proche UI, payment-method picker, and Manager-PIN bypass.
     *
     * The modal dispatches `pos-settlement-completed` on success (with
     * `open_receipt_preview` true/false) — see {@see self::onPosSettlementCompleted()}.
     */
    public function checkoutSession(): void
    {
        if ($this->requiresStaffMealAuth) {
            return;
        }
        if ($this->activeTableSessionId === null || $this->uiState === 'in_flight') {
            return;
        }
        if ($this->hasUnackedPlaced) {
            Notification::make()
                ->title(__('rad_table.cannot_close_with_unacked'))
                ->warning()
                ->send();

            return;
        }
        if ($this->posOrders->isEmpty()) {
            Notification::make()
                ->title(__('pos.checkout_no_orders_to_settle'))
                ->warning()
                ->send();

            return;
        }
        $this->dispatch(
            'pos-cloture-open',
            shop_id: $this->shopId,
            table_session_id: (int) $this->activeTableSessionId,
            expected_revision: $this->expectedSessionRevision,
        );
    }

    #[On('pos-settlement-completed')]
    public function onPosSettlementCompleted(mixed $table_session_id = null, mixed $open_receipt_preview = null): void
    {
        $sid = is_numeric($table_session_id) ? (int) $table_session_id : 0;
        $openPreview = $open_receipt_preview === true;

        if ($sid > 0 && $this->activeTableSessionId === $sid) {
            if ($openPreview) {
                $this->loadSessionData($sid);
                $this->openReceiptPreview(PrintIntent::Receipt->value);
            } else {
                $this->closeHost();
            }
            $this->dispatch('pos-refresh-tiles');

            return;
        }

        $this->dispatch('pos-refresh-tiles');
    }

    #[On('pos-discount-applied')]
    public function onPosDiscountApplied(mixed $scope = null, mixed $target_id = null): void
    {
        if ($this->activeTableSessionId === null) {
            return;
        }
        $this->loadSessionData((int) $this->activeTableSessionId);
        $this->dispatch('pos-refresh-tiles');
    }

    public function openDiscountForCurrent(): void
    {
        if ($this->requiresStaffMealAuth) {
            return;
        }
        if ($this->activeTableSessionId === null || $this->session === null || $this->uiState === 'in_flight') {
            return;
        }

        $scope = 'order';
        $targetId = $this->latestOrderId;
        if ($this->isStaffTable) {
            $scope = 'staff';
            $targetId = $this->activeTableSessionId;
        }

        if ($targetId === null || $targetId < 1) {
            Notification::make()
                ->title(__('pos.discount_target_not_found'))
                ->warning()
                ->send();

            return;
        }

        $this->dispatch(
            'pos-discount-open',
            shop_id: $this->shopId,
            scope: $scope,
            target_id: (int) $targetId,
        );
    }

    public function applyStaffMealQuick(): void
    {
        if ($this->requiresStaffMealAuth) {
            return;
        }
        if (! $this->isStaffTable || $this->activeTableSessionId === null || $this->session === null || $this->uiState === 'in_flight') {
            return;
        }

        $this->dispatch(
            'pos-discount-open',
            shop_id: $this->shopId,
            scope: 'staff',
            target_id: (int) $this->activeTableSessionId,
        );
    }

    public function promptRemoveLine(int $orderLineId): void
    {
        if ($this->requiresStaffMealAuth) {
            return;
        }
        if ($this->uiState === 'in_flight') {
            return;
        }
        if ($this->activeTableSessionId === null) {
            return;
        }
        $decision = app(DeleteOrderLineWithPolicyAction::class)->decide(
            $this->shopId,
            (int) $this->activeTableSessionId
        );
        $this->removeDecisionMode = (string) ($decision['mode'] ?? DeleteOrderLineWithPolicyAction::MODE_OPEN);

        if ($this->removeDecisionMode === DeleteOrderLineWithPolicyAction::MODE_PIN) {
            $this->removeAuthPanelOpen = true;
            $this->removeAuthLineId = $orderLineId;
            $this->removeApproverStaffId = null;
            $this->removeApproverPin = '';
            Notification::make()
                ->title(__('pos.remove_line_auth_required_title'))
                ->body(__('pos.remove_line_auth_required_body'))
                ->warning()
                ->send();

            return;
        }

        if ($this->removeDecisionMode === DeleteOrderLineWithPolicyAction::MODE_OPEN) {
            Notification::make()
                ->title(__('pos.remove_line_preprint_notice_title'))
                ->body(__('pos.remove_line_preprint_notice_body'))
                ->warning()
                ->send();
        }

        $this->executeRemoveLine(
            orderLineId: $orderLineId,
            approverStaffId: isset($decision['approver_staff_id']) ? (int) $decision['approver_staff_id'] : null,
            approvalMode: $this->removeDecisionMode,
        );
    }

    public function cancelRemoveWithAuth(): void
    {
        $this->removeAuthPanelOpen = false;
        $this->removeAuthLineId = null;
        $this->removeApproverStaffId = null;
        $this->removeApproverPin = '';
        $this->removeDecisionMode = 'open';
    }

    public function confirmRemoveWithAuth(): void
    {
        if ($this->uiState === 'in_flight' || $this->removeAuthLineId === null) {
            return;
        }
        if ($this->removeApproverStaffId === null || trim($this->removeApproverPin) === '') {
            Notification::make()
                ->title(__('pos.action_failed'))
                ->body(__('pos.remove_line_auth_input_required'))
                ->warning()
                ->send();

            return;
        }

        $lineId = (int) $this->removeAuthLineId;
        $approverId = (int) $this->removeApproverStaffId;
        // Keep values before reset: cancelRemoveWithAuth() clears PIN/state.
        $approverPin = $this->removeApproverPin;
        $this->cancelRemoveWithAuth();
        $this->executeRemoveLine($lineId, $approverId, DeleteOrderLineWithPolicyAction::MODE_PIN, $approverPin);
    }

    public function removeLine(int $orderLineId): void
    {
        $this->promptRemoveLine($orderLineId);
    }

    private function executeRemoveLine(
        int $orderLineId,
        ?int $approverStaffId = null,
        string $approvalMode = 'open',
        ?string $approverPin = null
    ): void {
        if ($this->uiState === 'in_flight') {
            return;
        }
        if ($this->activeTableSessionId === null) {
            return;
        }
        try {
            app(DeleteOrderLineWithPolicyAction::class)->execute(
                shopId: $this->shopId,
                tableSessionId: (int) $this->activeTableSessionId,
                orderLineId: $orderLineId,
                actorUserId: (int) (Auth::id() ?? 0),
                mode: $approvalMode,
                approverStaffId: $approverStaffId,
                approverPin: $approverPin,
            );
            $this->loadSessionData((int) $this->activeTableSessionId);
            $this->dispatch('pos-refresh-tiles');
        } catch (Throwable $e) {
            Notification::make()
                ->title(__('pos.action_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @return list<array{id:int,name:string,level:int}>
     */
    public function getRemoveApproverOptionsProperty(): array
    {
        return app(StaffDirectoryService::class)->approverCandidateOptions($this->shopId);
    }

    public function getSubtotalMinorProperty(): int
    {
        return $this->resolveSessionPricing()->finalTotalMinor;
    }

    /**
     * Same pricing pipeline as cloture / settlement (staff meal 100–104 = 50% off net).
     */
    private function resolveSessionPricing(): PricingResult
    {
        if ($this->memoSessionPricing !== null) {
            return $this->memoSessionPricing;
        }
        $tid = (int) ($this->session?->restaurant_table_id ?? $this->activeRestaurantTableId ?? 0);
        if ($tid < 1 || $this->posOrders->isEmpty()) {
            $this->memoSessionPricing = app(PricingEngine::class)->calculate(new PricingInput([], [], 0));

            return $this->memoSessionPricing;
        }
        $this->memoSessionPricing = StaffTableSettlementPricing::calculateFromPosOrders(
            $this->posOrders,
            $tid,
        );

        return $this->memoSessionPricing;
    }

    public function getIsStaffMealTableProperty(): bool
    {
        $tid = (int) ($this->session?->restaurant_table_id ?? $this->activeRestaurantTableId ?? 0);

        return StaffTableSettlementPricing::isStaffMealTableId($tid);
    }

    public function getStaffMealShowPricingBreakdownProperty(): bool
    {
        return $this->isStaffMealTable
            && $this->resolveSessionPricing()->orderDiscountAppliedMinor > 0;
    }

    public function getStaffMealGrossMinorProperty(): int
    {
        return $this->resolveSessionPricing()->orderSubtotalMinor;
    }

    public function getStaffMealDiscountMinorProperty(): int
    {
        return $this->resolveSessionPricing()->orderDiscountAppliedMinor;
    }

    /**
     * 賄い卓フッター: スタッフ割引適用前（明細ベース）の HT 合計（ミリウム）。
     */
    public function getStaffMealPreDiscountHtMinorProperty(): int
    {
        if (! $this->isStaffMealTable || $this->posOrders->isEmpty()) {
            return 0;
        }

        return $this->staffMealPreDiscountTaxSum()['ht_minor'];
    }

    /**
     * 賄い卓フッター: スタッフ割引適用前（明細ベース）の TVA 合計（ミリウム）。
     */
    public function getStaffMealPreDiscountVatMinorProperty(): int
    {
        if (! $this->isStaffMealTable || $this->posOrders->isEmpty()) {
            return 0;
        }

        return $this->staffMealPreDiscountTaxSum()['vat_minor'];
    }

    /**
     * レシート既定税率に基づく表示用（例: 13, 19）。明細の税分割と一致。
     */
    public function getStaffMealReceiptVatRateLabelProperty(): string
    {
        return ReceiptTaxMath::formatPercentForUi(ReceiptTaxMath::defaultVatPercent());
    }

    /**
     * @return array{ht_minor: int, vat_minor: int}
     */
    private function staffMealPreDiscountTaxSum(): array
    {
        if ($this->memoStaffMealPreDiscountTaxSum !== null) {
            return $this->memoStaffMealPreDiscountTaxSum;
        }

        $enriched = PosOrderReceiptLineEnricher::enrich($this->posOrders);
        $bucketInput = [];
        foreach ($enriched as $ln) {
            $bucketInput[] = [
                'ttc_minor' => (int) $ln['amount_minor'],
                'vat_percent' => (float) $ln['vat_percent'],
            ];
        }
        $buckets = ReceiptTaxMath::aggregateVatBuckets($bucketInput);
        $this->memoStaffMealPreDiscountTaxSum = ReceiptTaxMath::sumBucketsHtVat($buckets);

        return $this->memoStaffMealPreDiscountTaxSum;
    }

    public function getHasUnackedPlacedProperty(): bool
    {
        foreach ($this->posOrders as $o) {
            if ($o->status === OrderStatus::Placed) {
                return true;
            }
        }

        return false;
    }

    public function getCanRecuStaffProperty(): bool
    {
        return $this->activeTableSessionId !== null
            && $this->session !== null
            && $this->hasUnackedPlaced;
    }

    public function getCanImprimerAdditionProperty(): bool
    {
        return $this->activeTableSessionId !== null
            && $this->session !== null
            && ! $this->hasUnackedPlaced
            && $this->posOrders->isNotEmpty();
    }

    public function getCanClotureProperty(): bool
    {
        return $this->activeTableSessionId !== null
            && $this->session !== null
            && ! $this->hasUnackedPlaced
            && $this->posOrders->isNotEmpty();
    }

    public function getFooterActionsLockedProperty(): bool
    {
        return $this->uiState === 'in_flight'
            || ! $this->isOrdersLoaded
            || $this->requiresStaffMealAuth;
    }

    public function getActiveTableCategoryProperty(): ?TableCategory
    {
        if ($this->activeRestaurantTableId === null) {
            return null;
        }

        return TableCategory::tryResolveFromId((int) $this->activeRestaurantTableId);
    }

    public function getIsStaffTableProperty(): bool
    {
        return $this->activeTableCategory === TableCategory::Staff;
    }

    public function getIsTakeawayTableProperty(): bool
    {
        return $this->activeTableCategory === TableCategory::Takeaway;
    }

    public function getIsBilledStateProperty(): bool
    {
        return $this->session !== null
            && $this->session->last_addition_printed_at !== null
            && ! $this->hasUnackedPlaced
            && $this->posOrders->isNotEmpty();
    }

    public function getCanApplyDiscountProperty(): bool
    {
        if ($this->activeTableSessionId === null || $this->session === null) {
            return false;
        }

        if ($this->isStaffTable) {
            return true;
        }

        return $this->latestOrderId !== null;
    }

    public function getLatestOrderIdProperty(): ?int
    {
        if ($this->posOrders->isEmpty()) {
            return null;
        }

        /** @var PosOrder|null $order */
        $order = $this->posOrders->sortByDesc('id')->first();

        return $order !== null ? (int) $order->id : null;
    }

    public function getActiveCategoryLabelProperty(): string
    {
        return match ($this->activeTableCategory) {
            TableCategory::Staff => __('pos.staff_tile_heading'),
            TableCategory::Takeaway => __('pos.takeout_heading'),
            default => __('pos.table_name_fallback', ['id' => (int) ($this->activeRestaurantTableId ?? 0)]),
        };
    }

    public function getActiveSessionLabelProperty(): string
    {
        if ($this->session !== null
            && is_string($this->session->staff_name)
            && trim($this->session->staff_name) !== ''
            && StaffTableSettlementPricing::isStaffMealTableId((int) $this->session->restaurant_table_id)
        ) {
            return trim($this->session->staff_name);
        }

        if ($this->session !== null
            && is_string($this->session->customer_name)
            && trim($this->session->customer_name) !== ''
            && TableCategory::tryResolveFromId((int) $this->session->restaurant_table_id) === TableCategory::Takeaway
        ) {
            return trim($this->session->customer_name);
        }

        $n = $this->activeRestaurantTableName !== '' ? $this->activeRestaurantTableName : (string) ($this->activeRestaurantTableId ?? '');

        if ($this->activeTableSessionId !== null) {
            return $n !== '' ? $n : 'Session #'.$this->activeTableSessionId;
        }

        return $n !== '' ? $n : __('pos.drawer_table_only');
    }

    public function formatMinor(int $minor): string
    {
        return MenuItemMoney::formatMinorForDisplay($minor);
    }

    /**
     * 卓明細1行目: 表示名 = snapshot_name ＋ 半角スペース ＋ スタイル名
     */
    public function linePrimaryText(OrderLine $line): string
    {
        $name = trim((string) $line->snapshot_name);
        $payload = is_array($line->snapshot_options_payload) ? $line->snapshot_options_payload : null;
        if ($payload === null) {
            return $name;
        }
        $style = is_array($payload['style'] ?? null) ? $payload['style'] : null;
        if ($style !== null && (string) ($style['name'] ?? '') !== '') {
            return trim($name.' '.trim((string) $style['name']));
        }

        return $name;
    }

    /**
     * 卓明細2行目: extra: トッピング（, 区切り） · メモ
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function lineExtraLineForTable(?array $payload): string
    {
        if ($payload === null) {
            return '';
        }
        $names = [];
        $toppings = $payload['toppings'] ?? null;
        if (is_array($toppings)) {
            foreach ($toppings as $t) {
                if (is_array($t) && (string) ($t['name'] ?? '') !== '') {
                    $names[] = (string) $t['name'];
                }
            }
        }
        $topStr = $names === [] ? '' : implode(', ', $names);
        $note = is_string($payload['note'] ?? null) && trim((string) $payload['note']) !== ''
            ? trim((string) $payload['note'])
            : '';
        if ($topStr === '' && $note === '') {
            return '';
        }
        if ($topStr === '') {
            return __('pos.table_line_extra', ['list' => $note]);
        }
        if ($note === '') {
            return __('pos.table_line_extra', ['list' => $topStr]);
        }

        return __('pos.table_line_extra', ['list' => $topStr.' · '.$note]);
    }

    /**
     * @return Collection<int, OrderLine>
     */
    public function getAllLinesProperty(): Collection
    {
        /** @var Collection<int, OrderLine> $lines */
        $lines = $this->posOrders
            ->flatMap(static fn (PosOrder $o): Collection => $o->lines)
            ->sortBy('id')
            ->values();

        return $lines;
    }

    /**
     * @return Collection<int, OrderLine>
     */
    public function getUnsentLinesProperty(): Collection
    {
        return $this->allLines
            ->filter(static fn (OrderLine $line): bool => $line->status === OrderLineStatus::Placed)
            ->values();
    }

    /**
     * @return Collection<int, OrderLine>
     */
    public function getSentLinesProperty(): Collection
    {
        return $this->allLines
            ->filter(static fn (OrderLine $line): bool => $line->status !== OrderLineStatus::Placed)
            ->values();
    }

    public function isFreshUnsentLine(OrderLine $line): bool
    {
        if ($line->status !== OrderLineStatus::Placed) {
            return false;
        }

        return (optional($line->updated_at)?->diffInSeconds(now()) ?? 999999) <= 15;
    }

    public function render()
    {
        return view('livewire.pos.table-action-host');
    }
}
