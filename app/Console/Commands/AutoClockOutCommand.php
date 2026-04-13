<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AutoClockOutResult;
use App\Services\AutoClockOutService;
use App\Support\BusinessDate;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class AutoClockOutCommand extends Command
{
    protected $signature = 'app:auto-clock-out
                            {--date= : 処理対象の営業日を YYYY-MM-DD で指定（省略時は BusinessDate::current()）}';

    protected $description = '退勤打刻漏れスタッフに対し「シフト予定退勤 - ペナルティ分」を自動補完する（深夜バッチ専用）。';

    public function handle(AutoClockOutService $service): int
    {
        $businessDate = $this->resolveBusinessDate();

        if ($businessDate === null) {
            return self::FAILURE;
        }

        $dateString = $businessDate->toDateString();
        $this->info("AutoClockOut: 対象日={$dateString}");

        $result = $service->runForDate($businessDate);

        // ログ出力（補完ゼロでも記録）
        Log::info('AutoClockOut: 完了', [
            'date' => $dateString,
            'filled_count' => $result->filledCount(),
            'skipped_count' => $result->skippedCount(),
            'filled' => $result->filled,
            'skipped' => $result->skipped,
        ]);

        foreach ($result->filled as $row) {
            $this->line("  [補完] {$row['staff_name']} ({$row['meal']}) → {$row['out_at']}");
        }

        foreach ($result->skipped as $row) {
            $this->warn("  [スキップ] {$row['staff_name']} ({$row['meal']}) reason={$row['reason']}");
        }

        // 通知: 補完またはスキップが1件以上のときのみ管理者へ送信
        if ($result->needsNotification()) {
            $this->notifyAdmins($dateString, $result);
        }

        $this->info("AutoClockOut: 補完={$result->filledCount()} / スキップ={$result->skippedCount()}");

        return self::SUCCESS;
    }

    /**
     * --date オプションを解釈し営業日 Carbon を返す。
     * 省略時は BusinessDate::current()。
     * 不正なフォーマットなら null（コマンド失敗）。
     */
    private function resolveBusinessDate(): ?Carbon
    {
        $rawDate = $this->option('date');

        if ($rawDate === null || $rawDate === '') {
            return BusinessDate::current()->startOfDay();
        }

        // 厳格な Y-m-d 形式チェック
        $parsed = Carbon::createFromFormat('Y-m-d', $rawDate, config('app.business_timezone'));

        if ($parsed === false || $parsed->format('Y-m-d') !== $rawDate) {
            $this->error("AutoClockOut: --date のフォーマットが不正です（期待値: YYYY-MM-DD）。入力値: {$rawDate}");

            return null;
        }

        return $parsed->startOfDay();
    }

    private function notifyAdmins(string $date, AutoClockOutResult $result): void
    {
        $admins = User::all();

        if ($admins->isEmpty()) {
            return;
        }

        $body = "対象日: {$date} — 自動補完: {$result->filledCount()}件";

        if ($result->skippedCount() > 0) {
            $names = collect($result->skipped)
                ->map(fn (array $r): string => "{$r['staff_name']}(".($r['meal'] === 'lunch' ? 'Midi' : 'Soir').')')
                ->join(', ');
            $body .= " / 手動修正が必要（シフト未定義等）: {$names}";
        }

        Notification::make()
            ->title('🤖 自動退勤補完が実行されました')
            ->body($body)
            ->warning()
            ->sendToDatabase($admins);
    }
}
