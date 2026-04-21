<?php

namespace App\Filament\Exports;

use App\Models\MenuCategory;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class MenuCategoryExporter extends Exporter
{
    protected static ?string $model = MenuCategory::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('id'),
            ExportColumn::make('shop_id')->label('shop_id'),
            ExportColumn::make('name')->label('name'),
            ExportColumn::make('slug')->label('slug'),
            ExportColumn::make('sort_order')->label('sort_order'),
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
