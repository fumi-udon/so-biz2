<?php

namespace App\Services\Pos;

use App\Data\Pos\TableDashboardData;
use App\Data\Pos\TableTileAggregate;
use App\Domains\Pos\Tables\TableCategory;
use App\Domains\Pos\Tables\TableUiStatusInput;
use App\Domains\Pos\Tables\TableUiStatusResolver;
use App\Enums\OrderLineStatus;
use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Single round-trip (one SQL) 卓集約. V4 scalar aliases: `agg_*`.
 * See `docs/technical_contract_v4.md` §2.1–2.2
 *
 * Phase 2: TableUiStatusResolver を結線し、UI ステータスを Service 層で確定させる。
 */
final class TableDashboardQueryService
{
    public function __construct(
        private readonly TableUiStatusResolver $resolver = new TableUiStatusResolver,
    ) {}

    public function getDashboardData(int $shopId): TableDashboardData
    {
        $p = (string) DB::connection()->getTablePrefix();
        $active = TableSessionStatus::Active->value;
        $placed = OrderStatus::Placed->value;
        $confirmed = OrderStatus::Confirmed->value;
        $voided = OrderStatus::Voided->value;
        $linePlaced = OrderLineStatus::Placed->value;

        $rt = $p.'restaurant_tables';
        $ts = $p.'table_sessions';
        $orders = $p.'orders';
        $ol = $p.'order_lines';

        $innerActiveSessionId = "(
            SELECT ts_inner.id
            FROM {$ts} AS ts_inner
            WHERE ts_inner.restaurant_table_id = rt.id
            AND ts_inner.status = '{$this->q($active)}'
            ORDER BY ts_inner.id DESC
            LIMIT 1
        )";

        $innerLastAdditionPrintedAt = "(
            SELECT ts_p.last_addition_printed_at
            FROM {$ts} AS ts_p
            WHERE ts_p.restaurant_table_id = rt.id
            AND ts_p.status = '{$this->q($active)}'
            ORDER BY ts_p.id DESC
            LIMIT 1
        )";

        $innerActiveSessionStaffName = "(
            SELECT ts_sn.staff_name
            FROM {$ts} AS ts_sn
            WHERE ts_sn.id = ({$innerActiveSessionId})
            LIMIT 1
        )";

        $innerActiveSessionCustomerName = "(
            SELECT ts_cn.customer_name
            FROM {$ts} AS ts_cn
            WHERE ts_cn.id = ({$innerActiveSessionId})
            LIMIT 1
        )";

        $sql = <<<SQL
        SELECT
            rt.id,
            rt.name AS restaurant_table_name,
            ({$innerActiveSessionId}) AS agg_active_table_session_id,
            ({$innerActiveSessionStaffName}) AS agg_active_session_staff_name,
            ({$innerActiveSessionCustomerName}) AS agg_active_session_customer_name,
            (SELECT COALESCE((
                SELECT COUNT(o1.id)
                FROM {$orders} AS o1
                INNER JOIN {$ts} AS ts1 ON o1.table_session_id = ts1.id
                WHERE ts1.restaurant_table_id = rt.id
                AND ts1.status = '{$this->q($active)}'
                AND o1.status = '{$this->q($placed)}'
            ), 0)) AS agg_unacked_placed_pos_order_count,
            (SELECT CASE WHEN EXISTS(
                SELECT 1
                FROM {$ol} AS ol1
                INNER JOIN {$orders} AS o2 ON o2.id = ol1.order_id
                INNER JOIN {$ts} AS ts2 ON o2.table_session_id = ts2.id
                WHERE ts2.restaurant_table_id = rt.id
                AND ts2.status = '{$this->q($active)}'
                AND o2.status IN ('{$this->q($placed)}', '{$this->q($confirmed)}')
                AND ol1.status = '{$this->q($linePlaced)}'
            ) THEN 1 ELSE 0 END) AS agg_unacked_placed_line_exists,
            (SELECT MIN(o3.placed_at)
                FROM {$orders} AS o3
                INNER JOIN {$ts} AS ts3 ON o3.table_session_id = ts3.id
                WHERE ts3.restaurant_table_id = rt.id
                AND ts3.status = '{$this->q($active)}'
                AND o3.status = '{$this->q($placed)}'
            ) AS agg_oldest_relevant_placed_at,
            (SELECT CASE WHEN ({$innerLastAdditionPrintedAt}) IS NULL THEN 0 ELSE 1 END
            ) AS agg_addition_checkout_flag,
            ({$innerLastAdditionPrintedAt}) AS agg_last_addition_printed_at,
            (SELECT CASE
                WHEN ({$innerLastAdditionPrintedAt}) IS NULL THEN 0
                WHEN EXISTS(
                    SELECT 1
                    FROM {$orders} AS o7
                    WHERE o7.table_session_id = ({$innerActiveSessionId})
                    AND o7.status != '{$this->q($voided)}'
                    AND o7.created_at > ({$innerLastAdditionPrintedAt})
                ) THEN 1
                WHEN EXISTS(
                    SELECT 1
                    FROM {$ol} AS ol2
                    INNER JOIN {$orders} AS o8 ON o8.id = ol2.order_id
                    WHERE o8.table_session_id = ({$innerActiveSessionId})
                    AND o8.status != '{$this->q($voided)}'
                    AND ol2.created_at > ({$innerLastAdditionPrintedAt})
                ) THEN 1
                ELSE 0
            END) AS agg_has_order_after_addition_printed,
            (SELECT COALESCE((
                SELECT COUNT(o5.id)
                FROM {$orders} AS o5
                WHERE o5.table_session_id = ({$innerActiveSessionId})
                AND o5.status != '{$this->q($voided)}'
            ), 0)) AS agg_relevant_pos_order_count,
            (SELECT COALESCE((
                SELECT SUM(o6.total_price_minor)
                FROM {$orders} AS o6
                WHERE o6.table_session_id = ({$innerActiveSessionId})
                AND o6.status != '{$this->q($voided)}'
            ), 0)) AS agg_session_total_minor
        FROM {$rt} AS rt
        WHERE rt.shop_id = ?
        AND rt.is_active = 1
        ORDER BY rt.sort_order ASC, rt.id ASC
        SQL;

        $rows = DB::select($sql, [$shopId]);
        $tiles = [];
        $seen = [];
        /** @var stdClass $r */
        foreach ($rows as $r) {
            $rid = (int) $r->id;
            if (isset($seen[$rid])) {
                throw new \RuntimeException('Table dashboard aggregate: duplicate restaurant_table_id '.$rid);
            }
            $seen[$rid] = true;
            $tiles[] = $this->toTile($r);
        }

        return new TableDashboardData($tiles);
    }

    private function toTile(stdClass $r): TableTileAggregate
    {
        $at = $r->agg_oldest_relevant_placed_at;
        $oldest = $at === null ? null : CarbonImmutable::parse((string) $at);

        $printedAtRaw = $r->agg_last_addition_printed_at ?? null;
        $lastAdditionPrintedAt = $printedAtRaw === null ? null : CarbonImmutable::parse((string) $printedAtRaw);

        $hasActiveSession = $r->agg_active_table_session_id !== null;
        $unackedOrderCount = (int) $r->agg_unacked_placed_pos_order_count;
        $unackedLineExists = (int) $r->agg_unacked_placed_line_exists === 1;
        $hasUnackedPlaced = $unackedOrderCount > 0 || $unackedLineExists;
        $hasOrderAfterAdditionPrinted = (int) ($r->agg_has_order_after_addition_printed ?? 0) === 1;

        $input = new TableUiStatusInput(
            hasActiveSession: $hasActiveSession,
            hasUnackedPlaced: $hasUnackedPlaced,
            lastAdditionPrintedAt: $lastAdditionPrintedAt,
            hasOrdersAfterLastAdditionPrintedAt: $hasOrderAfterAdditionPrinted,
        );

        $uiStatus = $this->resolver->resolve($input);
        $category = TableCategory::tryResolveFromId((int) $r->id);
        $snRaw = $r->agg_active_session_staff_name ?? null;
        $sessionStaffName = is_string($snRaw) && trim($snRaw) !== '' ? trim($snRaw) : null;
        $cnRaw = $r->agg_active_session_customer_name ?? null;
        $sessionCustomerName = is_string($cnRaw) && trim($cnRaw) !== '' ? trim($cnRaw) : null;

        $displayTableName = (string) $r->restaurant_table_name;
        if ($category === TableCategory::Takeaway && $sessionCustomerName !== null) {
            $displayTableName = $sessionCustomerName;
        }

        return new TableTileAggregate(
            restaurantTableId: (int) $r->id,
            restaurantTableName: $displayTableName,
            activeSessionStaffName: $sessionStaffName,
            activeSessionCustomerName: $sessionCustomerName,
            activeTableSessionId: $r->agg_active_table_session_id === null
                ? null
                : (int) $r->agg_active_table_session_id,
            unackedPlacedPosOrderCount: $unackedOrderCount,
            unackedPlacedLineExists: $unackedLineExists,
            oldestRelevantPlacedAt: $oldest,
            additionOrCheckoutSignalActive: (int) $r->agg_addition_checkout_flag === 1,
            relevantPosOrderCount: (int) $r->agg_relevant_pos_order_count,
            sessionTotalMinor: (int) $r->agg_session_total_minor,
            category: $category,
            uiStatus: $uiStatus,
            lastAdditionPrintedAt: $lastAdditionPrintedAt,
            hasOrderAfterAdditionPrinted: $hasOrderAfterAdditionPrinted,
        );
    }

    private function q(string $value): string
    {
        if (! preg_match('/^[a-z0-9_\\-]+$/i', $value) || $value === '') {
            throw new \InvalidArgumentException('Unsafe enum / status value for SQL');
        }
        if (str_contains($value, "'") || str_contains($value, '\\')) {
            throw new \InvalidArgumentException('Invalid SQL literal');
        }

        return $value;
    }
}
