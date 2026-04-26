<?php

namespace App\Support\Http;

use App\Http\Middleware\AppendServerTiming;
use Symfony\Component\HttpFoundation\Response;

/**
 * Collects per-request timings for the Server-Timing header (Chrome DevTools → Network).
 * Emits `db` (query time sum) and `app` (request wall time). Activated by
 * {@see AppendServerTiming}.
 */
final class ServerTimingCollector
{
    private bool $active = false;

    private float $startedAt = 0.0;

    private float $dbMs = 0.0;

    private int $queryCount = 0;

    public function begin(): void
    {
        $this->active = true;
        $this->startedAt = microtime(true);
        $this->dbMs = 0.0;
        $this->queryCount = 0;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function recordQueryMilliseconds(float $ms): void
    {
        if (! $this->active) {
            return;
        }
        $this->dbMs += $ms;
        $this->queryCount++;
    }

    public function attachToResponse(Response $response): void
    {
        if (! $this->active) {
            return;
        }

        $wallMs = (microtime(true) - $this->startedAt) * 1000.0;

        // db = MySQL 報告のクエリ時間合計（ms）。app = リクエスト壁時計（DB 含むサーバー処理全体）。
        // ブラウザの TTFB から app を引くと概ねネットワーク側の寄与に近い（Server-Timing は応答ヘッダ内のサーバー計測のみ）。
        $metrics = [
            sprintf('db;dur=%.2f;desc="%d q"', $this->dbMs, $this->queryCount),
            sprintf('app;dur=%.2f', $wallMs),
        ];

        $response->headers->set('Server-Timing', implode(', ', $metrics), false);

        $this->active = false;
    }
}
