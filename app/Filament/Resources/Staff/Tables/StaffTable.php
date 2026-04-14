<?php

namespace App\Filament\Resources\Staff\Tables;

use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ReplicateAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class StaffTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('shop.name')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('role'),
                TextColumn::make('jobLevel.name')
                    ->label('Job level')
                    ->placeholder('—'),
                TextColumn::make('hourly_wage')
                    ->label('時給')
                    ->formatStateUsing(fn ($state): string => $state === null ? '—' : number_format((float) $state, 3, '.', ' ').' DT/h'),
                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make(),
                RestoreAction::make(),
                ReplicateAction::make()
                    ->label('データをコピー')
                    ->modalHeading('スタッフを複製')
                    ->modalSubmitActionLabel('複製して作成')
                    ->successNotificationTitle('コピーしました')
                    ->excludeAttributes([
                        'pin_code',
                    ])
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['name'] = ($data['name'] ?? '').' - コピー';
                        $data['pin_code'] = null;
                        $data['is_active'] = false;

                        return $data;
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
