<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyDailyCloseMismatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * 第1引数名は `userId` のまま（キューに滞留した古いシリアライズとの互換用）。
     * 意味: 現行は「責任スタッフ ID（staff.id）」。旧ジョブはパネル操作者ユーザー ID の場合あり。
     *
     * @param  array<string, mixed>  $formData
     * @param  array<string, mixed>  $calcResult
     */
    public function __construct(
        public ?int $userId,
        public array $formData,
        public array $calcResult,
    ) {}

    public function handle(): void
    {
        $lines = [
            '[Clôture caisse] Écart détecté',
            'Règle : mesure (espèces + chèque + carte) = pourboire déclaré + ventes POS',
            'responsible_staff_or_legacy_user_id: '.($this->userId !== null ? (string) $this->userId : 'null'),
            'verdict: '.($this->calcResult['verdict'] ?? ''),
            'final_difference (mesure - (pourboire+POS)): '.($this->calcResult['final_difference'] ?? ''),
            'measured_without_declared_tip (espèces+chèque+carte): '.($this->calcResult['measured_without_declared_tip'] ?? ''),
            'sum_tip_plus_pos_sales: '.($this->calcResult['sum_tip_plus_pos_sales'] ?? ''),
            'expected_sales (POS): '.($this->calcResult['expected_sales'] ?? ''),
            'system_tip: '.($this->calcResult['system_tip'] ?? ''),
            'declared_tip: '.($this->calcResult['declared_tip'] ?? ''),
            'form: '.json_encode($this->formData, JSON_UNESCAPED_UNICODE),
        ];

        Log::warning(implode("\n", $lines));

        $to = config('mail.daily_close_alert_address');
        if (! is_string($to) || $to === '') {
            return;
        }

        try {
            Mail::raw(implode("\n", $lines), function ($message) use ($to): void {
                $message->to($to)->subject('[Clôture caisse] Écart détecté');
            });
        } catch (Throwable $e) {
            Log::error('NotifyDailyCloseMismatchJob mail failed: '.$e->getMessage(), ['exception' => $e]);

            throw $e;
        }
    }
}
