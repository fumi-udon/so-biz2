<?php

namespace App\Support\Http;

use Symfony\Component\HttpFoundation\Response;

/**
 * Collects per-request timings for the Server-Timing header (Chrome DevTools → Network).
 * Activated per-request by {@see \App\Http\Middleware\AppendServerTiming}.
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
        $nonDbMs = max(0.0, $wallMs - $this->dbMs);

        $metrics = [
            sprintf('app;dur=%.2f', $wallMs),
            sprintf('db;dur=%.2f;desc="%d q"', $this->dbMs, $this->queryCount),
            sprintf('non-db;dur=%.2f', $nonDbMs),
        ];

        $response->headers->set('Server-Timing', implode(', ', $metrics), false);

        $this->active = false;
    }
}
