<?php

namespace App\Filament\Resources\InventoryItems;

use App\Filament\Resources\InventoryItems\Pages\CreateInventoryItem;
use App\Filament\Resources\InventoryItems\Pages\EditInventoryItem;
use App\Filament\Resources\InventoryItems\Pages\ListInventoryItems;
use App\Models\InventoryItem;
use App\Models\Shop;
use App\Models\Staff;
use App\Support\InventorySettingOptions;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
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
                    ->label('店舗')
                    ->options(fn (): array => Shop::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('category')
                    ->label('カテゴリ')
                    ->options(fn (Get $get): array => InventorySettingOptions::categoryForSelect($get('category')))
                    ->required()
                    ->searchable()
                    ->placeholder('カテゴリを選択')
                    ->hint('候補は 設定（inventory_category_options）で編集できます。'),
                TextInput::make('name')
                    ->label('品目名')
                    ->required()
                    ->maxLength(255),
                Select::make('timing')
                    ->label('確認タイミング')
                    ->options(fn (Get $get): array => InventorySettingOptions::timingForSelect($get('timing')))
                    ->default(fn (): ?string => array_key_first(InventorySettingOptions::timingForSelect()) ?? 'close')
                    ->required()
                    ->searchable()
                    ->placeholder('タイミングを選択')
                    ->hint('候補は 設定（inventory_timing_options）で編集できます。'),
                Select::make('assigned_staff_id')
                    ->label('担当スタッフ')
                    ->options(fn (): array => Staff::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('unit')
                    ->label('単位')
                    ->options(fn (Get $get): array => InventorySettingOptions::unitForSelect($get('unit')))
                    ->required()
                    ->searchable()
                    ->placeholder('単位を選択')
                    ->hint('候補は 設定（inventory_unit_options）で編集できます。'),
                Select::make('input_type')
                    ->label('入力形式')
                    ->options([
                        'number' => '数値',
                        'text' => '通常テキスト',
                        'select' => 'プルダウン',
                    ])
                    ->default('number')
                    ->live(),
                TagsInput::make('dropdown_options')
                    ->label('プルダウンの選択肢 (Enterで追加)')
                    ->placeholder('選択肢を入力')
                    ->visible(fn (Get $get): bool => $get('input_type') === 'select'),
                Toggle::make('is_active')
                    ->label('有効')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('shop.name')->label('店舗')->searchable(),
                TextColumn::make('category')->label('カテゴリ')->searchable(),
                TextColumn::make('name')->label('品目名')->searchable(),
                TextColumn::make('timing')->label('タイミング'),
                TextColumn::make('assignedStaff.name')->label('担当'),
                TextColumn::make('input_type')->label('入力形式'),
                TextColumn::make('unit')->label('単位'),
                IconColumn::make('is_active')->label('有効')->boolean(),
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
