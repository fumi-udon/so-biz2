<?php

namespace App\Livewire\Pos;

use App\Actions\Pos\FinalizeTableSettlementAction;
use App\Actions\Pos\FinalizeTableSettlementRequest;
use App\Domains\Pos\Pricing\PricingEngine;
use App\Domains\Pos\Settlement\SettlementSuggestionService;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\Pos\DiscountPinRejectedException;
use App\Exceptions\Pos\InsufficientTenderException;
use App\Exceptions\Pos\PendingOrdersRemainException;
use App\Exceptions\Pos\SessionAlreadySettledException;
use App\Exceptions\RevisionConflictException;
use App\Models\PosOrder;
use App\Models\Staff;
use App\Models\TableSession;
use App\Services\StaffPinAuthenticationService;
use App\Support\MenuItemMoney;
use App\Support\Pos\StaffTableSettlementPricing;
use Filament\Notifications\Notification;
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
 *   3. delegates the transactional settlement to FinalizeTableSettlementAction.
 *   4. dispatches `pos-settlement-completed` with `open_receipt_preview: true` so
 *      {@see TableActionHost} opens {@see ReceiptPreview} (intent=receipt); the
 *      cashier prints once from the preview (single `pos-trigger-print` path).
 *   5. offers a Manager-PIN bypass path; bypass dispatches with
 *      `open_receipt_preview: false` so the host closes without a receipt preview.
 *
 * The component itself is dumb: no business rules, only state and I/O
 * glue. Every hard invariant lives in the Action layer where tests prove it.
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

    public string $paymentMethod = 'cash';

    public ?int $tenderedMinor = null;

    public int $changeMinor = 0;

    public bool $bypassMode = false;

    public string $bypassReason = '';

    public ?int $bypassApproverStaffId = null;

    public string $bypassApproverPin = '';

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
            'paymentMethod',
            'tenderedMinor',
            'changeMinor',
            'bypassMode',
            'bypassReason',
            'bypassApproverStaffId',
            'bypassApproverPin',
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

    public function pickPayment(string $method): void
    {
        if (! in_array($method, ['cash', 'card', 'voucher'], true)) {
            return;
        }
        $this->paymentMethod = $method;
        if ($method === 'card') {
            $this->tenderedMinor = $this->finalTotalMinor;
        }
        $this->recomputeChange();
    }

    public function setTendered(int $minor): void
    {
        $this->tenderedMinor = max(0, $minor);
        $this->recomputeChange();
    }

    public function updatedTenderedMinor(): void
    {
        $this->recomputeChange();
    }

    public function toggleBypass(): void
    {
        $this->bypassMode = ! $this->bypassMode;
        if (! $this->bypassMode) {
            $this->bypassReason = '';
            $this->bypassApproverStaffId = null;
            $this->bypassApproverPin = '';
        }
    }

    public function confirm(): void
    {
        if ($this->uiState === 'in_flight' || $this->tableSessionId === null) {
            return;
        }
        $this->uiState = 'in_flight';

        try {
            $method = PaymentMethod::from($this->paymentMethod);
            $tendered = $method === PaymentMethod::Card
                ? $this->finalTotalMinor
                : (int) ($this->tenderedMinor ?? 0);

            app(FinalizeTableSettlementAction::class)->execute(
                new FinalizeTableSettlementRequest(
                    shopId: $this->shopId,
                    tableSessionId: (int) $this->tableSessionId,
                    expectedSessionRevision: $this->expectedSessionRevision,
                    tenderedMinor: $tendered,
                    paymentMethod: $method,
                    actorUserId: (int) (auth()->id() ?? 0),
                )
            );

            $this->dispatch(
                'pos-settlement-completed',
                table_session_id: (int) $this->tableSessionId,
                open_receipt_preview: true,
            );
            $this->dispatch('pos-refresh-tiles');
            $this->uiState = 'success';
            $this->closeModal();
        } catch (InsufficientTenderException $e) {
            $this->uiState = 'failed';
            Notification::make()->title(__('rad_table.insufficient_tender'))->danger()->send();
        } catch (PendingOrdersRemainException $e) {
            $this->uiState = 'failed';
            Notification::make()->title(__('rad_table.cannot_close_with_unacked'))->danger()->send();
        } catch (SessionAlreadySettledException $e) {
            $this->uiState = 'failed';
            Notification::make()->title(__('rad_table.session_already_settled'))->warning()->send();
            $this->closeModal();
        } catch (RevisionConflictException $e) {
            $this->uiState = 'failed';
            Notification::make()->title(__('pos.data_stale_title'))->body(__('pos.revision_conflict_reload'))->warning()->send();
            $this->dispatch('pos-refresh-tiles');
            $this->closeModal();
        } catch (Throwable $e) {
            $this->uiState = 'failed';
            Notification::make()->title(__('pos.action_failed'))->body($e->getMessage())->danger()->send();
        }
    }

    public function confirmBypass(): void
    {
        if ($this->uiState === 'in_flight' || $this->tableSessionId === null) {
            return;
        }
        if ($this->bypassApproverStaffId === null || trim($this->bypassApproverPin) === '' || trim($this->bypassReason) === '') {
            Notification::make()->title(__('pos.action_failed'))->body(__('rad_table.bypass_reason_required'))->warning()->send();

            return;
        }

        $this->uiState = 'in_flight';

        try {
            $staff = Staff::query()
                ->with('jobLevel')
                ->where('shop_id', $this->shopId)
                ->whereKey((int) $this->bypassApproverStaffId)
                ->first();

            if ($staff === null) {
                throw new DiscountPinRejectedException(__('pos.discount_approver_not_found'));
            }

            $err = app(StaffPinAuthenticationService::class)->verify(
                staff: $staff,
                pin: $this->bypassApproverPin,
                context: 'pos-cloture-bypass',
                maxAttempts: 5,
                decaySeconds: 60,
            );
            if ($err !== null) {
                throw new DiscountPinRejectedException($err);
            }
            if ((int) ($staff->jobLevel?->level ?? 0) < 4) {
                throw new \RuntimeException(__('rad_table.manager_pin_required'));
            }

            $operatorUserId = (int) (auth()->id() ?? 0);
            app(FinalizeTableSettlementAction::class)->execute(
                new FinalizeTableSettlementRequest(
                    shopId: $this->shopId,
                    tableSessionId: (int) $this->tableSessionId,
                    expectedSessionRevision: $this->expectedSessionRevision,
                    tenderedMinor: $this->finalTotalMinor,
                    paymentMethod: PaymentMethod::BypassForced,
                    actorUserId: $operatorUserId,
                    printBypassed: true,
                    bypassReason: $this->bypassReason,
                    bypassedByUserId: $operatorUserId,
                )
            );

            $this->dispatch(
                'pos-settlement-completed',
                table_session_id: (int) $this->tableSessionId,
                open_receipt_preview: false,
            );
            $this->dispatch('pos-refresh-tiles');
            $this->uiState = 'success';
            $this->closeModal();
        } catch (DiscountPinRejectedException $e) {
            $this->uiState = 'failed';
            Notification::make()->title(__('pos.action_failed'))->body($e->getMessage())->danger()->send();
        } catch (Throwable $e) {
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
        $this->changeMinor = 0;

        return true;
    }

    private function recomputeChange(): void
    {
        $tendered = (int) ($this->tenderedMinor ?? 0);
        $this->changeMinor = max(0, $tendered - $this->finalTotalMinor);
    }

    public function getApproverOptionsProperty(): array
    {
        return Staff::query()
            ->where('shop_id', $this->shopId)
            ->where('is_active', true)
            ->where('is_manager', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Staff $s): array => ['id' => (int) $s->id, 'name' => (string) $s->name])
            ->all();
    }
}
