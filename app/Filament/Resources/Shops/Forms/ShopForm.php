<?php

namespace App\Filament\Resources\Shops\Forms;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;

class ShopForm
{
    public static function configure(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('URLスラッグ（ゲストメニュー）')
                    ->nullable()
                    ->maxLength(64)
                    ->unique(table: 'shops', column: 'slug', ignoreRecord: true)
                    ->rules(['nullable', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'])
                    ->helperText('例: soya / bistronippon。空ならゲスト URL と未連携。'),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
