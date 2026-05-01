<?php

namespace App\Livewire\Pos;

use App\Actions\Pos\FinalizeTableSettlementAction;
use App\Actions\Pos\FinalizeTableSettlementRequest;
use App\Domains\Pos\Pricing\PricingEngine;
use App\Domains\Pos\Settlement\SettlementSuggestionService;
use App\Domains\Pos\Tables\TableCategory;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\Pos\InsufficientTenderException;
use App\Exceptions\Pos\PendingOrdersRemainException;
use App\Exceptions\Pos\SessionAlreadySettledException;
use App\Exceptions\RevisionConflictException;
use App\Models\PosOrder;
use App\Models\TableSession;
use App\Services\Pos\TableDashboardQueryService;
use App\Support\MenuItemMoney;
use App\Support\Pos\StaffTableSettlementPricing;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Cloture (checkout) modal Livewire component.
 *
 * Orchestrates the cashier-facing checkout flow:
 *   1. (re)prices the open session via PricingEngine (int-minor only).
 *   2. suggests Juste / Proche tender amounts via SettlementSuggestionService.
 *   3. delegates the transactional settlement to FinalizeTableSettlementAction (cash only in UI).
 *   4. dispatches `pos-settlement-completed` with `open_receipt_preview: true` so
 *      {@see TableActionHost} opens {@see ReceiptPreview} (intent=receipt); the
 *      cashier prints once from the preview (single `pos-trigger-print` path).
 */
class ClotureModal extends Component
{
    #[Locked]
    public int $shopId = 0;

    public bool $open = false;

    public ?int $tableSessionId = null;

    public int $expectedSessionRevision = 0;

    public string $tableLabel = '';

    public string $shopName = '';

    /** @var 'idle'|'in_flight'|'success'|'failed' */
    public string $uiState = 'idle';

    public int $subtotalMinor = 0;

    public int $discountAppliedMinor = 0;

    public int $roundingAdjustmentMinor = 0;

    public int $finalTotalMinor = 0;

    public int $justeMinor = 0;

    /** @var list<int> */
    public array $procheMinor = [];

    public ?int $tenderedMinor = null;

    /** Human-editable tender string (DT); synced to {@see $tenderedMinor}. */
    public string $tenderedDtInput = '';

    public int $changeMinor = 0;

    public function mount(int $shopId): void
    {
        $this->shopId = $shopId;
    }

    #[On('pos-cloture-open')]
    public function onOpen(mixed $shop_id = null, mixed $table_session_id = null, mixed $expected_revision = null): void
    {
        if ((int) $shop_id !== $this->shopId) {
            return;
        }
        $sid = is_numeric($table_session_id) ? (int) $table_session_id : 0;
        if ($sid < 1) {
            return;
        }
        $this->reset([
            'tenderedMinor',
            'tenderedDtInput',
            'changeMinor',
        ]);
        $this->uiState = 'idle';
        $this->tableSessionId = $sid;
        $this->expectedSessionRevision = is_numeric($expected_revision) ? (int) $expected_revision : 0;

        if (! $this->loadPricing()) {
            return;
        }

        $this->open = true;
    }

    public function closeModal(): void
    {
        $this->open = false;
        $this->tableSessionId = null;
        $this->uiState = 'idle';
    }

    /**
     * テスト・デバッグ用: 預かり金（ミリウム）を直接セットし、釣りを同期する。
     */
    public function setTendered(int $tenderedMinor): void
    {
        $tendered = MenuItemMoney::snapMinorToHalfDt(max(0, $tenderedMinor));
        $this->tenderedMinor = $tendered;
        $this->tenderedDtInput = MenuItemMoney::minorToDtInputString($tendered);
        $this->changeMinor = $tendered - $this->finalTotalMinor;
    }

    public function confirm(): void
    {
        if ($this->uiState === 'in_flight' || $this->tableSessionId === null) {
            return;
        }
        $this->uiState = 'in_flight';
        // TEMP: POS_SETTLE_DEBUG
        $traceId = sprintf(
            'settle-%d-%d-%d',
            $this->shopId,
            (int) $this->tableSessionId,
            (int) round(microtime(true) * 1000)
        );

        try {
            $actorUserId = $this->resolveSettlementActorUserId();
            if ($actorUserId < 1) {
                $this->uiState = 'failed';
                Notification::make()
                    ->title(__('pos.action_failed'))
                    ->body(
                        '会計を記録するユーザー ID が未設定です。環境変数 POS2_SETTLEMENT_ACTOR_USER_ID に users.id を設定し、POS2 から再ログインしてください。',
                    )
                    ->danger()
                    ->send();

                return;
            }
            $this->debugSettleLog('confirm_enter', [
                'trace_id' => $traceId,
                'shop_id' => $this->shopId,
                'table_session_id' => (int) $this->tableSessionId,
                'expected_revision' => $this->expectedSessionRevision,
                'actor_user_id' => $actorUserId,
                'ui_state' => $this->uiState,
                'tendered_dt_input' => $this->tenderedDtInput,
                'final_total_minor' => $this->finalTotalMinor,
            ]);
            $this->tenderedMinor = MenuItemMoney::parseDtInputToMinor($this->tenderedDtInput);
            $tendered = (int) ($this->tenderedMinor ?? 0);
            $this->changeMinor = $tendered - $this->finalTotalMinor;

            if ($tendered < $this->finalTotalMinor) {
                $this->uiState = 'failed';
                Notification::make()->title(__('rad_table.insufficient_tender'))->danger()->send();

                return;
            }

            $settlement = app(FinalizeTableSettlementAction::class)->execute(
                new FinalizeTableSettlementRequest(
                    shopId: $this->shopId,
                    tableSessionId: (int) $this->tableSessionId,
                    expectedSessionRevision: $this->expectedSessionRevision,
                    tenderedMinor: $tendered,
                    paymentMethod: PaymentMethod::Cash,
                    actorUserId: $actorUserId,
                    debugTraceId: $traceId,
                )
            );
            $this->debugSettleLog('confirm_action_success', [
                'trace_id' => $traceId,
                'settlement_id' => (int) $settlement->id,
                'table_session_id' => (int) $this->tableSessionId,
            ]);

            $this->dispatch(
                'pos-settlement-completed',
                table_session_id: (int) $settlement->table_session_id,
                open_receipt_preview: true,
                settlement_trace_id: $traceId,
            );
            if ($this->shopId > 0) {
                app(TableDashboardQueryService::class)->forgetCachedDashboard($this->shopId);
            }
            $this->dispatch('pos-refresh-tiles');
            $this->uiState = 'success';
            $this->closeModal();
        } catch (InsufficientTenderException $e) {
            $this->debugSettleLog('confirm_insufficient_tender', [
                'trace_id' => $traceId,
                'table_session_id' => (int) ($this->tableSessionId ?? 0),
                'message' => $e->getMessage(),
            ]);
            $this->uiState = 'failed';
            Notification::make()->title(__('rad_table.insufficient_tender'))->danger()->send();
        } catch (PendingOrdersRemainException $e) {
            $this->debugSettleLog('confirm_pending_orders_remain', [
                'trace_id' => $traceId,
                'table_session_id' => (int) ($this->tableSessionId ?? 0),
                'message' => $e->getMessage(),
            ]);
            $this->uiState = 'failed';
            Notification::make()->title(__('rad_table.cannot_close_with_unacked'))->danger()->send();
        } catch (SessionAlreadySettledException $e) {
            $this->debugSettleLog('confirm_session_already_settled', [
                'trace_id' => $traceId,
                'table_session_id' => (int) ($this->tableSessionId ?? 0),
                'message' => $e->getMessage(),
            ]);
            Notification::make()->title(__('rad_table.session_already_settled'))->warning()->send();
            // Idempotent completion path:
            // another in-flight confirm may have already settled this session.
            // Continue with the same post-settlement flow so cashier always reaches preview.
            $this->dispatch(
                'pos-settlement-completed',
                table_session_id: (int) $this->tableSessionId,
                open_receipt_preview: true,
                settlement_trace_id: $traceId,
            );
            if ($this->shopId > 0) {
                app(TableDashboardQueryService::class)->forgetCachedDashboard($this->shopId);
            }
            $this->dispatch('pos-refresh-tiles');
            $this->uiState = 'success';
            $this->closeModal();
        } catch (RevisionConflictException $e) {
            $this->debugSettleLog('confirm_revision_conflict', [
                'trace_id' => $traceId,
                'table_session_id' => (int) ($this->tableSessionId ?? 0),
                'message' => $e->getMessage(),
            ]);
            $this->uiState = 'failed';
            Notification::make()->title(__('pos.data_stale_title'))->body(__('pos.revision_conflict_reload'))->warning()->send();
            if ($this->shopId > 0) {
                app(TableDashboardQueryService::class)->forgetCachedDashboard($this->shopId);
            }
            $this->dispatch('pos-refresh-tiles');
            $this->loadPricing();
        } catch (Throwable $e) {
            $this->debugSettleLog('confirm_throwable', [
                'trace_id' => $traceId,
                'table_session_id' => (int) ($this->tableSessionId ?? 0),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            $this->uiState = 'failed';
            Notification::make()->title(__('pos.action_failed'))->body($e->getMessage())->danger()->send();
        }
    }

    public function formatMinor(int $minor): string
    {
        return MenuItemMoney::formatMinorForDisplay($minor);
    }

    public function render()
    {
        return view('livewire.pos.cloture-modal');
    }

    private function loadPricing(): bool
    {
        $session = TableSession::query()
            ->with('restaurantTable:id,name')
            ->where('shop_id', $this->shopId)
            ->whereKey($this->tableSessionId)
            ->first();

        if ($session === null) {
            Notification::make()->title(__('rad_table.active_session_not_found'))->danger()->send();
            $this->closeModal();

            return false;
        }

        $tid = (int) $session->restaurant_table_id;
        if (StaffTableSettlementPricing::isStaffMealTableId($tid)
            && is_string($session->staff_name)
            && trim($session->staff_name) !== '') {
            $this->tableLabel = trim($session->staff_name);
        } elseif (TableCategory::tryResolveFromId($tid) === TableCategory::Takeaway
            && is_string($session->customer_name)
            && trim($session->customer_name) !== '') {
            $this->tableLabel = trim($session->customer_name);
        } else {
            $this->tableLabel = (string) ($session->restaurantTable->name ?? '');
        }
        $this->shopName = (string) (config('app.name') ?? 'Restaurant');

        $orders = PosOrder::query()
            ->where('shop_id', $this->shopId)
            ->where('table_session_id', $session->id)
            ->where('status', '!=', OrderStatus::Voided)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $pricing = StaffTableSettlementPricing::calculateFromPosOrders(
            $orders,
            $tid,
            app(PricingEngine::class),
        );

        $this->subtotalMinor = $pricing->orderSubtotalMinor;
        $this->discountAppliedMinor = $pricing->orderDiscountAppliedMinor;
        $this->roundingAdjustmentMinor = $pricing->roundingAdjustmentMinor;
        $this->finalTotalMinor = $pricing->finalTotalMinor;

        $sugg = app(SettlementSuggestionService::class)->suggest($pricing->finalTotalMinor);
        $this->justeMinor = $sugg->justeMinor;
        $this->procheMinor = $sugg->procheMinor;

        $this->tenderedMinor = null;
        $this->tenderedDtInput = '';
        $this->changeMinor = 0;

        return true;
    }

    private function resolveSettlementActorUserId(): int
    {
        $id = (int) (auth()->id() ?? 0);
        if ($id >= 1) {
            return $id;
        }
        $sessionActor = (int) (session('pos2.settlement_actor_user_id') ?? 0);
        if ($sessionActor >= 1) {
            return $sessionActor;
        }

        return (int) config('app.pos2_settlement_actor_user_id', 0);
    }

    // TEMP: POS_SETTLE_DEBUG
    private function debugSettleLog(string $event, array $context = []): void
    {
        if (! config('app.debug')) {
            return;
        }

        Log::info('POS_SETTLE_DEBUG '.$event, $context);
    }
}
