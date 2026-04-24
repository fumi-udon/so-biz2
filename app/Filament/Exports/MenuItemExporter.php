<?php

namespace App\Filament\Exports;

use App\Filament\Concerns\RunsFilamentCsvJobsOnSyncQueueInLocal;
use App\Models\MenuItem;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class MenuItemExporter extends Exporter
{
    use RunsFilamentCsvJobsOnSyncQueueInLocal;

    protected static ?string $model = MenuItem::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('id'),
            ExportColumn::make('shop_id')->label('shop_id'),
            ExportColumn::make('menu_category_id')->label('menu_category_id'),
            ExportColumn::make('name')->label('name'),
            ExportColumn::make('kitchen_name')->label('kitchen_name'),
            ExportColumn::make('slug')->label('slug'),
            ExportColumn::make('description')->label('description'),
            ExportColumn::make('hero_image_path')->label('hero_image_path'),
            ExportColumn::make('from_price_minor')->label('from_price_minor'),
            ExportColumn::make('sort_order')->label('sort_order'),
            ExportColumn::make('is_active')
                ->label('is_active')
                ->formatStateUsing(fn (mixed $state): int => $state ? 1 : 0),
            ExportColumn::make('allergy_note')->label('allergy_note'),
            ExportColumn::make('dietary_slugs')
                ->label('dietary_slugs')
                ->state(function (MenuItem $record): string {
                    return $record->dietaryBadges->pluck('slug')->implode(',');
                }),
            ExportColumn::make('options_payload')
                ->label('options_payload')
                ->state(function (MenuItem $record): string {
                    $payload = $record->options_payload;

                    return is_array($payload)
                        ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        : (string) ($payload ?? '');
                }),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Export completed. '.Number::format($export->successful_rows).' rows.';
    }
}
