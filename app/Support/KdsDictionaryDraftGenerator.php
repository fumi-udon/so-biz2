<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\MenuItem;

/**
 * KDS 辞書テキスト用: 店舗マスタの Style / Topping 表示名から「元:略」行を生成する（手入力補助）。
 */
final class KdsDictionaryDraftGenerator
{
    public const int ABBR_MAX_LEN = 6;

    public static function buildText(int $shopId): string
    {
        if ($shopId < 1) {
            return '';
        }

        $styles = self::collectUniqueDisplayNames($shopId, 'styles');
        $toppings = self::collectUniqueDisplayNames($shopId, 'toppings');

        $lines = [];
        foreach ($styles as $original) {
            $lines[] = $original.':'.self::abbreviate($original);
        }
        if ($styles !== [] && $toppings !== []) {
            $lines[] = '';
        }
        foreach ($toppings as $original) {
            $lines[] = $original.':'.self::abbreviate($original);
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private static function collectUniqueDisplayNames(int $shopId, string $payloadKey): array
    {
        /** @var array<string, string> $byNorm original の最初の表記を保持 */
        $byNorm = [];

        MenuItem::query()
            ->where('shop_id', $shopId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['options_payload'])
            ->each(function (MenuItem $item) use ($payloadKey, &$byNorm): void {
                $payload = is_array($item->options_payload) ? $item->options_payload : [];
                $rows = is_array($payload[$payloadKey] ?? null) ? $payload[$payloadKey] : [];
                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $name = trim((string) ($row['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    $nk = KdsDictionarySetting::normalizeMatchKey($name);
                    if ($nk === '') {
                        continue;
                    }
                    if (! isset($byNorm[$nk])) {
                        $byNorm[$nk] = $name;
                    }
                }
            });

        $out = array_values($byNorm);
        sort($out, SORT_NATURAL | SORT_FLAG_CASE);

        return $out;
    }

    private static function abbreviate(string $raw): string
    {
        $s = trim($raw);
        if ($s === '') {
            return '';
        }
        $max = self::ABBR_MAX_LEN;
        if (mb_strlen($s, 'UTF-8') <= $max) {
            return mb_strtolower($s, 'UTF-8');
        }

        $parts = preg_split('/[\s&\/,]+/u', $s, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts !== [] && count($parts) >= 2) {
            $initials = '';
            foreach (array_slice($parts, 0, 5) as $p) {
                $initials .= mb_substr(mb_strtolower(trim($p), 'UTF-8'), 0, 1, 'UTF-8');
            }
            if (mb_strlen($initials, 'UTF-8') >= 2 && mb_strlen($initials, 'UTF-8') <= $max) {
                return $initials;
            }
            if (count($parts) === 2) {
                $a = mb_strtolower(mb_substr($parts[0], 0, 3, 'UTF-8'), 'UTF-8');
                $b = mb_strtolower(mb_substr($parts[1], 0, 3, 'UTF-8'), 'UTF-8');
                $combo = $a.'&'.$b;
                if (mb_strlen($combo, 'UTF-8') <= $max) {
                    return $combo;
                }
            }
        }

        return mb_strtolower(mb_substr($s, 0, $max, 'UTF-8'), 'UTF-8');
    }
}
