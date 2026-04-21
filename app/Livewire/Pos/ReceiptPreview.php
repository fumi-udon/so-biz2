<?php

namespace App\Livewire\Pos;

use App\Actions\Pos\Print\DispatchPrintJobAction;
use App\Actions\Pos\Print\DispatchPrintJobRequest;
use App\Actions\RadTable\RecordAdditionPrintForSessionAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PrintIntent;
use App\Enums\TableSessionStatus;
use App\Exceptions\RevisionConflictException;
use App\Models\OrderLine;
use App\Models\PosOrder;
use App\Models\Shop;
use App\Models\TableSession;
use App\Models\TableSessionSettlement;
use App\Support\MenuItemMoney;
use App\Support\Pos\EpsonReceiptXmlBuilder;
use App\Support\Pos\Print\AsciiPrintNormalizer;
use App\Support\Pos\Print\ReceiptPreviewData;
use App\Support\Pos\StaffTableSettlementPricing;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Throwable;

class ReceiptPreview extends Component
{
    use AsciiPrintNormalizer;

    public int $shopId = 0;

    public int $tableSessionId = 0;

    public string $intent = 'addition';

    public int $expectedSessionRevision = 0;

    /** @var 'idle'|'in_flight'|'success'|'failed' */
    public string $uiState = 'idle';

    protected ReceiptPreviewData $dto;

    /** @var Collection<int, PosOrder> */
    protected Collection $orders;

    protected int $orderDiscountMinor = 0;

    protected int $roundingAdjustmentMinor = 0;

    protected PrintIntent $printIntent;

    public function mount(int $shopId, int $tableSessionId, string $intent, int $expectedSessionRevision): void
    {
        $this->shopId = $shopId;
        $this->tableSessionId = $tableSessionId;
        $this->expectedSessionRevision = $expectedSessionRevision;

        $this->printIntent = PrintIntent::tryFrom($intent) ?? PrintIntent::Addition;
        $this->intent = $this->printIntent->value;

        $this->hydratePreviewData();
    }

    /**
     * サブシーケントリクエストではスナップショットに public のみ載るため、
     * protected の dto / printIntent / orders は毎回ここから復元する。
     */
    public function hydrate(): void
    {
        if (! isset($this->dto)) {
            $this->hydratePreviewData();
        }
    }

    public function printFromPreview(): void
    {
        if ($this->uiState === 'in_flight') {
            return;
        }

        $this->uiState = 'in_flight';

        try {
            $xmlPayload = [
                'shop_name' => (string) $this->printData['shop_name'],
                'table_label' => (string) $this->printData['table_label'],
                'intent' => $this->printIntent,
                'lines' => $this->printData['lines'],
                'subtotal_minor' => (int) $this->printData['subtotal_minor'],
                'order_discount_minor' => $this->orderDiscountMinor,
                'rounding_adjustment_minor' => $this->roundingAdjustmentMinor,
                'final_total_minor' => (int) $this->printData['total_minor'],
                'printed_at' => (string) $this->printData['printed_at'],
            ];
            if ($this->dto->originalSettledAt !== null && $this->dto->originalSettledAt !== '') {
                $xmlPayload['duplicate_original_at'] = __('pos.duplicata_original_settled_line', [
                    'at' => $this->dto->originalSettledAt,
                ]);
            }
            $xmlPayload = array_merge($xmlPayload, $this->receiptTenderLinesForPrinter());

            $xml = app(EpsonReceiptXmlBuilder::class)->build($xmlPayload);

            $payloadMeta = [
                'source' => 'receipt_preview',
                'intent' => $this->intent,
            ];
            $settlementId = $this->latestSettlementIdForMeta();
            if ($settlementId !== null) {
                $payloadMeta['settlement_id'] = $settlementId;
            }

            $idempotencyNonce = $this->printIntent === PrintIntent::Copy ? (string) Str::uuid() : null;
            if ($idempotencyNonce !== null) {
                $payloadMeta['nonce'] = $idempotencyNonce;
            }

            $job = app(DispatchPrintJobAction::class)->execute(new DispatchPrintJobRequest(
                shopId: $this->shopId,
                tableSessionId: $this->tableSessionId,
                intent: $this->printIntent,
                sessionRevisionSnapshot: $this->expectedSessionRevision,
                payloadXml: $xml,
                payloadMeta: $payloadMeta,
                idempotencyNonce: $idempotencyNonce,
            ));

            if ($this->printIntent === PrintIntent::Addition) {
                app(RecordAdditionPrintForSessionAction::class)->execute(
                    $this->shopId,
                    $this->tableSessionId,
                    $this->expectedSessionRevision,
                );
                $this->expectedSessionRevision++;
            }

            $this->dispatch(
                'pos-trigger-print',
                printJobId: (int) $job->id,
                jobKey: (string) $job->idempotency_key,
                xml: $xml,
                opts: ['timeoutMs' => 10_000],
            );

            $this->uiState = 'success';
            $this->dispatch(
                'receipt-preview-printed',
                table_session_id: $this->tableSessionId,
                intent: $this->intent,
            );
        } catch (RevisionConflictException $e) {
            $this->uiState = 'failed';
            Notification::make()
                ->title(__('pos.data_stale_title'))
                ->body(__('pos.revision_conflict_reload'))
                ->warning()
                ->send();
        } catch (Throwable $e) {
            $this->uiState = 'failed';
            Notification::make()
                ->title(__('pos.action_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            if ($this->uiState !== 'success') {
                $this->uiState = 'idle';
            }
        }
    }

    public function closePreview(): void
    {
        $this->dispatch('close-receipt');
    }

    #[Computed]
    public function viewData(): array
    {
        return [
            'intent' => $this->dto->intent,
            'title' => $this->intentTitle($this->printIntent),
            'shop_name' => $this->dto->shopName,
            'table_label' => $this->dto->tableLabel,
            'lines' => $this->dto->lines,
            'subtotal_minor' => $this->dto->subtotalMinor,
            'total_minor' => $this->dto->totalMinor,
            'printed_at' => $this->dto->printedAt,
            'original_settled_at' => $this->dto->originalSettledAt,
            'order_discount_minor' => $this->orderDiscountMinor,
            'rounding_adjustment_minor' => $this->roundingAdjustmentMinor,
        ];
    }

    #[Computed]
    public function printData(): array
    {
        return [
            'intent' => $this->dto->intent,
            'title' => $this->toAsciiUpper($this->intentTitle($this->printIntent)),
            'shop_name' => $this->toAsciiUpper($this->dto->shopName),
            'table_label' => $this->toAsciiUpper($this->dto->tableLabel),
            'lines' => array_map(fn (array $line): array => [
                'qty' => (int) $line['qty'],
                'name' => $this->toAsciiUpper((string) $line['name']),
                'amount_minor' => (int) $line['amount_minor'],
            ], $this->dto->lines),
            'subtotal_minor' => $this->dto->subtotalMinor,
            'total_minor' => $this->dto->totalMinor,
            'printed_at' => $this->toAsciiUpper($this->dto->printedAt),
        ];
    }

    public function render()
    {
        return view('livewire.pos.receipt-preview');
    }

    public function formatMinor(int $minor): string
    {
        return MenuItemMoney::formatMinorForDisplay($minor);
    }

    private function hydratePreviewData(): void
    {
        $this->printIntent = PrintIntent::tryFrom($this->intent) ?? PrintIntent::Addition;

        $session = TableSession::query()
            ->where('shop_id', $this->shopId)
            ->whereKey($this->tableSessionId)
            ->with('restaurantTable:id,name')
            ->firstOrFail();

        $this->orders = PosOrder::query()
            ->where('shop_id', $this->shopId)
            ->where('table_session_id', $this->tableSessionId)
            ->where('status', '!=', OrderStatus::Voided)
            ->with('lines:id,order_id,qty,line_total_minor,line_discount_minor,snapshot_name')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $settlement = TableSessionSettlement::query()
            ->where('table_session_id', $this->tableSessionId)
            ->orderByDesc('id')
            ->first();

        $useSettlementSsot = $settlement !== null
            && (
                $this->printIntent === PrintIntent::Copy
                || ($this->printIntent === PrintIntent::Receipt && $session->status === TableSessionStatus::Closed)
            );

        $pricing = StaffTableSettlementPricing::calculateFromPosOrders(
            $this->orders,
            (int) $session->restaurant_table_id,
        );

        $shopName = (string) (Shop::query()->whereKey($this->shopId)->value('name') ?? '');
        $tableName = (string) ($session->restaurantTable?->name ?? '');

        $tableLabel = $tableName !== ''
            ? $tableName
            : 'Session #'.$this->tableSessionId;

        if (StaffTableSettlementPricing::isStaffMealTableId((int) $session->restaurant_table_id)) {
            $staffName = is_string($session->staff_name) ? trim($session->staff_name) : '';
            if ($staffName !== '') {
                $tableLabel = $staffName;
            }
        }

        $lines = [];
        foreach ($this->orders as $order) {
            /** @var Collection<int, OrderLine> $orderLines */
            $orderLines = $order->lines->sortBy('id')->values();
            foreach ($orderLines as $line) {
                $lines[] = [
                    'qty' => (int) $line->qty,
                    'name' => (string) $line->snapshot_name,
                    'amount_minor' => max(0, (int) $line->line_total_minor - (int) ($line->line_discount_minor ?? 0)),
                ];
            }
        }

        $lineSumMinor = (int) array_sum(array_column($lines, 'amount_minor'));

        $originalSettledAt = null;
        if ($settlement !== null && $settlement->settled_at !== null) {
            $originalSettledAt = $settlement->settled_at->timezone(config('app.timezone'))->format('Y-m-d H:i');
        }

        $printedAt = Carbon::now()->format('Y-m-d H:i');

        if ($useSettlementSsot) {
            $this->orderDiscountMinor = (int) $settlement->order_discount_applied_minor;
            $this->roundingAdjustmentMinor = (int) $settlement->rounding_adjustment_minor;

            if ($lineSumMinor !== (int) $settlement->order_subtotal_minor) {
                Log::warning('receipt_preview.settlement_line_subtotal_mismatch', [
                    'shop_id' => $this->shopId,
                    'table_session_id' => $this->tableSessionId,
                    'intent' => $this->printIntent->value,
                    'line_sum_minor' => $lineSumMinor,
                    'settlement_order_subtotal_minor' => (int) $settlement->order_subtotal_minor,
                    'settlement_id' => (int) $settlement->id,
                ]);
            }

            $this->dto = new ReceiptPreviewData(
                intent: $this->intent,
                shopName: $shopName,
                tableLabel: $tableLabel,
                lines: $lines,
                subtotalMinor: (int) $settlement->order_subtotal_minor,
                totalMinor: (int) $settlement->final_total_minor,
                printedAt: $printedAt,
                originalSettledAt: $originalSettledAt,
            );

            return;
        }

        $this->orderDiscountMinor = (int) $pricing->orderDiscountAppliedMinor;
        $this->roundingAdjustmentMinor = (int) $pricing->roundingAdjustmentMinor;

        if ($this->printIntent === PrintIntent::Copy && $settlement === null) {
            Log::warning('receipt_preview.copy_without_settlement', [
                'shop_id' => $this->shopId,
                'table_session_id' => $this->tableSessionId,
            ]);
        }

        $this->dto = new ReceiptPreviewData(
            intent: $this->intent,
            shopName: $shopName,
            tableLabel: $tableLabel,
            lines: $lines,
            subtotalMinor: (int) $pricing->orderSubtotalMinor,
            totalMinor: (int) $pricing->finalTotalMinor,
            printedAt: $printedAt,
            originalSettledAt: $settlement !== null ? $originalSettledAt : null,
        );
    }

    private function intentTitle(PrintIntent $intent): string
    {
        return match ($intent) {
            PrintIntent::Addition => 'ADDITION',
            PrintIntent::Receipt => 'FACTURE',
            PrintIntent::Copy => 'DUPLICATA',
            PrintIntent::StaffCopy => 'STAFF COPY',
        };
    }

    /**
     * @return array{tendered_minor?: int, change_minor?: int}
     */
    private function receiptTenderLinesForPrinter(): array
    {
        if (! in_array($this->printIntent, [PrintIntent::Receipt, PrintIntent::Copy], true)) {
            return [];
        }

        $settlement = TableSessionSettlement::query()
            ->where('table_session_id', $this->tableSessionId)
            ->orderByDesc('id')
            ->first();

        if ($settlement === null) {
            return [];
        }

        if ($settlement->payment_method === PaymentMethod::Cash) {
            return [
                'tendered_minor' => (int) $settlement->tendered_minor,
                'change_minor' => (int) $settlement->change_minor,
            ];
        }

        return [];
    }

    private function latestSettlementIdForMeta(): ?int
    {
        if (! in_array($this->printIntent, [PrintIntent::Receipt, PrintIntent::Copy], true)) {
            return null;
        }

        $id = TableSessionSettlement::query()
            ->where('table_session_id', $this->tableSessionId)
            ->orderByDesc('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
}
