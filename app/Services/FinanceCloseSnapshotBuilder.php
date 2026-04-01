<?php

namespace App\Services;

/**
 * Bravo 保存時の分析用スナップショット（売上構成・チップ比率等）。
 * 原価・経費は未連携のため利益は含めない。
 */
final class FinanceCloseSnapshotBuilder
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $calc
     * @return array<string, mixed>
     */
    public static function build(
        array $payload,
        array $calc,
        string $businessDate,
        string $shift,
        ?int $responsibleStaffId,
        ?int $panelOperatorUserId = null,
    ): array {
        $recettes = (float) ($payload['recettes'] ?? 0);
        $montant = (float) ($payload['montant_initial'] ?? 0);
        $cash = (float) ($payload['cash'] ?? 0);
        $cheque = (float) ($payload['cheque'] ?? 0);
        $carte = (float) ($payload['carte'] ?? 0);
        $chips = (float) ($payload['chips'] ?? 0);
        $expected = (float) ($calc['expected_sales'] ?? 0);
        $register = (float) ($calc['measured_without_declared_tip'] ?? 0);

        $pct = static function (float $part, float $whole): ?float {
            if ($whole <= 0.0005) {
                return null;
            }

            return round($part / $whole * 100, 3);
        };

        return [
            'schema_version' => 1,
            'saved_at_iso' => now()->toIso8601String(),
            'business_date' => $businessDate,
            'shift' => $shift,
            'input' => [
                'recettes' => round($recettes, 3),
                'montant_initial' => round($montant, 3),
                'cash' => round($cash, 3),
                'cheque' => round($cheque, 3),
                'carte' => round($carte, 3),
                'chips' => round($chips, 3),
            ],
            'calc' => [
                'expected_sales' => round($expected, 3),
                'sum_tip_plus_pos_sales' => round((float) ($calc['sum_tip_plus_pos_sales'] ?? (($calc['declared_tip'] ?? 0) + $expected)), 3),
                'measured_without_declared_tip' => round($register, 3),
                'system_tip' => round((float) ($calc['system_tip'] ?? 0), 3),
                'declared_tip' => round((float) ($calc['declared_tip'] ?? 0), 3),
                'final_tip_amount' => round((float) ($calc['final_tip_amount'] ?? 0), 3),
                'reserve_amount' => round((float) ($calc['reserve_amount'] ?? 0), 3),
                'final_difference' => round((float) ($calc['final_difference'] ?? 0), 3),
                'verdict' => (string) ($calc['verdict'] ?? ''),
            ],
            'derived' => [
                'measured_share_pct' => [
                    'cash_of_register' => $pct($cash, $register),
                    'cheque_of_register' => $pct($cheque, $register),
                    'carte_of_register' => $pct($carte, $register),
                    'chips_of_register' => $pct($chips, $register),
                ],
                'relative_to_sales' => [
                    'cash_to_recettes_pct' => $pct($cash, $recettes),
                    'carte_to_recettes_pct' => $pct($carte, $recettes),
                    'chips_to_recettes_pct' => $pct($chips, $recettes),
                ],
                'theoretical_total' => round($recettes + $montant, 3),
                'revenue_analysis' => [
                    'recettes_as_pos_sales_proxy' => round($recettes, 3),
                    'note' => '利益・原価は未連携。recettes を売上代理、チップは chips および実測構成で分析可能。',
                ],
            ],
            'meta' => [
                'responsible_staff_id' => $responsibleStaffId,
                'panel_operator_user_id' => $panelOperatorUserId,
                'app_env' => app()->environment(),
            ],
        ];
    }
}
