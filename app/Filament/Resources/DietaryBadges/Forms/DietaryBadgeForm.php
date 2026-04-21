<?php

namespace App\Filament\Resources\DietaryBadges\Forms;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;

class DietaryBadgeForm
{
    private const string MARIO_SKY =
        'rounded-2xl border-2 border-b-4 border-sky-400 bg-gradient-to-b from-sky-50 to-white text-gray-950 shadow-sm dark:border-sky-600 dark:from-slate-900 dark:to-slate-950 dark:text-white';

    private const string MARIO_COIN =
        'rounded-2xl border-2 border-b-4 border-amber-500 bg-gradient-to-b from-amber-100 to-yellow-50 text-gray-950 shadow-sm dark:border-amber-600 dark:from-amber-950/70 dark:to-slate-900 dark:text-white';

    public static function configure(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('バッジ定義')
                    ->description('店舗未選択＝全店で使える共通バッジ。スラッグは全体で一意です。')
                    ->compact()
                    ->columns(['default' => 1, 'sm' => 2])
                    ->schema([
                        Select::make('shop_id')
                            ->label('店舗（空＝共通）')
                            ->relationship('shop', 'name', fn ($query) => $query->where('is_active', true)->orderBy('name'))
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        TextInput::make('slug')
                            ->label('スラッグ')
                            ->required()
                            ->maxLength(64)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->helperText('例: vegan, vegetarian, gluten-free'),
                        TextInput::make('name')
                            ->label('表示名')
                            ->required()
                            ->maxLength(120)
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

                Section::make('アイコン画像')
                    ->description('SVG / ラスタ。管理画面用。ゲストは storage URL で表示（次 Phase）。')
                    ->compact()
                    ->schema([
                        FileUpload::make('icon_path')
                            ->label('アイコン')
                            ->disk('public')
                            ->directory('dietary-badges/icons')
                            ->visibility('public')
                            ->acceptedFileTypes([
                                'image/svg+xml',
                                'image/png',
                                'image/jpeg',
                                'image/webp',
                                'image/gif',
                            ])
                            ->maxSize(1024)
                            ->downloadable()
                            ->openable()
                            ->imagePreviewHeight('96'),
                    ])
                    ->extraAttributes(['class' => self::MARIO_COIN]),
            ]);
    }
}
