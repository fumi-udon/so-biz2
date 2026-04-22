<?php

namespace App\Livewire\Kds;

use App\Actions\Kds\UpdateOrderLineStatusAction;
use App\Domains\Pos\Tables\TableCategory;
use App\Enums\OrderLineStatus;
use App\Enums\TableSessionStatus;
use App\Exceptions\RevisionConflictException;
use App\Models\OrderLine;
use App\Models\Shop;
use App\Models\TableSession;
use App\Services\Kds\KdsBroadcastService;
use App\Services\Kds\KdsQueryService;
use App\Support\Pos\StaffTableSettlementPricing;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

#[Layout('layouts.kds')]
class KdsDashboard extends Component
{
    /**
     * Shop scope（端末 PIN ログイン後の `session('kds.active_shop_id')`）。
     * `Locked` でクライアントからの上書きを防ぐ。
     */
    #[Locked]
    public int $shopId = 0;

    private const NEW_ARRIVAL_HIGHLIGHT_SECONDS = 12;

    public bool $historyOpen = false;

    /** @var 'connecting'|'connected'|'disconnected'|'error' */
    public string $realtimeState = 'connecting';

    public int $pollSeconds = 2;

    public ?string $lastRealtimeEventAt = null;

    public function mount(): void
    {
        $id = (int) session('kds.active_shop_id', 0);
        if ($id > 0 && Shop::query()->whereKey($id)->where('is_active', true)->exists()) {
            $this->shopId = $id;
        } else {
            $this->shopId = 0;
        }
        if ($this->shopId === 0) {
            $this->realtimeState = 'disconnected';
        }
    }

    /**
     * テーブル単位にグルーピング済みのチケット集合を返す。
     *
     * 形: [
     *   'tableId' => int,
     *   'tableName' => string,
     *   'sortOrder' => int,
     *   'tickets' => list<OrderLine>, // 赤→緑→新しい順
     *   'allServed' => bool, // 列内がすべて Served のとき true（アニメ用）
     * ][]
     *
     * @return list<array<string, mixed>>
     */
    public function getTableColumnsProperty(): array
    {
        if ($this->shopId === 0) {
            return [];
        }

        /** @var Collection<int, OrderLine> $rows */
        $rows = app(KdsQueryService::class)->pullActiveSessionTicketsForDashboard($this->shopId);

        $byBatch = [];
        foreach ($rows as $line) {
            $order = $line->order;
            $session = $line->order?->tableSession;
            $table = $session?->restaurantTable;
            if ($session === null || $table === null || $order === null) {
                continue;
            }
            $tid = (int) $table->id;
            $sessionId = (int) $session->id;
            $orderId = (int) $order->id;
            $batchUuid = $line->kds_ticket_batch_id;
            $batchKey = (is_string($batchUuid) && $batchUuid !== '')
                ? "{$sessionId}:b:{$batchUuid}"
                : "{$sessionId}:o:{$orderId}";

            if (! isset($byBatch[$batchKey])) {
                $staffName = $session->staff_name;
                $tableName = (string) ($table->name ?? '');
                if (StaffTableSettlementPricing::isStaffMealTableId($tid)
                    && is_string($staffName)
                    && trim($staffName) !== '') {
                    $tableName = trim($staffName);
                } elseif (TableCategory::tryResolveFromId($tid) === TableCategory::Takeaway
                    && is_string($session->customer_name)
                    && trim($session->customer_name) !== '') {
                    $tableName = trim($session->customer_name);
                }
                $byBatch[$batchKey] = [
                    'batchKey' => $batchKey,
                    'tableId' => $tid,
                    'tableSessionId' => $sessionId,
                    'orderId' => $orderId,
                    'batchAt' => $order->placed_at ?? $line->updated_at,
                    'tableName' => $tableName,
                    'sortOrder' => (int) ($table->sort_order ?? 0),
                    'category' => app(KdsQueryService::class)->tableCategoryForTableId($tid)?->value ?? 'other',
                    'tickets' => [],
                ];
            } else {
                $byBatch[$batchKey]['orderId'] = min((int) $byBatch[$batchKey]['orderId'], $orderId);
                $placedAt = $order->placed_at;
                if ($placedAt !== null) {
                    $current = $byBatch[$batchKey]['batchAt'] ?? null;
                    if ($current === null || $placedAt->lt($current)) {
                        $byBatch[$batchKey]['batchAt'] = $placedAt;
                    }
                }
            }
            $byBatch[$batchKey]['tickets'][] = $line;
        }

        foreach ($byBatch as &$col) {
            usort($col['tickets'], static function (OrderLine $a, OrderLine $b): int {
                $ca = (int) ($a->menuItem?->menuCategory?->sort_order ?? PHP_INT_MAX);
                $cb = (int) ($b->menuItem?->menuCategory?->sort_order ?? PHP_INT_MAX);
                if ($ca !== $cb) {
                    return $ca <=> $cb;
                }

                $ia = (int) ($a->menuItem?->sort_order ?? PHP_INT_MAX);
                $ib = (int) ($b->menuItem?->sort_order ?? PHP_INT_MAX);
                if ($ia !== $ib) {
                    return $ia <=> $ib;
                }

                $na = mb_strtolower(trim((string) ($a->snapshot_kitchen_name ?: $a->snapshot_name ?: $a->menuItem?->name ?: '')));
                $nb = mb_strtolower(trim((string) ($b->snapshot_kitchen_name ?: $b->snapshot_name ?: $b->menuItem?->name ?: '')));
                if ($na !== $nb) {
                    return $na <=> $nb;
                }

                return ((int) $a->id) <=> ((int) $b->id);
            });

            foreach ($col['tickets'] as $ticket) {
                $isActionable = in_array($ticket->status, [OrderLineStatus::Confirmed, OrderLineStatus::Cooking], true);
                $ageSeconds = optional($ticket->updated_at)?->diffInSeconds(now()) ?? 999999;
                $ticket->setAttribute('kds_is_new_arrival', $isActionable && $ageSeconds <= self::NEW_ARRIVAL_HIGHLIGHT_SECONDS);
            }
        }
        unset($col);

        $cols = array_values($byBatch);
        $query = app(KdsQueryService::class);
        usort($cols, static function (array $a, array $b) use ($query): int {
            $ap = $query->tableCategoryPriorityForKds((int) ($a['tableId'] ?? 0));
            $bp = $query->tableCategoryPriorityForKds((int) ($b['tableId'] ?? 0));
            $at = optional($a['batchAt'] ?? null)?->getTimestamp() ?? 0;
            $bt = optional($b['batchAt'] ?? null)?->getTimestamp() ?? 0;

            return [$ap, $a['sortOrder'], $a['tableId'], $at, $a['orderId']] <=> [$bp, $b['sortOrder'], $b['tableId'], $bt, $b['orderId']];
        });

        $out = [];
        $tableBatchOrdinal = [];
        foreach ($cols as $col) {
            /** @var list<OrderLine> $tickets */
            $tickets = $col['tickets'];
            $allServed = collect($tickets)->every(
                static fn (OrderLine $line): bool => $line->status === OrderLineStatus::Served
            );
            $tid = (int) ($col['tableId'] ?? 0);
            $tableBatchOrdinal[$tid] = ($tableBatchOrdinal[$tid] ?? 0) + 1;
            $ordinal = (int) $tableBatchOrdinal[$tid];
            $baseName = (string) ($col['tableName'] !== '' ? $col['tableName'] : '#'.$tid);
            $col['displayLabel'] = $ordinal === 1 ? $baseName : "{$baseName} (Add #{$ordinal})";
            $col['allServed'] = $allServed;
            $out[] = $col;
        }

        return $out;
    }

    public function toggleHistory(): void
    {
        $this->historyOpen = ! $this->historyOpen;
    }

    /**
     * 履歴ドロワーが開いているときのみ DB を参照する（poll ごとに叩かない）。
     *
     * @return list<array<string, mixed>>
     */
    public function getHistoryColumnsProperty(): array
    {
        if (! $this->historyOpen || $this->shopId === 0) {
            return [];
        }

        return app(KdsQueryService::class)
            ->pullServedSessionsForToday($this->shopId)
            ->map(static function (TableSession $s): array {
                $lines = $s->posOrders->flatMap(static fn ($o) => $o->lines)->values();
                $tid = (int) ($s->restaurant_table_id ?? 0);
                $tn = (string) ($s->restaurantTable?->name ?? '');
                if (StaffTableSettlementPricing::isStaffMealTableId($tid)
                    && is_string($s->staff_name)
                    && trim($s->staff_name) !== '') {
                    $tn = trim($s->staff_name);
                } elseif (TableCategory::tryResolveFromId($tid) === TableCategory::Takeaway
                    && is_string($s->customer_name)
                    && trim($s->customer_name) !== '') {
                    $tn = trim($s->customer_name);
                }

                return [
                    'sessionId' => (int) $s->id,
                    'tableName' => $tn,
                    'openedAt' => $s->opened_at,
                    'isClosed' => $s->status === TableSessionStatus::Closed,
                    'lines' => $lines,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * 赤チケット（Confirmed/Cooking）をタップ → Served（緑）へ。
     * 楽観的ロックで失敗（他スタッフが先に更新）した場合は握り潰し、
     * 即時再描画（Livewire 標準の再レンダリング）で最新状態を出す。
     */
    public function markServed(int $orderLineId, int $expectedLineRevision): void
    {
        try {
            app(UpdateOrderLineStatusAction::class)->execute(
                $orderLineId,
                OrderLineStatus::Served->value,
                $expectedLineRevision,
            );
        } catch (RevisionConflictException $e) {
            Notification::make()
                ->title(__('kds.conflict_title'))
                ->body(__('kds.conflict_body'))
                ->warning()
                ->send();
        } catch (Throwable $e) {
            Log::warning('KDS markServed failed', [
                'order_line_id' => $orderLineId,
                'expected_revision' => $expectedLineRevision,
                'error' => $e->getMessage(),
            ]);
            Notification::make()
                ->title(__('kds.update_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * 同一店舗内で赤→緑、緑→赤のロールバック（誤タップ救済）も可能にする。
     */
    public function revertToConfirmed(int $orderLineId, int $expectedLineRevision): void
    {
        try {
            app(UpdateOrderLineStatusAction::class)->execute(
                $orderLineId,
                OrderLineStatus::Confirmed->value,
                $expectedLineRevision,
            );
        } catch (RevisionConflictException $e) {
            Notification::make()
                ->title(__('kds.conflict_title'))
                ->body(__('kds.conflict_body'))
                ->warning()
                ->send();
        } catch (Throwable $e) {
            Log::warning('KDS revert failed', [
                'order_line_id' => $orderLineId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Pusher (Echo) からの「即時更新ベル」受信時に Alpine からデバウンス越しに呼ばれる。
     *
     * 実装は意図的に no-op としている。Livewire はこのメソッド呼び出しに伴って
     * 通常の Mount/Render サイクルを走らせ、`tableColumns` プロパティが
     * `KdsQueryService` を再実行することで最新状態を返す。
     *
     * これにより:
     *   - DOM Morph と `wire:poll.10s` の競合が起きない（Livewire が応答を 1 本に直列化）
     *   - Echo がバースト発火しても 300ms デバウンスで 1 リクエストに集約される
     */
    public function refreshTickets(): void
    {
        // no-op: Livewire の標準再レンダリングのみで十分。
    }

    public function syncRealtimeState(string $state): void
    {
        if (! in_array($state, ['connecting', 'connected', 'disconnected', 'error'], true)) {
            return;
        }

        $this->realtimeState = $state;
        $this->pollSeconds = $state === 'connected' ? 60 : 2;
    }

    public function markRealtimeEventReceived(): void
    {
        $this->lastRealtimeEventAt = now()->format('H:i:s');
    }

    /**
     * @return array{offline:bool,last_fail_at:?string,last_fail_hms:?string,last_ok_hms:?string,last_fail_error:?string}
     */
    public function getBroadcastHealthProperty(): array
    {
        $health = app(KdsBroadcastService::class)->recentHealthForShop($this->shopId);
        $failAt = is_string($health['last_fail_at'] ?? null) ? CarbonImmutable::parse($health['last_fail_at']) : null;
        $okAt = is_string($health['last_ok_at'] ?? null) ? CarbonImmutable::parse($health['last_ok_at']) : null;

        return [
            'offline' => $this->realtimeState !== 'connected',
            'last_fail_at' => $failAt?->toIso8601String(),
            'last_fail_hms' => $failAt?->format('H:i:s'),
            'last_ok_hms' => $okAt?->format('H:i:s'),
            'last_fail_error' => is_string($health['last_fail_error'] ?? null) ? $health['last_fail_error'] : null,
        ];
    }

    public function render(): View
    {
        return view('livewire.kds.kds-dashboard', [
            'columns' => $this->tableColumns,
            'hasShop' => $this->shopId !== 0,
            'broadcastHealth' => $this->broadcastHealth,
        ]);
    }
}
