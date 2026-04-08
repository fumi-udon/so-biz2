<?php

namespace App\Filament\Resources\DailyTips;

use App\Filament\Resources\DailyTips\Pages\CalculateTips;
use App\Filament\Resources\DailyTips\Pages\CreateDailyTip;
use App\Filament\Resources\DailyTips\Pages\EditDailyTip;
use App\Filament\Resources\DailyTips\Pages\ListDailyTips;
use App\Filament\Resources\DailyTips\Pages\TipDashboard;
use App\Filament\Resources\DailyTips\RelationManagers\DailyTipDistributionRelationManager;
use App\Filament\Support\AdminOnlyResource;
use App\Models\DailyTip;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DailyTipResource extends AdminOnlyResource
{
    protected static ?string $model = DailyTip::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Pourboires';

    protected static ?string $modelLabel = 'Daily Tip';

    protected static ?string $pluralModelLabel = 'Daily Tips';

    public static function form(Form $form): Form
    {
        return $form->schema([
            DatePicker::make('business_date')
                ->required()
                ->native(false),
            Select::make('shift')
                ->required()
                ->options([
                    'lunch' => 'Lunch',
                    'dinner' => 'Dinner',
                ]),
            TextInput::make('total_amount')
                ->required()
                ->numeric()
                ->step(0.001)
                ->minValue(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('business_date')->date()->sortable(),
                TextColumn::make('shift')->badge(),
                TextColumn::make('total_amount')->numeric(decimalPlaces: 3),
                TextColumn::make('distributions_count')->label('対象人数')->counts('distributions'),
            ])
            ->defaultSort('business_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            DailyTipDistributionRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => TipDashboard::route('/'),
            'list_all' => ListDailyTips::route('/list-all'),
            'calculate' => CalculateTips::route('/calculate'),
            'create' => CreateDailyTip::route('/create'),
            'edit' => EditDailyTip::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('distributions');
    }
}
