<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function canAccess(): bool
    {
        if (auth()->user()?->isPiloteOnly()) {
            return false;
        }

        return parent::canAccess();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('roles');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('名前')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('メールアドレス')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('password')
                    ->label('パスワード')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->minLength(8)
                    ->maxLength(255)
                    ->dehydrated(fn ($state): bool => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit'
                        ? '空欄のままならパスワードは変更されません。'
                        : null),
                TextInput::make('role')
                    ->label('ロール（レガシー・非推奨）')
                    ->maxLength(255)
                    ->hidden(),
                CheckboxList::make('roles')
                    ->label('Spatieロール')
                    ->relationship(
                        'roles',
                        'name',
                        fn ($query) => $query->orderBy('name'),
                    )
                    ->columns(2)
                    ->gridDirection('row')
                    ->searchable()
                    ->bulkToggleable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('名前')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('メールアドレス'),
                TextColumn::make('role')
                    ->label('ロール（レガシー）')
                    ->placeholder('—')
                    ->hidden(),
                TextColumn::make('roles.name')
                    ->label('Spatieロール')
                    ->badge()
                    ->separator(' ')
                    ->placeholder('—'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
