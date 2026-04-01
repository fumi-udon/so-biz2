<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 外部 API（bistronippon.com 等）から注文一覧を取得し、日付ごとのランチ／ディナー売上合計を算出する。
 *
 * 金額は POS のオーダー行の `total`（sum_line_keys で上書き可）。明細 items＋ingredients の再計算はしない。
 *
 * `end_date` は processing_timezone（空なら app.timezone）のローカル時刻として解釈し、Midi/Soir 境界と同じ TZ で比較する。
 * 旧 Jesser 比較は use_legacy_strtotime（サーバ既定 TZ の strtotime）。
 */
final class BistronipponOrdersRecettesService
{
    /**
     * @return array{lunch: float, dinner: float, journal: float}
     *
     * @throws RequestException|\RuntimeException
     */
    public function fetchLunchDinnerTotals(string $dateYmd): array
    {
        $url = (string) config('daily_close.orders_api.url', '');
        if ($url === '') {
            throw new \RuntimeException('orders_api.url is not configured.');
        }

        $store = (string) config('daily_close.orders_api.store', 'main');
        $timeout = max(1, (int) config('daily_close.orders_api.timeout', 20));
        $connectTimeout = min(max(1, (int) config('daily_close.orders_api.connect_timeout', 5)), $timeout);

        $pending = Http::connectTimeout($connectTimeout)
            ->timeout($timeout)
            ->acceptJson();

        if (app()->environment('local')) {
            $pending = $pending->withoutVerifying();
        }

        $response = $pending->get($url, [
            'store' => $store,
            'date' => $dateYmd,
        ]);

        $response->throw();

        $raw = $response->json();
        $collect = $this->normalizeToOrderCollection($raw);

        $useLegacy = (bool) config('daily_close.orders_api.use_legacy_strtotime', false);

        if ($useLegacy) {
            $totalLunch = $this->sumLunchLegacyStyle($collect, $dateYmd);
            $totalDinner = $this->sumDinnerLegacyStyle($collect, $dateYmd);
        } else {
            $tz = $this->processingTimezone();
            $totalLunch = $this->sumLunchCarbon($collect, $dateYmd, $tz);
            $totalDinner = $this->sumDinnerCarbon($collect, $dateYmd, $tz);
        }

        $journal = $totalLunch + $totalDinner;

        if ((bool) config('daily_close.orders_api.debug_aggregate_log', false)) {
            $tzLog = $this->processingTimezone();
            Log::info('daily_close.orders_api.aggregate', [
                'date' => $dateYmd,
                'mode' => $useLegacy ? 'legacy_strtotime' : 'carbon_tz',
                'tz' => $tzLog,
                'rows' => $collect->count(),
                'lunch_sum' => $totalLunch,
                'dinner_sum' => $totalDinner,
                'journal_sum' => $journal,
            ]);
        }

        return [
            'lunch' => $totalLunch,
            'dinner' => $totalDinner,
            'journal' => $journal,
        ];
    }

    private function processingTimezone(): string
    {
        $tz = (string) config('daily_close.orders_api.processing_timezone', '');

        return $tz !== '' ? $tz : (string) config('app.timezone', 'UTC');
    }

    /**
     * @return list<string>
     */
    private function orderAmountKeys(): array
    {
        $keys = config('daily_close.orders_api.sum_line_keys');
        if (is_array($keys) && $keys !== []) {
            return array_values(array_filter(array_map('strval', $keys)));
        }

        return ['total'];
    }

    /**
     * オーダー1件の売上（POS が返すヘッダ金額。明細の合算ではない）。
     *
     * @param  array<string, mixed>  $order
     */
    private function orderRecetteAmount(array $order): float
    {
        foreach ($this->orderAmountKeys() as $key) {
            if (! array_key_exists($key, $order)) {
                continue;
            }
            $v = $order[$key];
            if ($v === null || $v === '') {
                continue;
            }

            return (float) $v;
        }

        return 0.0;
    }

    /**
     * @param  Collection<int, mixed>  $filtered
     */
    private function sumFiltered(Collection $filtered): float
    {
        return (float) $filtered->sum(fn (mixed $item): float => is_array($item) ? $this->orderRecetteAmount($item) : 0.0);
    }

    /**
     * @param  Collection<int, mixed>  $collect
     */
    private function sumLunchCarbon(Collection $collect, string $dateYmd, string $tz): float
    {
        $day = Carbon::parse($dateYmd, $tz)->startOfDay();
        $from = $day->copy()->addSecond();
        $to = $day->copy()->setTime(18, 0, 0);

        $filtered = $collect->filter(fn ($item) => $this->itemInWindowCarbon($item, $from, $to, $tz));

        return $this->sumFiltered($filtered);
    }

    /**
     * @param  Collection<int, mixed>  $collect
     */
    private function sumDinnerCarbon(Collection $collect, string $dateYmd, string $tz): float
    {
        $day = Carbon::parse($dateYmd, $tz)->startOfDay();
        $from = $day->copy()->setTime(18, 0, 1);
        $to = $day->copy()->setTime(23, 59, 59);

        $filtered = $collect->filter(fn ($item) => $this->itemInWindowCarbon($item, $from, $to, $tz));

        return $this->sumFiltered($filtered);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function itemInWindowCarbon(mixed $item, Carbon $from, Carbon $to, string $tz): bool
    {
        if (! is_array($item)) {
            return false;
        }
        if (! array_key_exists('end_date', $item) || $item['end_date'] === null) {
            return false;
        }
        try {
            // API のローカル時刻（オフセットなし）を店舗 TZ として解釈し、$from/$to と同じ基準で比較する
            $end = Carbon::parse((string) $item['end_date'], $tz);
        } catch (\Throwable) {
            return false;
        }

        return $end->gte($from) && $end->lte($to);
    }

    /**
     * @param  mixed  $raw
     */
    private function normalizeToOrderCollection($raw): Collection
    {
        if (! is_array($raw)) {
            return collect();
        }

        if (array_is_list($raw)) {
            return collect($raw);
        }

        if (isset($raw['data']) && is_array($raw['data'])) {
            return collect($raw['data']);
        }

        if (isset($raw['orders']) && is_array($raw['orders'])) {
            return collect($raw['orders']);
        }

        return collect($raw)->values();
    }

    /**
     * JesserController と同じランチ帯・行合算（比較・切り戻し用）。
     *
     * @param  Collection<int, mixed>  $collect
     */
    private function sumLunchLegacyStyle(Collection $collect, string $todayDate): float
    {
        $filtered = $collect->filter(function ($item) use ($todayDate): bool {
            if (! is_array($item)) {
                return false;
            }

            return ! is_null($item['end_date'] ?? null)
                && strtotime((string) $item['end_date']) >= strtotime($todayDate.' 00:00:01')
                && strtotime((string) $item['end_date']) <= strtotime($todayDate.' 18:00:00');
        });

        return $this->sumFiltered($filtered);
    }

    /**
     * JesserController と同じディナー帯・行合算（比較・切り戻し用）。
     *
     * @param  Collection<int, mixed>  $collect
     */
    private function sumDinnerLegacyStyle(Collection $collect, string $todayDate): float
    {
        $filtered = $collect->filter(function ($item) use ($todayDate): bool {
            if (! is_array($item)) {
                return false;
            }

            return ! is_null($item['end_date'] ?? null)
                && strtotime((string) $item['end_date']) >= strtotime($todayDate.'18:00:01')
                && strtotime((string) $item['end_date']) <= strtotime($todayDate.'23:59:59');
        });

        return $this->sumFiltered($filtered);
    }
}
