<?php

namespace App\Actions\RadTable;

use App\Enums\OrderLineStatus;
use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Exceptions\RevisionConflictException;
use App\Models\OrderLine;
use App\Models\PosOrder;
use App\Models\TableSession;
use App\Services\Kds\KdsBroadcastService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class RecuPlacedOrdersForSessionAction
{
    public function execute(int $shopId, int $tableSessionId, int $expectedSessionRevision): int
    {
        return (int) DB::transaction(function () use ($shopId, $tableSessionId, $expectedSessionRevision): int {
            $session = TableSession::query()
                ->whereKey($tableSessionId)
                ->where('shop_id', $shopId)
                ->where('status', TableSessionStatus::Active)
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                throw new RuntimeException(__('rad_table.active_session_not_found'));
            }

            if ((int) $session->session_revision !== $expectedSessionRevision) {
                throw new RevisionConflictException(
                    resource: 'table_session',
                    id: (int) $session->id,
                    currentRevision: (int) $session->session_revision,
                    clientSentRevision: $expectedSessionRevision,
                );
            }

            $placedOrderIds = PosOrder::query()
                ->where('table_session_id', $session->id)
                ->where('status', OrderStatus::Placed)
                ->pluck('id');

            $n = PosOrder::query()
                ->where('table_session_id', $session->id)
                ->where('status', OrderStatus::Placed)
                ->update(['status' => OrderStatus::Confirmed]);

            if ($placedOrderIds->isNotEmpty()) {
                // 同一の Validate 操作で確定した全行に同一キーを付与（KDS は1列＝1チケットのため必須）。
                // 複数 PosOrder が1操作で一括 Confirmed される場合、order_id では列が分裂する。
                $kdsTicketBatchId = (string) Str::uuid();
                OrderLine::query()
                    ->whereIn('order_id', $placedOrderIds)
                    ->where('status', OrderLineStatus::Placed)
                    ->update([
                        'status' => OrderLineStatus::Confirmed,
                        'line_revision' => DB::raw('line_revision + 1'),
                        'kds_ticket_batch_id' => $kdsTicketBatchId,
                    ]);
            }

            $session->increment('session_revision');

            // Bistro 最適化:
            //   旧 Outbox + Job 構成を撤回し、`afterCommit` で
            //   `KdsBroadcastService::notifyOrderConfirmed()` を呼ぶ。
            //   さらに Service 内部で `dispatch()->afterResponse()` を行うため、
            //   ホール iPad の HTTP レスポンスは Pusher API レイテンシに一切引きずられない。
            //   万一 Pusher が落ちても KDS は wire:poll.10s で 10 秒以内に追従する。
            if ($placedOrderIds->isNotEmpty()) {
                DB::afterCommit(static function () use ($shopId): void {
                    app(KdsBroadcastService::class)->notifyOrderConfirmed($shopId);
                });
            }

            return $n;
        });
    }
}
