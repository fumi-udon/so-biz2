<?php

namespace App\Services\Kds;

use App\Domains\Pos\Tables\TableCategory;
use App\Enums\OrderLineStatus;
use App\Enums\TableSessionStatus;
use App\Models\OrderLine;
use App\Models\TableSession;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

final class KdsQueryService
{
    public function tableCategoryForTableId(int $tableId): ?TableCategory
    {
        return TableCategory::tryResolveFromId($tableId);
    }

    public function tableCategoryPriorityForKds(int $tableId): int
    {
        return match ($this->tableCategoryForTableId($tableId)) {
            TableCategory::Customer => 0,
            TableCategory::Staff, TableCategory::Takeaway => 1,
            default => 2,
        };
    }

    /**
     * Keyset Pull（§10）: `OFFSET` は使用しない。
     *
     * @return Collection<int, OrderLine>
     */
    public function pullActiveTickets(
        int $shopId,
        ?string $cursorUpdatedAt = null,
        ?int $cursorId = null,
        int $limit = 200,
    ): Collection {
        $limit = max(1, min(500, $limit));

        $q = OrderLine::query()
            ->where('shop_id', $shopId)
            ->whereIn('status', [
                OrderLineStatus::Confirmed,
                OrderLineStatus::Cooking,
            ])
            ->with(['menuItem'])
            ->orderBy('updated_at')
            ->orderBy('id');

        if ($cursorUpdatedAt !== null && $cursorId !== null) {
            $t = Carbon::parse($cursorUpdatedAt);
            $q->whereRaw(
                '(updated_at > ?) OR (updated_at = ? AND id > ?)',
                [$t, $t, $cursorId]
            );
        }

        return $q->limit($limit)->get();
    }

    /**
     * KDS ダッシュボード用: Active セッションのうち、仮想バッチキー
     *（COALESCE(TRIM(kds_ticket_batch_id), CONCAT('o:', order_id))）単位で
     * Confirmed / Cooking が 1 件でも残っているバッチに属する行を返す。
     * そのバッチでは Served 行も含め（Cancelled は除外）、
     * バッチ内がすべて Served 等で未完了がなくなったバッチは結果に含まれない。
     *
     * @return Collection<int, OrderLine>
     */
    public function pullActiveSessionTicketsForDashboard(int $shopId): Collection
    {
        $q = OrderLine::query()
            ->where('order_lines.shop_id', $shopId)
            ->whereNot('order_lines.status', OrderLineStatus::Cancelled)
            ->whereIn('order_lines.status', [
                OrderLineStatus::Confirmed,
                OrderLineStatus::Cooking,
                OrderLineStatus::Served,
            ])
            ->whereHas(
                'order.tableSession',
                fn ($q) => $q->where('status', TableSessionStatus::Active)
            )
            ->whereExists(function ($query): void {
                $p = $query->getConnection()->getTablePrefix();
                $olPending = '`'.$p.'ol_pending`';
                $olOuter = '`'.$p.'order_lines`';
                $query->selectRaw('1')
                    ->from('order_lines as ol_pending')
                    ->join('orders as po_pending', 'ol_pending.order_id', '=', 'po_pending.id')
                    ->join('orders as po_self', 'po_self.id', '=', 'order_lines.order_id')
                    ->whereColumn('po_pending.table_session_id', 'po_self.table_session_id')
                    ->whereColumn('ol_pending.shop_id', 'order_lines.shop_id')
                    ->whereRaw(
                        "COALESCE(NULLIF(TRIM({$olPending}.`kds_ticket_batch_id`), ''), CONCAT('o:', {$olPending}.`order_id`)) = COALESCE(NULLIF(TRIM({$olOuter}.`kds_ticket_batch_id`), ''), CONCAT('o:', {$olOuter}.`order_id`))"
                    )
                    ->whereIn('ol_pending.status', [
                        OrderLineStatus::Confirmed,
                        OrderLineStatus::Cooking,
                    ]);
            })
            ->with([
                'menuItem:id,menu_category_id,name,kitchen_name,role_category,sort_order',
                'menuItem.menuCategory:id,sort_order',
                'order:id,table_session_id,shop_id,placed_at',
                'order.tableSession:id,restaurant_table_id,shop_id,staff_name,customer_name',
                'order.tableSession.restaurantTable:id,shop_id,name,sort_order',
            ]);

        return $q
            ->orderBy('id')
            ->get();
    }

    /**
     * 当日開始のセッションのうち、少なくとも 1 件の Served 行を持つものを返す。
     * Active / Closed の両方を含む（会計済みも当日履歴として参照するため）。
     *
     * @return Collection<int, TableSession>
     */
    public function pullServedSessionsForToday(int $shopId): Collection
    {
        return TableSession::query()
            ->where('shop_id', $shopId)
            ->where('opened_at', '>=', now()->startOfDay())
            ->whereIn('status', [
                TableSessionStatus::Active,
                TableSessionStatus::Closed,
            ])
            ->whereHas(
                'posOrders.lines',
                fn ($q) => $q->where('status', OrderLineStatus::Served)
            )
            ->with([
                'restaurantTable:id,shop_id,name,sort_order',
                'posOrders.lines' => function ($q): void {
                    $q->where('status', OrderLineStatus::Served)
                        ->orderBy('id')
                        ->with(['menuItem:id,name,kitchen_name']);
                },
            ])
            ->orderByDesc('opened_at')
            ->get();
    }
}
