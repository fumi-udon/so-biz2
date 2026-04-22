<?php

namespace App\Actions\GuestOrder;

use App\Enums\OrderLineStatus;
use App\Enums\OrderStatus;
use App\Events\Pos\PosOrderPlaced;
use App\Exceptions\GuestOrderForbiddenException;
use App\Exceptions\GuestOrderValidationException;
use App\Models\GuestOrderIdempotency;
use App\Models\MenuItem;
use App\Models\OrderLine;
use App\Models\PosOrder;
use App\Models\RestaurantTable;
use App\Models\Shop;
use App\Models\TableSession;
use App\Services\Pos\PosLineComputationService;
use App\Services\Pos\TableSessionLifecycleService;
use App\Support\Pos\Receipt\ReceiptTaxMath;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class SubmitGuestOrderAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(string $tenantSlug, string $tableToken, array $payload): SubmitGuestOrderResult
    {
        $this->validatePayloadShape($payload, $tenantSlug, $tableToken);

        return DB::transaction(function () use ($tenantSlug, $tableToken, $payload): SubmitGuestOrderResult {
            $lineComputation = app(PosLineComputationService::class);
            $shop = Shop::query()
                ->where('slug', $tenantSlug)
                ->where('is_active', true)
                ->first();

            if ($shop === null) {
                throw new GuestOrderForbiddenException(__('Shop not found.'));
            }

            $table = RestaurantTable::query()
                ->where('shop_id', $shop->id)
                ->where('qr_token', $tableToken)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if ($table === null) {
                throw new GuestOrderValidationException(__('Unknown table.'));
            }

            // Frictionless guest model: auto-create a fresh Active TableSession
            // when none exists (e.g. empty table, post-checkout, only Closed history).
            // `getOrCreateActiveSession` already takes a `lockForUpdate` on existing
            // active rows; we are inside DB::transaction with the table row locked
            // above, so concurrent QR scans on the same table cannot create duplicates.
            $session = app(TableSessionLifecycleService::class)->getOrCreateActiveSession($table);
            $session = TableSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            $idempotencyKey = trim((string) ($payload['idempotencyKey'] ?? ''));
            $existingIdempotency = GuestOrderIdempotency::query()
                ->where('table_session_id', $session->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existingIdempotency !== null) {
                return new SubmitGuestOrderResult(posOrderId: $existingIdempotency->pos_order_id);
            }

            /** @var list<array<string, mixed>> $linesIn */
            $linesIn = $payload['lines'];
            $computedLines = [];
            $orderTotalMinor = 0;
            $snapshotVat = ReceiptTaxMath::defaultVatPercent();

            foreach ($linesIn as $row) {
                if (! is_array($row)) {
                    throw new GuestOrderValidationException(__('Invalid order line payload.'));
                }

                $item = $this->resolveMenuItem($shop->id, $row);
                $item = $this->lockMenuItemAndAssertActive($item, $shop->id);
                $qty = max(1, (int) ($row['qty'] ?? 1));

                $unitPriceMinor = $lineComputation->computeUnitPriceMinor($item, $row);
                $lineTotalMinor = $unitPriceMinor * $qty;
                $orderTotalMinor += $lineTotalMinor;

                $snapshotName = (string) $item->name;
                $kitchen = $item->kitchen_name;
                $snapshotKitchen = ($kitchen !== null && trim((string) $kitchen) !== '')
                    ? (string) $kitchen
                    : $snapshotName;

                $computedLines[] = [
                    'menu_item_id' => $item->id,
                    'qty' => $qty,
                    'unit_price_minor' => $unitPriceMinor,
                    'line_total_minor' => $lineTotalMinor,
                    'vat_rate_percent' => $snapshotVat,
                    'snapshot_name' => $snapshotName,
                    'snapshot_kitchen_name' => $snapshotKitchen,
                    'snapshot_options_payload' => $lineComputation->buildSnapshotOptionsPayload($item, $row),
                    'status' => OrderLineStatus::Placed,
                ];
            }

            $order = PosOrder::query()->create([
                'shop_id' => $shop->id,
                'table_session_id' => $session->id,
                'status' => OrderStatus::Placed,
                'total_price_minor' => $orderTotalMinor,
                'placed_at' => now(),
            ]);

            foreach ($computedLines as $line) {
                OrderLine::query()->create([
                    'order_id' => $order->id,
                    'menu_item_id' => $line['menu_item_id'],
                    'qty' => $line['qty'],
                    'unit_price_minor' => $line['unit_price_minor'],
                    'line_total_minor' => $line['line_total_minor'],
                    'vat_rate_percent' => $line['vat_rate_percent'] ?? $snapshotVat,
                    'snapshot_name' => $line['snapshot_name'],
                    'snapshot_kitchen_name' => $line['snapshot_kitchen_name'],
                    'snapshot_options_payload' => $line['snapshot_options_payload'],
                    'status' => $line['status'],
                ]);
            }

            try {
                GuestOrderIdempotency::query()->create([
                    'table_session_id' => $session->id,
                    'idempotency_key' => $idempotencyKey,
                    'pos_order_id' => $order->id,
                ]);
            } catch (QueryException $e) {
                if (! $this->isUniqueConstraintViolation($e)) {
                    throw $e;
                }
                $row = GuestOrderIdempotency::query()
                    ->where('table_session_id', $session->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($row !== null) {
                    return new SubmitGuestOrderResult(posOrderId: $row->pos_order_id);
                }

                throw $e;
            }

            $eventShopId = (int) $shop->id;
            $eventTableId = (int) $table->id;
            $eventSessionId = (int) $session->id;
            $eventOrderId = (int) $order->id;
            TableSession::query()->whereKey($session->id)->increment('session_revision');

            DB::afterCommit(function () use ($eventShopId, $eventTableId, $eventSessionId, $eventOrderId): void {
                PosOrderPlaced::dispatch($eventShopId, $eventTableId, $eventSessionId, $eventOrderId);
            });

            return new SubmitGuestOrderResult(posOrderId: $order->id);
        });
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate')
            || str_contains($message, 'integrity constraint');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validatePayloadShape(array $payload, string $tenantSlug, string $tableToken): void
    {
        if (($payload['schemaVersion'] ?? null) !== 1) {
            throw new GuestOrderValidationException(__('Invalid payload version.'));
        }

        if (($payload['intent'] ?? '') !== 'submit_to_table_pos') {
            throw new GuestOrderValidationException(__('Invalid intent.'));
        }

        $ctx = $payload['context'] ?? [];
        if (! is_array($ctx)) {
            throw new GuestOrderValidationException(__('Invalid context.'));
        }

        if (($ctx['tenantSlug'] ?? '') !== $tenantSlug || ($ctx['tableToken'] ?? '') !== $tableToken) {
            throw new GuestOrderValidationException(__('Session context mismatch.'));
        }

        $lines = $payload['lines'] ?? null;
        if (! is_array($lines) || $lines === []) {
            throw new GuestOrderValidationException(__('Cart is empty.'));
        }

        $rawKey = $payload['idempotencyKey'] ?? '';
        $idempotencyKey = is_string($rawKey) ? trim($rawKey) : '';
        if ($idempotencyKey === '' || strlen($idempotencyKey) < 8 || strlen($idempotencyKey) > 128) {
            throw new GuestOrderValidationException(__('guest.invalid_idempotency_key'));
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveMenuItem(int $shopId, array $row): MenuItem
    {
        $itemId = isset($row['itemId']) ? trim((string) $row['itemId']) : '';
        if ($itemId === '') {
            throw new GuestOrderValidationException(__('Missing item id.'));
        }

        $base = MenuItem::query()->where('shop_id', $shopId);

        if (ctype_digit($itemId)) {
            $byId = (clone $base)->whereKey((int) $itemId)->first();
            if ($byId !== null) {
                return $byId;
            }
        }

        $bySlug = (clone $base)->where('slug', $itemId)->first();
        if ($bySlug === null) {
            throw new GuestOrderValidationException(__('Unknown menu item.'));
        }

        return $bySlug;
    }

    /**
     * Re-read the row with a lock to avoid time-of-check/time-of-use
     * (e.g. staff deactivates the item after resolve but before commit).
     */
    private function lockMenuItemAndAssertActive(MenuItem $item, int $shopId): MenuItem
    {
        $locked = MenuItem::query()
            ->where('shop_id', $shopId)
            ->whereKey($item->id)
            ->lockForUpdate()
            ->first();

        if ($locked === null) {
            throw new GuestOrderValidationException(__('Unknown menu item.'));
        }

        if (! $locked->is_active) {
            throw new GuestOrderValidationException(__('guest.item_unavailable'));
        }

        return $locked;
    }
}
