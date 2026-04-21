<?php

namespace App\Filament\Resources\MenuItems\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DietaryBadgesRelationManager extends RelationManager
{
    protected static string $relationship = 'dietaryBadges';

    protected static ?string $title = '食事・アイコンバッジ';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\ImageColumn::make('icon_path')
                    ->label('')
                    ->disk('public')
                    ->height(36)
                    ->width(36)
                    ->circular()
                    ->defaultImageUrl('data:image/svg+xml,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 36 36"><rect fill="#e2e8f0" width="36" height="36" rx="8"/><text x="18" y="22" text-anchor="middle" fill="#64748b" font-size="14">?</text></svg>')),
                Tables\Columns\TextColumn::make('slug')
                    ->label('スラッグ')
                    ->searchable()
                    ->color('gray')
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('name')
                    ->label('表示名')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('pivot.sort_order')
                    ->label('並び')
                    ->sortable(true, function (Builder $query, string $direction): Builder {
                        return $query->orderBy('menu_item_dietary_badge.sort_order', $direction);
                    }),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(function (Builder $query): Builder {
                        $shopId = $this->getOwnerRecord()->shop_id;

                        return $query
                            ->where('is_active', true)
                            ->where(function (Builder $q) use ($shopId): void {
                                $q->whereNull('shop_id')->orWhere('shop_id', $shopId);
                            })
                            ->orderBy('dietary_badges.sort_order')
                            ->orderBy('dietary_badges.name');
                    })
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        TextInput::make('sort_order')
                            ->label('並び順')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->minValue(0)
                            ->step(1),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form([
                        TextInput::make('sort_order')
                            ->label('並び順')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->step(1),
                    ]),
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make(),
            ])
            ->defaultSort('menu_item_dietary_badge.sort_order');
    }
}
