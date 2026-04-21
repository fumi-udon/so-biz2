<?php

namespace App\Filament\Resources\MenuItems;

use App\Filament\Resources\MenuItems\Forms\MenuItemForm;
use App\Filament\Resources\MenuItems\Pages\CreateMenuItem;
use App\Filament\Resources\MenuItems\Pages\EditMenuItem;
use App\Filament\Resources\MenuItems\Pages\ListMenuItems;
use App\Filament\Resources\MenuItems\RelationManagers\DietaryBadgesRelationManager;
use App\Filament\Support\AdminOnlyResource;
use App\Models\MenuItem;
use App\Support\MenuItemMoney;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MenuItemResource extends AdminOnlyResource
{
    protected static ?string $model = MenuItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'メニュー・注文';

    protected static ?string $modelLabel = 'メニュー商品';

    protected static ?string $pluralModelLabel = 'メニュー商品';

    protected static ?int $navigationSort = 22;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['shop', 'menuCategory', 'dietaryBadges']);
    }

    public static function form(Form $form): Form
    {
        return MenuItemForm::configure($form);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('shop.name')->label('店舗')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('menuCategory.name')->label('カテゴリ')->searchable(),
                Tables\Columns\TextColumn::make('name')->label('商品名')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('slug')->label('スラッグ')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('from_price_minor')
                    ->label('価格 (DT)')
                    ->formatStateUsing(fn (int|float|string|null $state): string => MenuItemMoney::formatMinorForDisplay((int) $state))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('表示')->boolean(),
                Tables\Columns\IconColumn::make('allergy_note')
                    ->label('アレルギー')
                    ->boolean()
                    ->getStateUsing(fn (MenuItem $record): bool => filled($record->allergy_note)),
                Tables\Columns\TextColumn::make('sort_order')->label('順')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->label('更新')->dateTime('Y-m-d H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('shop_id')
                    ->label('店舗')
                    ->relationship('shop', 'name'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('表示')
                    ->trueLabel('表示のみ')
                    ->falseLabel('非表示のみ')
                    ->native(false),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            DietaryBadgesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMenuItems::route('/'),
            'create' => CreateMenuItem::route('/create'),
            'edit' => EditMenuItem::route('/{record}/edit'),
        ];
    }
}
