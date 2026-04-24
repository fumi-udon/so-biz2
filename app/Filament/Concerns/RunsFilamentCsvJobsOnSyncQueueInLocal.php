<?php

namespace App\Filament\Concerns;

use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Filament の CSV エクスポート／インポートは {@see ShouldQueue} ジョブで処理される。
 * `QUEUE_CONNECTION=database` 等でキューワーカーを起動していないとジョブが滞留し、
 * 「Export started」のまま完了しない。local 環境では sync で同期的に実行する。
 */
trait RunsFilamentCsvJobsOnSyncQueueInLocal
{
    public function getJobConnection(): ?string
    {
        if (app()->environment('local')) {
            return 'sync';
        }

        return parent::getJobConnection();
    }
}
