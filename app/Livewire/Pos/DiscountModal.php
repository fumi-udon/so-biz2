<?php

namespace App\Livewire\Pos;

use App\Actions\Pos\Discount\RecordDiscountRequest;
use App\Actions\Pos\Discount\RecordItemDiscountAction;
use App\Actions\Pos\Discount\RecordOrderDiscountAction;
use App\Actions\Pos\Discount\RecordStaffDiscountAction;
use App\Exceptions\Pos\DiscountPinRejectedException;
use App\Exceptions\Pos\SessionAlreadySettledException;
use App\Models\Staff;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Discount application modal. Handles all three scopes via a single UI:
 *
 *   scope=item   → RecordItemDiscountAction  (targetId=order_line_id)
 *   scope=order  → RecordOrderDiscountAction (targetId=order_id)
 *   scope=staff  → RecordStaffDiscountAction (targetId=table_session_id,
 *                  fixed 50%, only enabled on staff tables 100-109)
 *
 * Keeps the Livewire layer thin: PIN verification, DB writes, and audit
 * logging all happen inside the Action. Each submission generates a fresh
 * idempotency key so accidental double-clicks still produce exactly one
 * audit row (UNIQUE constraint guards against malicious replays).
 */
class DiscountModal extends Component
{
    #[Locked]
    public int $shopId = 0;

    public bool $open = false;

    /** @var 'idle'|'in_flight'|'failed' */
    public string $uiState = 'idle';

    /** @var 'item'|'order'|'staff' */
    public string $scope = 'item';

    public ?int $targetId = null;

    public ?int $approverStaffId = null;

    public string $approverPin = '';

    public string $reason = '';

    /** @var 'flat'|'percent' */
    public string $mode = 'flat';

    public ?int $flatMinor = null;

    public ?int $percentBasisPoints = null;

    private string $idempotencyKey = '';

    public function mount(int $shopId): void
    {
        $this->shopId = $shopId;
    }

    #[On('pos-discount-open')]
    public function onOpen(
        mixed $shop_id = null,
        mixed $scope = null,
        mixed $target_id = null,
    ): void {
        if ((int) $shop_id !== $this->shopId) {
            return;
        }
        $scopeStr = is_string($scope) ? $scope : '';
        if (! in_array($scopeStr, ['item', 'order', 'staff'], true)) {
            return;
        }
        $tid = is_numeric($target_id) ? (int) $target_id : 0;
        if ($tid < 1) {
            return;
        }

        $this->resetExcept(['shopId']);
        $this->scope = $scopeStr;
        $this->targetId = $tid;
        $this->mode = $scopeStr === 'staff' ? 'percent' : 'flat';
        if ($scopeStr === 'staff') {
            $this->percentBasisPoints = 5_000;
        }
        $this->idempotencyKey = (string) Str::uuid();
        $this->uiState = 'idle';
        $this->open = true;
    }

    public function closeModal(): void
    {
        $this->open = false;
        $this->uiState = 'idle';
    }

    public function submit(): void
    {
        if ($this->uiState === 'in_flight' || $this->targetId === null || $this->approverStaffId === null) {
            return;
        }
        $this->uiState = 'in_flight';

        try {
            $req = new RecordDiscountRequest(
                shopId: $this->shopId,
                operatorUserId: (int) (auth()->id() ?? 0),
                approverStaffId: (int) $this->approverStaffId,
                approverPin: $this->approverPin,
                reason: $this->reason,
                idempotencyKey: $this->idempotencyKey !== '' ? $this->idempotencyKey : (string) Str::uuid(),
                flatMinor: $this->mode === 'flat' ? max(0, (int) ($this->flatMinor ?? 0)) : null,
                percentBasisPoints: $this->mode === 'percent' ? max(0, min(10_000, (int) ($this->percentBasisPoints ?? 0))) : null,
            );

            match ($this->scope) {
                'item' => app(RecordItemDiscountAction::class)->execute($req, (int) $this->targetId),
                'order' => app(RecordOrderDiscountAction::class)->execute($req, (int) $this->targetId),
                'staff' => app(RecordStaffDiscountAction::class)->execute($req, (int) $this->targetId),
            };

            $this->dispatch('pos-discount-applied', scope: $this->scope, target_id: (int) $this->targetId);
            $this->dispatch('pos-refresh-tiles');
            $this->closeModal();
        } catch (DiscountPinRejectedException $e) {
            $this->uiState = 'failed';
            Notification::make()->title(__('pos.action_failed'))->body($e->getMessage())->danger()->send();
        } catch (SessionAlreadySettledException $e) {
            $this->uiState = 'failed';
            Notification::make()->title(__('rad_table.session_already_settled'))->warning()->send();
            $this->closeModal();
        } catch (Throwable $e) {
            $this->uiState = 'failed';
            Notification::make()->title(__('pos.action_failed'))->body($e->getMessage())->danger()->send();
        }
    }

    /**
     * @return list<array{id:int,name:string,level:int}>
     */
    public function getApproverOptionsProperty(): array
    {
        return Staff::query()
            ->with('jobLevel')
            ->where('shop_id', $this->shopId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'job_level_id'])
            ->filter(fn (Staff $s): bool => (int) ($s->jobLevel?->level ?? 0) >= 3)
            ->map(fn (Staff $s): array => [
                'id' => (int) $s->id,
                'name' => (string) $s->name,
                'level' => (int) ($s->jobLevel?->level ?? 0),
            ])
            ->values()
            ->all();
    }

    public function render()
    {
        return view('livewire.pos.discount-modal');
    }
}
