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
    protected static ?int $sort = -8;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $dateString = BusinessDate::toDateString();

        $tableName = (new InventoryRecord)->getTable();
        $itemsTableName = (new InventoryItem)->getTable();

        // プレフィックスを手動取得して完全なテーブル名を組み立てる（whereRaw / orderByRaw 用）
        $prefix = DB::connection()->getTablePrefix();
        $fullTableName = $prefix.$tableName;

        $threshold = 5;

        $valueCast = match (DB::getDriverName()) {
            'sqlite' => "CAST({$fullTableName}.value AS REAL)",
            default => "CAST({$fullTableName}.value AS DECIMAL(14,4))",
        };

        return $table
            ->heading('在庫低下アラート（本日・数値棚卸し／残量5以下）')
            ->description('当日の棚卸し記録が数値で、残量が閾値以下の品目を最大5件表示します。')
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
                    ->label('品目'),
                TextColumn::make('inventoryItem.category')
                    ->label('カテゴリ'),
                TextColumn::make('value')
                    ->label('残量')
                    ->formatStateUsing(fn (?string $state): string => $state ?? '—')
                    ->color('danger')
                    ->weight('bold'),
                TextColumn::make('inventoryItem.unit')
                    ->label('単位'),
            ])
            ->paginated(false);
    }
}
