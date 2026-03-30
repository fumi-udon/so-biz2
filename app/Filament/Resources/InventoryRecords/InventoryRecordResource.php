<?php

namespace App\Filament\Resources\InventoryRecords;

use App\Filament\Resources\InventoryRecords\Pages\CreateInventoryRecord;
use App\Filament\Resources\InventoryRecords\Pages\EditInventoryRecord;
use App\Filament\Resources\InventoryRecords\Pages\ListInventoryRecords;
use App\Models\InventoryItem;
use App\Models\InventoryRecord;
use App\Models\Staff;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryRecordResource extends Resource
{
    protected static ?string $model = InventoryRecord::class;

    protected static ?string $navigationGroup = '本部・在庫';

    protected static ?string $modelLabel = '棚卸し記録';

    protected static ?string $pluralModelLabel = '棚卸し記録';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['inventoryItem', 'recordedByStaff']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('inventory_item_id')
                    ->label('品目')
                    ->options(fn (): array => InventoryItem::query()
                        ->where('is_active', true)
                        ->orderBy('category')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->required()
                    ->searchable()
                    ->preload(),
                DatePicker::make('date')
                    ->label('日付')
                    ->required()
                    ->native(false),
                TextInput::make('value')
                    ->label('値')
                    ->maxLength(2000),
                Select::make('recorded_by_staff_id')
                    ->label('記録者')
                    ->options(fn (): array => Staff::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
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
                TextColumn::make('inventoryItem.name')
                    ->label('品目')
                    ->weight('bold')
                    ->description(fn (InventoryRecord $record): string => '記録: '.(optional($record->recordedByStaff)->name ?? '—'))
                    ->searchable(),
                TextColumn::make('value')
                    ->label('残量')
                    ->size('lg')
                    ->weight('bold')
                    ->color('primary')
                    ->suffix(fn (InventoryRecord $record): string => ($u = optional($record->inventoryItem)->unit) ? ' '.$u : ''),
            ])
            ->filters([
                Filter::make('date')
                    ->form([
                        DatePicker::make('record_date')
                            ->label('📅 日付選択')
                            ->default(today())
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $raw = $data['record_date'] ?? today();
                        $date = $raw instanceof \Carbon\CarbonInterface
                            ? $raw->format('Y-m-d')
                            : (string) $raw;

                        return $query->whereDate('date', $date);
                    }),
            ], layout: FiltersLayout::AboveContent)
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
