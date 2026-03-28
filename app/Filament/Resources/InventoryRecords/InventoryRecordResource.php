<?php

namespace App\Filament\Resources\InventoryRecords;

use App\Filament\Resources\InventoryRecords\Pages\CreateInventoryRecord;
use App\Filament\Resources\InventoryRecords\Pages\EditInventoryRecord;
use App\Filament\Resources\InventoryRecords\Pages\ListInventoryRecords;
use App\Models\InventoryRecord;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class InventoryRecordResource extends Resource
{
    protected static ?string $model = InventoryRecord::class;

    protected static string|UnitEnum|null $navigationGroup = '本部・在庫';

    protected static ?string $modelLabel = '棚卸し記録';

    protected static ?string $pluralModelLabel = '棚卸し記録';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['inventoryItem', 'recordedByStaff']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('inventory_item_id')
                    ->relationship('inventoryItem', 'name', fn (Builder $q) => $q->where('is_active', true))
                    ->required()
                    ->searchable()
                    ->preload(),
                DatePicker::make('date')
                    ->required()
                    ->native(false),
                TextInput::make('quantity')
                    ->numeric()
                    ->required()
                    ->step(0.01),
                Select::make('recorded_by_staff_id')
                    ->relationship('recordedByStaff', 'name', fn (Builder $q) => $q->where('is_active', true))
                    ->required()
                    ->searchable()
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->paginated(false)
            ->groups([
                Group::make('inventoryItem.category')
                    ->label('カテゴリ')
                    ->titlePrefixedWithLabel(false)
                    ->collapsible(),
            ])
            ->defaultGroup('inventoryItem.category')
            ->columns([
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('inventoryItem.name')
                    ->label('品目')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('inventoryItem.unit')
                    ->label('単位'),
                TextColumn::make('recordedByStaff.name')
                    ->label('記録者'),
            ])
            ->defaultSort('date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryRecords::route('/'),
            'create' => CreateInventoryRecord::route('/create'),
            'edit' => EditInventoryRecord::route('/{record}/edit'),
        ];
    }
}
