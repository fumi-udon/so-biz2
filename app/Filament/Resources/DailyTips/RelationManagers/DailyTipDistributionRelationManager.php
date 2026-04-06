<?php

namespace App\Filament\Resources\DailyTips\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DailyTipDistributionRelationManager extends RelationManager
{
    protected static string $relationship = 'distributions';

    protected static ?string $title = 'Tip Distributions';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('staff.name')
            ->columns([
                TextColumn::make('staff.name')->label('Staff Name')->searchable(),
                TextColumn::make('weight')->numeric(decimalPlaces: 3),
                TextColumn::make('amount')->numeric(decimalPlaces: 3),
                IconColumn::make('is_tardy_deprived')->label('Tardy')->boolean(),
                IconColumn::make('is_manual_added')->label('Manual')->boolean(),
                TextColumn::make('note')->limit(50)->wrap(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
