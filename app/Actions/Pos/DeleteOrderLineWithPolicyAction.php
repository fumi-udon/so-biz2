<?php

namespace App\Actions\Pos;

use App\Models\OrderLine;
use App\Models\Staff;
use App\Models\TableSession;
use App\Services\StaffPinAuthenticationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class DeleteOrderLineWithPolicyAction
{
    public const string MODE_OPEN = 'open';

    public const string MODE_PIN = 'pin';

    public const string MODE_CACHE = 'ttl';

    private const int PIN_MAX_ATTEMPTS = 5;

    private const int PIN_DECAY_SECONDS = 60;

    private const int CACHE_TTL_SECONDS = 120;

    /**
     * @return array{mode:string,approver_staff_id:int|null}
     */
    public function decide(int $shopId, int $tableSessionId): array
    {
        $session = TableSession::query()
            ->where('shop_id', $shopId)
            ->whereKey($tableSessionId)
            ->first();

        if ($session === null || $session->last_addition_printed_at === null) {
            return ['mode' => self::MODE_OPEN, 'approver_staff_id' => null];
        }

        $terminalId = $this->terminalSessionId();
        $indexKey = $this->cacheIndexKey($shopId, $terminalId, $tableSessionId);
        $approverId = (int) (Cache::get($indexKey) ?? 0);
        if ($approverId > 0 && Cache::has($this->cacheApprovalKey($shopId, $terminalId, $tableSessionId, $approverId))) {
            return ['mode' => self::MODE_CACHE, 'approver_staff_id' => $approverId];
        }

        return ['mode' => self::MODE_PIN, 'approver_staff_id' => null];
    }

    public function execute(
        int $shopId,
        int $tableSessionId,
        int $orderLineId,
        int $actorUserId,
        string $mode,
        ?int $approverStaffId = null,
        ?string $approverPin = null,
    ): void {
        $approvedBy = null;
        $approvalMode = $mode;

        if ($mode === self::MODE_PIN) {
            $approvedBy = $this->verifyPinApprover($shopId, $approverStaffId, $approverPin);
            $this->storeApprovalCache($shopId, $tableSessionId, $approvedBy);
        } elseif ($mode === self::MODE_CACHE) {
            $approvedBy = $this->verifyCacheApproval($shopId, $tableSessionId, $approverStaffId);
        }

        $line = OrderLine::query()
            ->whereKey($orderLineId)
            ->with(['order' => static fn ($q) => $q->select(['id', 'table_session_id'])])
            ->first();

        if ($line === null || $line->order === null) {
            throw new RuntimeException(__('pos.line_not_found'));
        }

        $orderId = (int) $line->order->id;
        $resolvedSessionId = (int) $line->order->table_session_id;
        $session = TableSession::query()
            ->where('shop_id', $shopId)
            ->whereKey($resolvedSessionId)
            ->first();
        $wasPrinted = $session !== null && $session->last_addition_printed_at !== null;

        app(RemovePosOrderLineAction::class)->execute($shopId, $orderLineId);

        $payload = [
            'shop_id' => $shopId,
            'table_session_id' => $resolvedSessionId,
            'order_id' => $orderId,
            'order_line_id' => $orderLineId,
            'removed_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'approver_staff_id' => $approvedBy,
            'approval_mode' => $approvalMode,
            'was_printed' => $wasPrinted,
            'created_at' => now(),
        ];
        $this->insertAuditLog($payload);
    }

    private function verifyPinApprover(int $shopId, ?int $approverStaffId, ?string $approverPin): int
    {
        if (($approverStaffId ?? 0) < 1 || trim((string) $approverPin) === '') {
            throw new RuntimeException(__('pos.remove_line_auth_input_required'));
        }

        $staff = Staff::query()
            ->with('jobLevel')
            ->where('shop_id', $shopId)
            ->whereKey((int) $approverStaffId)
            ->where('is_active', true)
            ->first();
        if ($staff === null) {
            throw new RuntimeException(__('pos.discount_approver_not_found'));
        }

        $pinError = app(StaffPinAuthenticationService::class)->verify(
            staff: $staff,
            pin: (string) $approverPin,
            context: 'pos-remove-line-after-print',
            maxAttempts: self::PIN_MAX_ATTEMPTS,
            decaySeconds: self::PIN_DECAY_SECONDS,
        );
        if ($pinError !== null) {
            throw new RuntimeException($pinError);
        }
        if ((int) ($staff->jobLevel?->level ?? 0) < 3) {
            throw new RuntimeException(__('pos.remove_line_level3_required'));
        }

        return (int) $staff->id;
    }

    private function verifyCacheApproval(int $shopId, int $tableSessionId, ?int $approverStaffId): int
    {
        $approverId = (int) ($approverStaffId ?? 0);
        if ($approverId < 1) {
            throw new RuntimeException(__('pos.remove_line_auth_required_body'));
        }
        $terminalId = $this->terminalSessionId();
        $approvalKey = $this->cacheApprovalKey($shopId, $terminalId, $tableSessionId, $approverId);
        if (! Cache::has($approvalKey)) {
            throw new RuntimeException(__('pos.remove_line_auth_required_body'));
        }

        return $approverId;
    }

    private function storeApprovalCache(int $shopId, int $tableSessionId, int $approverStaffId): void
    {
        $terminalId = $this->terminalSessionId();
        $indexKey = $this->cacheIndexKey($shopId, $terminalId, $tableSessionId);
        $approvalKey = $this->cacheApprovalKey($shopId, $terminalId, $tableSessionId, $approverStaffId);
        Cache::put($indexKey, $approverStaffId, now()->addSeconds(self::CACHE_TTL_SECONDS));
        Cache::put($approvalKey, 1, now()->addSeconds(self::CACHE_TTL_SECONDS));
    }

    private function terminalSessionId(): string
    {
        try {
            $request = request();
            if (method_exists($request, 'hasSession') && $request->hasSession()) {
                $requestSessionId = (string) $request->session()->getId();
                if ($requestSessionId !== '') {
                    return $requestSessionId;
                }
            }
        } catch (\Throwable) {
            // Fallback for CLI/Livewire test contexts without request session.
        }

        $appSessionId = (string) app('session')->getId();
        if ($appSessionId !== '') {
            return $appSessionId;
        }

        return 'terminal-cli';
    }

    private function cacheIndexKey(int $shopId, string $terminalSessionId, int $tableSessionId): string
    {
        return "pos:remove-auth:shop:{$shopId}:terminal:{$terminalSessionId}:table:{$tableSessionId}:latest";
    }

    private function cacheApprovalKey(int $shopId, string $terminalSessionId, int $tableSessionId, int $approverStaffId): string
    {
        return "pos:remove-auth:shop:{$shopId}:terminal:{$terminalSessionId}:table:{$tableSessionId}:approver:{$approverStaffId}";
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function insertAuditLog(array $payload): void
    {
        try {
            DB::table('pos_line_deletion_audit_logs')->insert($payload);

            return;
        } catch (QueryException $e) {
            if (! $this->isTableNotFound($e)) {
                throw $e;
            }
        }

        $logical = 'pos_line_deletion_audit_logs';
        $prefix = (string) DB::connection()->getTablePrefix();
        $candidates = array_values(array_unique(array_filter([
            $logical,
            $prefix !== '' ? $prefix.$logical : null,
        ])));

        foreach ($candidates as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $this->insertIntoPhysicalTable($table, $payload);

            return;
        }

        throw new RuntimeException('Delete audit table is missing. Run migrations for pos_line_deletion_audit_logs.');
    }

    private function isTableNotFound(QueryException $e): bool
    {
        $code = (string) ($e->getCode() ?? '');
        $msg = strtolower((string) $e->getMessage());

        return $code === '42S02' || str_contains($msg, '1146') || str_contains($msg, 'doesn\'t exist');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function insertIntoPhysicalTable(string $table, array $payload): void
    {
        $columns = array_keys($payload);
        $bindings = array_values($payload);
        $wrappedTable = '`'.str_replace('`', '``', $table).'`';
        $columnSql = implode(', ', array_map(static fn (string $col): string => '`'.$col.'`', $columns));
        $placeholderSql = implode(', ', array_fill(0, count($columns), '?'));
        $sql = "insert into {$wrappedTable} ({$columnSql}) values ({$placeholderSql})";
        DB::insert($sql, $bindings);
    }
}
