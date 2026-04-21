<?php

namespace App\Services\Pos;

use App\Exceptions\GuestOrderValidationException;
use App\Models\MenuItem;

/**
 * 卓注文行の税込価格（ミリウム）と KDS 用スナップショット（ゲスト/スタッフ同一ロジック）。
 */
final class PosLineComputationService
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function computeUnitPriceMinor(MenuItem $item, array $row): int
    {
        $rules = $this->optionsRules($item);
        $styleRequired = (bool) ($rules['style_required'] ?? false);

        $styleId = isset($row['styleId']) && $row['styleId'] !== null && $row['styleId'] !== ''
            ? (string) $row['styleId']
            : null;

        if ($styleRequired && ($styleId === null || $styleId === '')) {
            throw new GuestOrderValidationException(__('Style selection is required.'));
        }

        if ($styleId !== null && $styleId !== '') {
            $base = $this->priceMinorForSelectedStyle($item, $styleId);
        } else {
            $base = $this->referenceFromPriceMinor($item);
        }

        /** @var list<mixed> $toppingSnapshots */
        $toppingSnapshots = is_array($row['toppingSnapshots'] ?? null) ? $row['toppingSnapshots'] : [];

        $delta = $this->sumToppingDeltasFromMaster($item, $toppingSnapshots);

        return max(0, $base + $delta);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function buildSnapshotOptionsPayload(MenuItem $item, array $row): array
    {
        $payload = is_array($item->options_payload) ? $item->options_payload : [];
        $styles = is_array($payload['styles'] ?? null) ? $payload['styles'] : [];
        $toppings = is_array($payload['toppings'] ?? null) ? $payload['toppings'] : [];

        $styleId = isset($row['styleId']) && $row['styleId'] !== null && $row['styleId'] !== ''
            ? (string) $row['styleId']
            : null;

        $styleSnapshot = null;
        if ($styleId !== null) {
            foreach ($styles as $s) {
                if (! is_array($s) || (string) ($s['id'] ?? '') !== $styleId) {
                    continue;
                }
                $styleSnapshot = [
                    'id' => (string) ($s['id'] ?? ''),
                    'name' => (string) ($s['name'] ?? ''),
                    'price_minor' => array_key_exists('price_minor', $s) ? max(0, (int) $s['price_minor']) : 0,
                ];
                break;
            }
        }

        $toppingOut = [];
        /** @var list<mixed> $clientToppings */
        $clientToppings = is_array($row['toppingSnapshots'] ?? null) ? $row['toppingSnapshots'] : [];
        foreach ($clientToppings as $ct) {
            if (! is_array($ct)) {
                continue;
            }
            $tid = isset($ct['id']) ? (string) $ct['id'] : '';
            if ($tid === '') {
                continue;
            }
            foreach ($toppings as $t) {
                if (! is_array($t) || (string) ($t['id'] ?? '') !== $tid) {
                    continue;
                }
                $toppingOut[] = [
                    'id' => (string) ($t['id'] ?? ''),
                    'name' => (string) ($t['name'] ?? ''),
                    'price_delta_minor' => max(0, (int) ($t['price_delta_minor'] ?? 0)),
                ];
                break;
            }
        }

        return [
            'style' => $styleSnapshot,
            'toppings' => $toppingOut,
            'note' => isset($row['note']) ? (string) $row['note'] : '',
            'client' => [
                'lineId' => isset($row['lineId']) ? (string) $row['lineId'] : null,
                'mergeKey' => isset($row['mergeKey']) ? (string) $row['mergeKey'] : null,
            ],
        ];
    }

    /**
     * @return array{style_required: bool, merge_identical_lines: bool}
     */
    private function optionsRules(MenuItem $item): array
    {
        $payload = is_array($item->options_payload) ? $item->options_payload : [];
        $rules = is_array($payload['rules'] ?? null) ? $payload['rules'] : [];

        return [
            'style_required' => (bool) ($rules['style_required'] ?? false),
            'merge_identical_lines' => (bool) ($rules['merge_identical_lines'] ?? true),
        ];
    }

    private function referenceFromPriceMinor(MenuItem $item): int
    {
        $payload = is_array($item->options_payload) ? $item->options_payload : [];
        $styles = array_values(array_filter(
            is_array($payload['styles'] ?? null) ? $payload['styles'] : [],
            static fn ($row): bool => is_array($row),
        ));

        $fromMinor = max(0, (int) $item->from_price_minor);
        foreach ($styles as $styleRow) {
            if (! is_array($styleRow) || ! array_key_exists('price_minor', $styleRow)) {
                continue;
            }
            $pm = max(0, (int) $styleRow['price_minor']);
            $fromMinor = min($fromMinor, $pm);
        }

        return $fromMinor;
    }

    private function priceMinorForSelectedStyle(MenuItem $item, string $styleId): int
    {
        $payload = is_array($item->options_payload) ? $item->options_payload : [];
        $styles = is_array($payload['styles'] ?? null) ? $payload['styles'] : [];

        foreach ($styles as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ((string) ($row['id'] ?? '') !== $styleId) {
                continue;
            }
            if (! array_key_exists('price_minor', $row)) {
                break;
            }

            return max(0, (int) $row['price_minor']);
        }

        throw new GuestOrderValidationException(__('Invalid style selection.'));
    }

    /**
     * @param  list<mixed>  $toppingSnapshots
     */
    private function sumToppingDeltasFromMaster(MenuItem $item, array $toppingSnapshots): int
    {
        $payload = is_array($item->options_payload) ? $item->options_payload : [];
        $master = array_values(array_filter(
            is_array($payload['toppings'] ?? null) ? $payload['toppings'] : [],
            static fn ($row): bool => is_array($row),
        ));

        $sum = 0;

        foreach ($toppingSnapshots as $snap) {
            if (! is_array($snap)) {
                continue;
            }
            $id = isset($snap['id']) ? (string) $snap['id'] : '';
            if ($id === '') {
                continue;
            }

            $matched = false;
            foreach ($master as $t) {
                if ((string) ($t['id'] ?? '') !== $id) {
                    continue;
                }
                $sum += max(0, (int) ($t['price_delta_minor'] ?? 0));
                $matched = true;
                break;
            }

            if (! $matched) {
                throw new GuestOrderValidationException(__('Invalid topping selection.'));
            }
        }

        return $sum;
    }
}
