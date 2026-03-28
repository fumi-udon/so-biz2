<?php

namespace App\Filament\Exports;

use App\Models\InventoryItem;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class InventoryItemExporter extends Exporter
{
    protected static ?string $model = InventoryItem::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('id'),
            ExportColumn::make('shop_id')->label('shop_id'),
            ExportColumn::make('name')->label('name'),
            ExportColumn::make('category')->label('category'),
            ExportColumn::make('timing')->label('timing'),
            ExportColumn::make('assigned_staff_id')->label('assigned_staff_id'),
            ExportColumn::make('unit')->label('unit'),
            ExportColumn::make('input_type')->label('input_type'),
            ExportColumn::make('options')
                ->label('options')
                ->formatStateUsing(fn ($state): string => is_array($state) ? json_encode($state, JSON_UNESCAPED_UNICODE) : (string) $state),
            ExportColumn::make('is_active')
                ->label('is_active')
                ->formatStateUsing(fn (mixed $state): int => $state ? 1 : 0),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Export completed. '.Number::format($export->successful_rows).' rows.';
    }
}
