<?php

namespace App\Filament\Exports;

use App\Filament\Concerns\RunsFilamentCsvJobsOnSyncQueueInLocal;
use App\Models\Shop;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class ShopExporter extends Exporter
{
    use RunsFilamentCsvJobsOnSyncQueueInLocal;

    protected static ?string $model = Shop::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('id'),
            ExportColumn::make('name')
                ->label('name'),
            ExportColumn::make('is_active')
                ->label('is_active')
                ->formatStateUsing(fn (mixed $state): int|string => $state ? 1 : 0),
            ExportColumn::make('created_at')
                ->label('created_at'),
            ExportColumn::make('updated_at')
                ->label('updated_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your shop export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
