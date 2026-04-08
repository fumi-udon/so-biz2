<?php

namespace App\Actions\Finance;

use App\Jobs\NotifyDailyCloseMismatchJob;
use App\Models\Finance;
use App\Services\FinanceCalculatorService;
use App\Services\FinanceCloseSnapshotBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Encapsulates the exact persist path from the former Filament DailyCloseCheck::calculate()
 * lock + try/catch + transaction + failure row + job dispatch.
 */
final class PersistDailyCloseResultAction
{
    /**
     * @param  array<string, mixed>  $data  Full form state (business_date, shift, lunch_*, dinner_*)
     * @param  array<string, mixed>  $payload  payloadForSelectedShift($data)
     * @return array{ok: true, calc: array<string, mixed>, payload: array<string, mixed>, db_saved: true}|array{ok: false, reason: 'locked'|'exception', exception?: Throwable, calc?: array<string, mixed>, payload?: array<string, mixed>, db_saved?: bool}
     */
    public function execute(
        string $businessDate,
        array $data,
        array $payload,
        int $responsibleStaffId,
        float $toleranceMoinsValue,
        float $tolerancePlusValue,
        ?int $panelOperatorUserId,
    ): array {
        $shift = (string) ($data['shift'] ?? 'dinner');
        $rateKey = 'daily-close-persist:'.$businessDate.':'.$shift.':'.$responsibleStaffId;

        $locked = Cache::lock($rateKey, 10)->get(function () use ($businessDate, $data, $payload, $responsibleStaffId, $toleranceMoinsValue, $tolerancePlusValue, $panelOperatorUserId): array {
            try {
                /** @var FinanceCalculatorService $service */
                $service = app(FinanceCalculatorService::class);

                $calc = $service->calculateResult(
                    $payload,
                    $toleranceMoinsValue,
                    $tolerancePlusValue,
                );

                DB::transaction(function () use ($businessDate, $data, $calc, $payload, $responsibleStaffId, $panelOperatorUserId, $toleranceMoinsValue, $tolerancePlusValue): void {
                    Finance::query()->create([
                        'business_date' => $businessDate,
                        'shift' => $data['shift'],
                        'recettes' => $payload['recettes'],
                        'cash' => $payload['cash'],
                        'cheque' => $payload['cheque'],
                        'carte' => $payload['carte'],
                        'chips' => $payload['chips'],
                        'montant_initial' => $payload['montant_initial'] ?? 0,
                        'register_total' => $calc['measured_without_declared_tip'],
                        // 旧列 system_calculated_tip は互換維持のため残し、新ロジックの system_tip を格納。
                        'system_calculated_tip' => $calc['system_tip'],
                        'system_tip_amount' => $calc['system_tip'],
                        'declared_tip_amount' => $calc['declared_tip'],
                        'final_tip_amount' => $calc['final_tip_amount'],
                        'reserve_amount' => $calc['reserve_amount'],
                        'final_difference' => $calc['final_difference'],
                        'tolerance_used' => max($toleranceMoinsValue, $tolerancePlusValue),
                        'verdict' => $calc['verdict'],
                        'close_status' => $calc['close_status'],
                        'failure_reason' => $calc['verdict'] === 'bravo' ? null : 'outside_allowed_range',
                        'close_snapshot' => FinanceCloseSnapshotBuilder::build(
                            $payload,
                            $calc,
                            $businessDate,
                            (string) ($data['shift'] ?? 'dinner'),
                            $responsibleStaffId,
                            $panelOperatorUserId,
                        ),
                        'responsible_pin_verified' => true,
                        'panel_operator_user_id' => $panelOperatorUserId,
                        'responsible_staff_id' => $responsibleStaffId,
                        'created_by' => null,
                    ]);
                });

                if ($calc['verdict'] !== 'bravo') {
                    NotifyDailyCloseMismatchJob::dispatch(
                        $responsibleStaffId,
                        $data,
                        $calc,
                    );
                }

                return [
                    'ok' => true,
                    'calc' => $calc,
                    'payload' => $payload,
                ];
            } catch (Throwable $e) {
                // 送信押下時は異常系でも失敗レコードを残す。DB ダウン時は create が再例外となり通知が届かないため内側で隔離する。
                try {
                    Finance::query()->create([
                        'business_date' => $businessDate,
                        'shift' => (string) ($data['shift'] ?? 'dinner'),
                        'recettes' => $payload['recettes'] ?? 0,
                        'cash' => $payload['cash'] ?? 0,
                        'cheque' => $payload['cheque'] ?? 0,
                        'carte' => $payload['carte'] ?? 0,
                        'chips' => $payload['chips'] ?? 0,
                        'montant_initial' => $payload['montant_initial'] ?? 0,
                        'register_total' => 0,
                        'system_calculated_tip' => 0,
                        'system_tip_amount' => 0,
                        'declared_tip_amount' => $payload['chips'] ?? 0,
                        'final_tip_amount' => $payload['chips'] ?? 0,
                        'reserve_amount' => 0,
                        'final_difference' => 0,
                        'tolerance_used' => max($toleranceMoinsValue, $tolerancePlusValue),
                        'verdict' => 'failed',
                        'close_status' => 'failed',
                        'failure_reason' => mb_substr($e->getMessage(), 0, 240),
                        'responsible_pin_verified' => true,
                        'panel_operator_user_id' => $panelOperatorUserId,
                        'responsible_staff_id' => $responsibleStaffId,
                        'created_by' => null,
                    ]);
                } catch (Throwable $persistException) {
                    Log::critical('daily_close.calculate.failure_record_persist_failed', [
                        'original_exception' => $e->getMessage(),
                        'persist_exception' => $persistException->getMessage(),
                    ]);
                }

                return [
                    'ok' => false,
                    'reason' => 'exception',
                    'exception' => $e,
                    'db_saved' => false,
                ];
            }
        });

        if ($locked === false) {
            return ['ok' => false, 'reason' => 'locked'];
        }

        /** @var array<string, mixed> $locked */
        return $locked;
    }
}
