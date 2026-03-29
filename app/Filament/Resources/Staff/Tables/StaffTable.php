<?php

namespace App\Filament\Resources\Staff\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\RestoreAction;
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
                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                RestoreAction::make(),
                ReplicateAction::make()
                    ->label('データをコピー')
                    ->modalHeading('スタッフを複製')
                    ->modalSubmitActionLabel('複製して作成')
                    ->successNotificationTitle('コピーしました')
                    ->excludeAttributes([
                        'name',
                        'pin_code',
                    ])
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['name'] = '';
                        $data['pin_code'] = null;

                        return $data;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
