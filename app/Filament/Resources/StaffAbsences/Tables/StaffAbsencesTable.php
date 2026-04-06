<?php

namespace App\Filament\Resources\StaffAbsences\Tables;

use App\Models\StaffAbsence;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StaffAbsencesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('staff.name')
                    ->label('スタッフ')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date')
                    ->label('日付')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('meal_type')
                    ->label('区分')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        StaffAbsence::MEAL_LUNCH => 'ランチ',
                        StaffAbsence::MEAL_DINNER => 'ディナー',
                        StaffAbsence::MEAL_FULL => '全日',
                        default => $state,
                    })
                    ->badge(),
                TextColumn::make('note')
                    ->label('メモ')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('更新')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('date', 'desc')
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
