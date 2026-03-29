<?php

namespace App\Filament\Exports;

use App\Models\Staff;
use App\Support\FixedShiftsCsv;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class StaffExporter extends Exporter
{
    protected static ?string $model = Staff::class;

    public static function getColumns(): array
    {
        $base = [
            ExportColumn::make('id')
                ->label('id'),
            ExportColumn::make('shop_id')
                ->label('shop_id'),
            ExportColumn::make('name')
                ->label('name'),
            ExportColumn::make('pin_code')
                ->label('pin_code'),
            ExportColumn::make('role')
                ->label('role'),
            ExportColumn::make('target_weekly_hours')
                ->label('target_weekly_hours'),
            ExportColumn::make('wage')
                ->label('wage'),
            ExportColumn::make('job_level')
                ->label('job_level'),
            ExportColumn::make('age')
                ->label('age'),
            ExportColumn::make('gender')
                ->label('gender'),
            ExportColumn::make('origin')
                ->label('origin'),
            ExportColumn::make('note')
                ->label('note'),
            ExportColumn::make('extra_profile')
                ->label('extra_profile')
                ->getStateUsing(function (Staff $record): ?string {
                    $v = $record->extra_profile;

                    if ($v === null || $v === [] || $v === '') {
                        return null;
                    }

                    return json_encode($v, JSON_UNESCAPED_UNICODE);
                }),
            ExportColumn::make('is_active')
                ->label('is_active')
                ->formatStateUsing(fn (mixed $state): int|string => $state ? 1 : 0),
        ];

        foreach (FixedShiftsCsv::flatColumnNames() as $col) {
            $path = FixedShiftsCsv::dataGetPathFromFlatColumn($col);

            $base[] = ExportColumn::make($col)
                ->label($col)
                ->getStateUsing(function (Staff $record) use ($path): ?string {
                    if ($path === null) {
                        return null;
                    }

                    $v = data_get($record->fixed_shifts, $path);

                    if ($v === null || $v === '') {
                        return null;
                    }

                    return is_string($v) ? $v : (string) $v;
                });
        }

        return $base;
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your staff export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
