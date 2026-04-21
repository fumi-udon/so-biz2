<?php

namespace App\Filament\Resources\TableSessionSettlements;

use App\Filament\Resources\TableSessionSettlements\Pages\ListTableSessionSettlements;
use App\Filament\Resources\TableSessionSettlements\Pages\ViewSettlementDuplicata;
use App\Models\TableSessionSettlement;
use App\Support\MenuItemMoney;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TableSessionSettlementResource extends Resource
{
    protected static ?string $model = TableSessionSettlement::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 18;

    public static function getNavigationLabel(): string
    {
        return __('pos.settlement_history_nav');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'メニュー・注文';
    }

    public static function getModelLabel(): string
    {
        return __('pos.settlement_history_nav');
    }

    public static function getPluralModelLabel(): string
    {
        return __('pos.settlement_history_nav');
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return static::canPrintDuplicata(auth()->user());
    }

    /**
     * 履歴からの DUPLICATA（再印刷）を manager / super_admin のみに限定。
     */
    public static function canPrintDuplicata(?Authenticatable $user): bool
    {
        if ($user === null || ! $user->canAccessPanel(Filament::getCurrentPanel())) {
            return false;
        }

        $superAdmin = config('filament-shield.super_admin.name', 'super_admin');

        return $user->hasRole($superAdmin) || $user->hasRole('manager');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['shop', 'tableSession.restaurantTable']);
    }

    public static function form(Form $form): Form
    {
        return $form;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('settled_at')
                    ->label(__('pos.settlement_column_settled_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('shop.name')
                    ->label('店舗')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tableSession.restaurantTable.name')
                    ->label('卓')
                    ->default('—')
                    ->description(fn (TableSessionSettlement $record): string => 'Session #'.$record->table_session_id),
                Tables\Columns\TextColumn::make('final_total_minor')
                    ->label('TOTAL')
                    ->alignEnd()
                    ->formatStateUsing(fn (int|string|null $state): string => MenuItemMoney::formatMinorForDisplay((int) $state))
                    ->sortable(),
            ])
            ->defaultSort('settled_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('duplicata')
                    ->label(__('pos.settlement_history_duplicata'))
                    ->icon('heroicon-o-printer')
                    ->url(fn (TableSessionSettlement $record): string => static::getUrl('duplicata', ['record' => $record]))
                    ->visible(fn (): bool => static::canPrintDuplicata(auth()->user())),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTableSessionSettlements::route('/'),
            'duplicata' => ViewSettlementDuplicata::route('/{record}/duplicata'),
        ];
    }
}
