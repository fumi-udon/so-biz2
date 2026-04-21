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
    private const int TRANSIENT_SERVED_SECONDS = 6;

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
     * KDS ダッシュボード用: 現在「Active」なテーブルセッション配下の
     * Confirmed / Cooking / Served 行を一括取得（Bistro 規模なので全件で十分）。
     *
     * Served も含めるのは、タップで赤→緑に変化したチケットが
     * 直後に消えず「列の一番下」に残る UX を成立させるため。
     * セッションが Closed になれば自然に画面から消える。
     *
     * @return Collection<int, OrderLine>
     */
    public function pullActiveSessionTicketsForDashboard(int $shopId): Collection
    {
        $servedCutoff = now()->subSeconds(self::TRANSIENT_SERVED_SECONDS);

        return OrderLine::query()
            ->where('order_lines.shop_id', $shopId)
            ->where(static function ($q) use ($servedCutoff): void {
                $q->whereIn('status', [
                    OrderLineStatus::Confirmed,
                    OrderLineStatus::Cooking,
                ])
                    ->orWhere(static function ($served) use ($servedCutoff): void {
                        $served->where('status', OrderLineStatus::Served)
                            ->where('updated_at', '>=', $servedCutoff);
                    });
            })
            ->whereHas(
                'order.tableSession',
                fn ($q) => $q->where('status', TableSessionStatus::Active)
            )
            ->with([
                'menuItem:id,menu_category_id,name,kitchen_name,role_category,sort_order',
                'menuItem.menuCategory:id,sort_order',
                'order:id,table_session_id,shop_id,placed_at',
                'order.tableSession:id,restaurant_table_id,shop_id,staff_name',
                'order.tableSession.restaurantTable:id,shop_id,name,sort_order',
            ])
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
