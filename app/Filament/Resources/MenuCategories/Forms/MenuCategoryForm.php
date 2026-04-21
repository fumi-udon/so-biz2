<?php

namespace App\Filament\Resources\MenuCategories\Forms;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;

class MenuCategoryForm
{
    private const string MARIO_SKY =
        'rounded-2xl border-2 border-b-4 border-sky-400 bg-gradient-to-b from-sky-50 to-white text-gray-950 shadow-sm dark:border-sky-600 dark:from-slate-900 dark:to-slate-950 dark:text-white';

    public static function configure(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('カテゴリ')
                    ->description('同一店舗内でスラッグは一意。ゲストのカテゴリレール順は並び順です。')
                    ->compact()
                    ->columns(['default' => 1, 'sm' => 2])
                    ->schema([
                        Select::make('shop_id')
                            ->label('店舗')
                            ->relationship('shop', 'name', fn ($query) => $query->where('is_active', true)->orderBy('name'))
                            ->required()
                            ->searchable()
                            ->preload(),
                        TextInput::make('name')
                            ->label('表示名')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->label('スラッグ')
                            ->required()
                            ->maxLength(255)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->columnSpan(['default' => 1, 'sm' => 2]),
                        TextInput::make('sort_order')
                            ->label('並び順')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->step(1),
                        Toggle::make('is_active')
                            ->label('有効')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->extraAttributes(['class' => self::MARIO_SKY]),
            ]);
    }
}
