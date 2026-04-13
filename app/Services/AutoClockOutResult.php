<?php

namespace App\Services;

/**
 * AutoClockOutService::runForDate() の処理結果 DTO。
 *
 * filled  : 自動補完が完了したエントリ
 * skipped : シフト未定義・パース失敗・DB エラー等でスキップしたエントリ
 */
final class AutoClockOutResult
{
    /**
     * @param  list<array{staff_name: string, meal: string, out_at: string}>  $filled
     * @param  list<array{staff_name: string, meal: string, reason: string}>  $skipped
     */
    public function __construct(
        public readonly array $filled,
        public readonly array $skipped,
    ) {}

    public function filledCount(): int
    {
        return count($this->filled);
    }

    public function skippedCount(): int
    {
        return count($this->skipped);
    }

    /** 補完もスキップも発生しなかった（完全に対象なし） */
    public function isEmpty(): bool
    {
        return $this->filled === [] && $this->skipped === [];
    }

    /** 通知が必要か（補完またはスキップが1件以上ある場合） */
    public function needsNotification(): bool
    {
        return ! $this->isEmpty();
    }
}
