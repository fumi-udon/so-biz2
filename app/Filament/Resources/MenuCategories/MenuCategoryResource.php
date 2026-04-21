<?php

namespace App\Filament\Resources\MenuCategories;

use App\Filament\Resources\MenuCategories\Forms\MenuCategoryForm;
use App\Filament\Resources\MenuCategories\Pages\CreateMenuCategory;
use App\Filament\Resources\MenuCategories\Pages\EditMenuCategory;
use App\Filament\Resources\MenuCategories\Pages\ListMenuCategories;
use App\Filament\Support\AdminOnlyResource;
use App\Models\MenuCategory;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MenuCategoryResource extends AdminOnlyResource
{
    protected static ?string $model = MenuCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'メニュー・注文';

    protected static ?string $modelLabel = 'メニューカテゴリ';

    protected static ?string $pluralModelLabel = 'メニューカテゴリ';

    protected static ?int $navigationSort = 21;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('shop');
    }

    public static function form(Form $form): Form
    {
        return MenuCategoryForm::configure($form);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('shop.name')->label('店舗')->searchable(),
                Tables\Columns\TextColumn::make('name')->label('表示名')->searchable(),
                Tables\Columns\TextColumn::make('slug')->label('スラッグ')->searchable(),
                Tables\Columns\IconColumn::make('is_active')->label('有効')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('順')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('shop_id')
                    ->label('店舗')
                    ->relationship('shop', 'name'),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMenuCategories::route('/'),
            'create' => CreateMenuCategory::route('/create'),
            'edit' => EditMenuCategory::route('/{record}/edit'),
        ];
    }
}
