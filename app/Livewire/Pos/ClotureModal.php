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

    public function setTendered(int $minor): void
    {
        $this->tenderedMinor = max(0, $minor);
        $this->tenderedDtInput = $this->tenderedMinor > 0
            ? MenuItemMoney::minorToDtInputString($this->tenderedMinor)
            : '';
        $this->recomputeChange();
    }

    public function updatedTenderedDtInput(): void
    {
        $this->tenderedMinor = MenuItemMoney::parseDtInputToMinor($this->tenderedDtInput);
        $this->recomputeChange();
    }

    public function confirm(): void
    {
        if ($this->uiState === 'in_flight' || $this->tableSessionId === null) {
            return;
        }
        $this->uiState = 'in_flight';

        try {
            $tendered = (int) ($this->tenderedMinor ?? 0);

            app(FinalizeTableSettlementAction::class)->execute(
                new FinalizeTableSettlementRequest(
                    shopId: $this->shopId,
                    tableSessionId: (int) $this->tableSessionId,
                    expectedSessionRevision: $this->expectedSessionRevision,
                    tenderedMinor: $tendered,
                    paymentMethod: PaymentMethod::Cash,
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

    public function formatMinor(int $minor): string
    {
        return MenuItemMoney::formatMinorForDisplay($minor);
    }

    public function getTenderedDisplayMinorProperty(): int
    {
        return max(0, (int) ($this->tenderedMinor ?? 0));
    }

    public function getChangeToneProperty(): string
    {
        if ($this->changeMinor < 0) {
            return 'short';
        }
        if ($this->changeMinor > 0) {
            return 'positive';
        }

        return 'neutral';
    }

    public function formatSignedMinor(int $minor): string
    {
        if ($minor < 0) {
            return '− '.$this->formatMinor(abs($minor));
        }

        return $this->formatMinor($minor);
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

    private function recomputeChange(): void
    {
        $tendered = (int) ($this->tenderedMinor ?? 0);
        $this->changeMinor = $tendered - $this->finalTotalMinor;
    }
}
