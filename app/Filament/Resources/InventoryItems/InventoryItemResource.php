<?php

namespace App\Filament\Resources\InventoryItems;

use App\Filament\Resources\InventoryItems\Pages\CreateInventoryItem;
use App\Filament\Resources\InventoryItems\Pages\EditInventoryItem;
use App\Filament\Resources\InventoryItems\Pages\ListInventoryItems;
use App\Models\InventoryItem;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class InventoryItemResource extends Resource
{
    protected static ?string $model = InventoryItem::class;

    protected static string|UnitEnum|null $navigationGroup = '本部・在庫';

    protected static ?string $modelLabel = '棚卸し品目';

    protected static ?string $pluralModelLabel = '棚卸し品目';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['shop', 'assignedStaff']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('shop_id')
                    ->relationship('shop', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('category')
                    ->required()
                    ->maxLength(255),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('timing')
                    ->default('close')
                    ->maxLength(255),
                Select::make('assigned_staff_id')
                    ->relationship('assignedStaff', 'name', fn (Builder $q) => $q->where('is_active', true))
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('unit')
                    ->required()
                    ->maxLength(50),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('shop.name')->label('店舗')->searchable(),
                TextColumn::make('category')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('timing'),
                TextColumn::make('assignedStaff.name')->label('担当'),
                TextColumn::make('unit'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryItems::route('/'),
            'create' => CreateInventoryItem::route('/create'),
            'edit' => EditInventoryItem::route('/{record}/edit'),
        ];
    }
}
