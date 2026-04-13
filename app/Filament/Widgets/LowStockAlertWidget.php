<?php

namespace App\Filament\Widgets;

use App\Models\InventoryItem;
use App\Models\InventoryRecord;
use App\Support\BusinessDate;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class LowStockAlertWidget extends TableWidget
{
    protected static ?int $sort = -5;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        if (auth()->user()?->isPiloteOnly() === true) {
            return false;
        }

        return parent::canView();
    }

    public function table(Table $table): Table
    {
        $dateString = BusinessDate::toDateString();

        $tableName = (new InventoryRecord)->getTable();
        $itemsTableName = (new InventoryItem)->getTable();

        // Préfixe DB pour whereRaw / orderByRaw
        $prefix = DB::connection()->getTablePrefix();
        $fullTableName = $prefix.$tableName;

        $threshold = 5;

        $valueCast = match (DB::getDriverName()) {
            'sqlite' => "CAST({$fullTableName}.value AS REAL)",
            default => "CAST({$fullTableName}.value AS DECIMAL(14,4))",
        };

        return $table
            ->heading('Alerte stock bas (inventaire numérique, reste ≤ 5)')
            ->description('Articles inventoriés en quantité aujourd’hui avec reste sous le seuil (5 max).')
            ->query(
                InventoryRecord::query()
                    ->select($tableName.'.*')
                    ->join($itemsTableName, $itemsTableName.'.id', '=', $tableName.'.inventory_item_id')
                    ->whereDate($tableName.'.date', $dateString)
                    ->where($itemsTableName.'.is_active', true)
                    ->where($itemsTableName.'.input_type', 'number')
                    ->whereNotNull($tableName.'.value')
                    ->where($tableName.'.value', '!=', '')
                    ->whereRaw("{$valueCast} <= ?", [$threshold])
                    ->with('inventoryItem')
                    ->orderByRaw("{$valueCast} asc")
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('inventoryItem.name')
                    ->label('Article')
                    ->extraCellAttributes(['class' => 'text-[12px] font-medium text-gray-950 dark:text-gray-100']),
                TextColumn::make('inventoryItem.category')
                    ->label('Catégorie')
                    ->extraCellAttributes(['class' => 'text-[12px] text-gray-900 dark:text-gray-100']),
                TextColumn::make('value')
                    ->label('Reste')
                    ->formatStateUsing(fn (?string $state): string => $state ?? '—')
                    ->color('danger')
                    ->weight('bold')
                    ->extraCellAttributes(['class' => 'text-[12px]']),
                TextColumn::make('inventoryItem.unit')
                    ->label('Unité')
                    ->extraCellAttributes(['class' => 'text-[12px] text-gray-900 dark:text-gray-100']),
            ])
            ->paginated(false);
    }
}
