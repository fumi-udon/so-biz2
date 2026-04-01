<?php

namespace App\Services;

final class FinanceCalculatorService
{
    /**
     * Cash hors fond de caisse. Règle Bravo:
     * mesure caisse (cash + chèque + carte) = tip déclaré (paramètres) + ventes POS.
     *
     * @param  array{
     *     recettes?: float|int|string|null,
     *     cash: float|int|string|null,
     *     cheque: float|int|string|null,
     *     carte: float|int|string|null,
     *     chips: float|int|string|null,
     *     montant_initial?: float|int|string|null,
     * }  $data
     * @return array{
     *     measured_without_declared_tip: float,
     *     expected_sales: float,
     *     sum_tip_plus_pos_sales: float,
     *     system_tip: float,
     *     declared_tip: float,
     *     final_tip_amount: float,
     *     reserve_amount: float,
     *     final_difference: float,
     *     verdict: 'bravo'|'plus_error'|'minus_error',
     *     close_status: 'success'|'failed',
     * }
     */
    public function calculateResult(
        array $data,
        ?float $toleranceMoins = null,
        ?float $tolerancePlus = null,
    ): array
    {
        $posSales = $this->resolvePosSales($data);
        $cash = (float) ($data['cash'] ?? 0);
        $cheque = (float) ($data['cheque'] ?? 0);
        $carte = (float) ($data['carte'] ?? 0);
        $declaredTip = (float) ($data['chips'] ?? 0);

        $measuredWithoutDeclaredTip = $cash + $cheque + $carte;
        $sumTipPlusPos = round($declaredTip + $posSales, 3);
        // Écart: mesure caisse − (tip déclaré + ventes POS). Zéro = Bravo (tolérance).
        $finalDifference = round($measuredWithoutDeclaredTip - $sumTipPlusPos, 3);
        // Auxiliaire: mesure − ventes POS seules (colonnes legacy / analyse).
        $systemTip = round($measuredWithoutDeclaredTip - $posSales, 3);
        $verdict = $this->resolveVerdict(
            $finalDifference,
            $toleranceMoins ?? 1.000,
            $tolerancePlus ?? 3.000,
        );
        $closeStatus = $verdict === 'bravo' ? 'success' : 'failed';
        $finalTipAmount = round($declaredTip, 3);
        $reserveAmount = 0.0;

        return [
            'measured_without_declared_tip' => round($measuredWithoutDeclaredTip, 3),
            'expected_sales' => round($posSales, 3),
            'sum_tip_plus_pos_sales' => $sumTipPlusPos,
            'system_tip' => $systemTip,
            'declared_tip' => round($declaredTip, 3),
            'final_tip_amount' => round($finalTipAmount, 3),
            'reserve_amount' => round($reserveAmount, 3),
            'final_difference' => $finalDifference,
            'verdict' => $verdict,
            'close_status' => $closeStatus,
        ];
    }

    private function resolvePosSales(array $data): float
    {
        return (float) ($data['recettes'] ?? 0.0);
    }

    private function resolveVerdict(float $finalDifference, float $toleranceMoins, float $tolerancePlus): string
    {
        if ($finalDifference >= -abs($toleranceMoins) && $finalDifference <= abs($tolerancePlus)) {
            return 'bravo';
        }

        if ($finalDifference < 0) {
            return 'minus_error';
        }

        return 'plus_error';
    }

    /**
     * 管理者参照用のしきい値。
     *
     * @return list<array{range: string, tolerance: float}>
     */
    public static function toleranceBandsForAdmin(): array
    {
        return [
            ['range' => 'Tolérance configurable depuis paramètres caisse', 'tolerance' => 0.0],
        ];
    }
}
