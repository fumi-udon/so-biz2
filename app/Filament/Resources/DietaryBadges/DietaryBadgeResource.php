<?php

namespace App\Filament\Resources\DietaryBadges;

use App\Filament\Resources\DietaryBadges\Forms\DietaryBadgeForm;
use App\Filament\Resources\DietaryBadges\Pages\CreateDietaryBadge;
use App\Filament\Resources\DietaryBadges\Pages\EditDietaryBadge;
use App\Filament\Resources\DietaryBadges\Pages\ListDietaryBadges;
use App\Filament\Support\AdminOnlyResource;
use App\Models\DietaryBadge;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DietaryBadgeResource extends AdminOnlyResource
{
    protected static ?string $model = DietaryBadge::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'メニュー・注文';

    protected static ?string $modelLabel = '食事バッジ';

    protected static ?string $pluralModelLabel = '食事バッジ';

    protected static ?int $navigationSort = 20;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('shop');
    }

    public static function form(Form $form): Form
    {
        return DietaryBadgeForm::configure($form);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('icon_path')
                    ->label('')
                    ->disk('public')
                    ->height(36)
                    ->width(36)
                    ->circular()
                    ->defaultImageUrl('data:image/svg+xml,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 36 36"><rect fill="#fde047" width="36" height="36" rx="8"/><text x="18" y="23" text-anchor="middle" fill="#854d0e" font-size="16">?</text></svg>')),
                Tables\Columns\TextColumn::make('slug')->label('スラッグ')->searchable(),
                Tables\Columns\TextColumn::make('name')->label('表示名')->searchable(),
                Tables\Columns\TextColumn::make('shop.name')
                    ->label('店舗')
                    ->placeholder('共通')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->label('有効')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('順')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('badge_scope')
                    ->label('スコープ')
                    ->trueLabel('店舗別のみ')
                    ->falseLabel('共通のみ')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('shop_id'),
                        false: fn (Builder $q) => $q->whereNull('shop_id'),
                    ),
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
            'index' => ListDietaryBadges::route('/'),
            'create' => CreateDietaryBadge::route('/create'),
            'edit' => EditDietaryBadge::route('/{record}/edit'),
        ];
    }
}
