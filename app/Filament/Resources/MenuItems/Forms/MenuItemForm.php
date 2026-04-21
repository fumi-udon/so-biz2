<?php

namespace App\Filament\Resources\MenuItems\Forms;

use App\Models\MenuCategory;
use App\Rules\MenuItemOptionIdFormat;
use App\Rules\MenuItemSlugFormat;
use App\Support\MenuItemMoney;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Str;

/**
 * Dense, mobile-first form layout (reference: AttendanceForm + Mario-inspired blocks).
 * Contrast: explicit light/dark text on tinted surfaces per .cursorrules.
 */
class MenuItemForm
{
    /** Sky pipe block */
    private const string MARIO_SKY =
        'rounded-2xl border-2 border-b-4 border-sky-400 bg-gradient-to-b from-sky-50 to-white text-gray-950 shadow-sm dark:border-sky-600 dark:from-slate-900 dark:to-slate-950 dark:text-white';

    /** Ground / grass */
    private const string MARIO_GRASS =
        'rounded-2xl border-2 border-b-4 border-emerald-500 bg-gradient-to-b from-emerald-50 to-green-50 text-gray-950 shadow-sm dark:border-emerald-700 dark:from-emerald-950/80 dark:to-slate-900 dark:text-white';

    /** Brick / power-up panel */
    private const string MARIO_BRICK =
        'rounded-2xl border-2 border-b-4 border-red-500 bg-gradient-to-b from-orange-50 to-amber-50 text-gray-950 shadow-sm dark:border-red-700 dark:from-slate-900 dark:to-slate-950 dark:text-white';

    /** Coin strip */
    private const string MARIO_COIN =
        'rounded-2xl border-2 border-b-4 border-amber-500 bg-gradient-to-b from-amber-100 to-yellow-50 text-gray-950 shadow-sm dark:border-amber-600 dark:from-amber-950/70 dark:to-slate-900 dark:text-white';

    /**
     * Normalize options_payload for form fill (create/edit) so nested dot paths hydrate reliably.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function hydrateOptionsPayloadForForm(array $data): array
    {
        $raw = $data['options_payload'] ?? null;
        $op = is_array($raw) ? $raw : [];

        $rules = is_array($op['rules'] ?? null) ? $op['rules'] : [];
        $data['options_payload'] = [
            'rules' => [
                'style_required' => (bool) ($rules['style_required'] ?? false),
            ],
            'styles' => array_values(array_filter(
                is_array($op['styles'] ?? null) ? $op['styles'] : [],
                static fn ($row): bool => is_array($row),
            )),
            'toppings' => array_values(array_filter(
                is_array($op['toppings'] ?? null) ? $op['toppings'] : [],
                static fn ($row): bool => is_array($row),
            )),
        ];

        return $data;
    }

    /**
     * Persist a clean options_payload tree (drop empty repeater rows, null when nothing set).
     *
     * @param  array<string, mixed>|null  $payload
     */
    public static function normalizeOptionsPayloadBeforeSave(?array $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $styleRequired = (bool) ($payload['rules']['style_required'] ?? false);

        $usedStyleIds = [];
        $styles = [];
        foreach ($payload['styles'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '' && $name !== '') {
                $id = Str::slug($name) ?: 'option';
            }
            if ($id === '') {
                continue;
            }
            $id = self::makeUniqueOptionId($id, $usedStyleIds);
            $styles[] = [
                'id' => $id,
                'name' => (string) ($row['name'] ?? ''),
                'price_minor' => MenuItemMoney::normalizePersistedOptionMinor($row['price_minor'] ?? 0),
            ];
        }

        $usedToppingIds = [];
        $toppings = [];
        foreach ($payload['toppings'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '' && $name !== '') {
                $id = Str::slug($name) ?: 'option';
            }
            if ($id === '') {
                continue;
            }
            $id = self::makeUniqueOptionId($id, $usedToppingIds);
            $toppings[] = [
                'id' => $id,
                'name' => (string) ($row['name'] ?? ''),
                'price_delta_minor' => MenuItemMoney::normalizePersistedOptionMinor($row['price_delta_minor'] ?? 0),
            ];
        }

        if (! $styleRequired && $styles === [] && $toppings === []) {
            return null;
        }

        return [
            'rules' => ['style_required' => $styleRequired],
            'styles' => array_values($styles),
            'toppings' => array_values($toppings),
        ];
    }

    public static function configure(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('商品の基本')
                    ->description('店舗・カテゴリ・表示名。スマホでも崩れないよう 1 列 / 2 列切替。')
                    ->compact()
                    ->columns(['default' => 1, 'sm' => 2])
                    ->schema([
                        Select::make('shop_id')
                            ->label('店舗')
                            ->relationship('shop', 'name', fn ($query) => $query->where('is_active', true)->orderBy('name'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(debounce: 500)
                            ->afterStateUpdated(fn (Set $set) => $set('menu_category_id', null)),
                        Select::make('menu_category_id')
                            ->label('メニューカテゴリ')
                            ->options(fn (Get $get): array => MenuCategory::query()
                                ->where('shop_id', (int) ($get('shop_id') ?: 0))
                                ->where('is_active', true)
                                ->orderBy('sort_order')
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->required()
                            ->searchable()
                            ->preload(),
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextInput::make('name')
                                    ->label('商品名 (Client App)')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('kitchen_name')
                                    ->label('キッチン表示名 (Staff KDS)')
                                    ->maxLength(255)
                                    ->placeholder('未設定時はゲスト側で商品名にフォールバック'),
                            ])
                            ->columnSpan(['default' => 1, 'sm' => 2]),
                        TextInput::make('slug')
                            ->label('スラッグ')
                            ->maxLength(255)
                            ->helperText('未入力時は商品名から自動生成。英小文字・数字・ハイフンのみ。')
                            ->rules([
                                'nullable',
                                'string',
                                'max:255',
                                new MenuItemSlugFormat,
                            ]),
                        Toggle::make('is_active')
                            ->label('メニューに表示')
                            ->helperText('オフにすると非表示（一覧でフィルタ可能）')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->extraAttributes(['class' => self::MARIO_SKY]),

                Section::make('価格・並び')
                    ->compact()
                    ->columns(['default' => 1, 'sm' => 2])
                    ->schema([
                        TextInput::make('from_price_minor')
                            ->label('税込参考価格 (DT)')
                            ->required()
                            ->default(0)
                            ->formatStateUsing(function ($state) {
                                if ($state === null || $state === '') {
                                    return '0';
                                }
                                if (is_string($state) && ! is_numeric($state)) {
                                    return (string) $state;
                                }

                                return MenuItemMoney::minorToDtInputString((int) $state);
                            })
                            ->dehydrateStateUsing(fn ($state) => MenuItemMoney::parseDtInputToMinor($state))
                            ->placeholder('12 または 12.5（0.5 DT 刻み）')
                            ->helperText('例: 12, 12.5, 12.5dt。内部は 0.5 DT=500 ミリウムで保存。'),
                        TextInput::make('sort_order')
                            ->label('並び順')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->step(1),
                    ])
                    ->extraAttributes(['class' => self::MARIO_GRASS]),

                Section::make('ヒーロー画像')
                    ->description('PNG / JPEG / WebP / SVG。SVG は管理画面限定で許可。')
                    ->compact()
                    ->schema([
                        FileUpload::make('hero_image_path')
                            ->label('画像')
                            ->disk('public')
                            ->directory('menu-items/heroes')
                            ->visibility('public')
                            ->acceptedFileTypes([
                                'image/svg+xml',
                                'image/png',
                                'image/jpeg',
                                'image/webp',
                                'image/gif',
                            ])
                            ->maxSize(2048)
                            ->downloadable()
                            ->openable()
                            ->imagePreviewHeight('120')
                            ->helperText('最大 2MB。未選択のままなら画像なし。'),
                    ])
                    ->extraAttributes(['class' => self::MARIO_COIN]),

                Section::make('説明')
                    ->compact()
                    ->schema([
                        Textarea::make('description')
                            ->label('説明文')
                            ->rows(3)
                            ->maxLength(5000)
                            ->columnSpanFull(),
                    ])
                    ->extraAttributes(['class' => self::MARIO_SKY]),

                Section::make('アレルギー・注意')
                    ->description('記載があるとゲスト側でバッジ表示（実装は次 Phase）。医療的保証ではありません。')
                    ->compact()
                    ->schema([
                        Textarea::make('allergy_note')
                            ->label('アレルギー・原材料の注意')
                            ->rows(2)
                            ->maxLength(2000)
                            ->placeholder('例: 小麦・大豆・ごま')
                            ->columnSpanFull(),
                    ])
                    ->extraAttributes(['class' => self::MARIO_GRASS]),

                Section::make('オプション構成 (Client App)')
                    ->description('スタイル（必須ラジオ相当）とトッピング（任意チェック相当）。ID 未入力時は表示名からスラッグを自動付与。重複は -2, -3 で解消。価格は 0.5 DT 刻み。')
                    ->compact()
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        Toggle::make('options_payload.rules.style_required')
                            ->label('スタイル選択を必須にするか')
                            ->default(false)
                            ->inline(false)
                            ->columnSpan(['default' => 1, 'lg' => 1]),
                        Repeater::make('options_payload.styles')
                            ->label('スタイル一覧（ラジオ相当）')
                            ->schema([
                                Grid::make(['default' => 1, 'sm' => 3])
                                    ->schema([
                                        TextInput::make('id')
                                            ->label('ID (slug)')
                                            ->maxLength(64)
                                            ->helperText('未入力なら表示名から自動。英小文字・数字・ハイフン。')
                                            ->rules([new MenuItemOptionIdFormat]),
                                        TextInput::make('name')
                                            ->label('表示名')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(debounce: 500)
                                            ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                                                $id = trim((string) ($get('id') ?? ''));
                                                if ($id !== '') {
                                                    return;
                                                }
                                                if (! is_string($state) || trim($state) === '') {
                                                    return;
                                                }
                                                $slug = Str::slug($state);
                                                if ($slug !== '') {
                                                    $set('id', $slug);
                                                }
                                            }),
                                        TextInput::make('price_minor')
                                            ->label('価格 (DT)')
                                            ->required()
                                            ->default(0)
                                            ->formatStateUsing(function ($state) {
                                                if ($state === null || $state === '') {
                                                    return '0';
                                                }
                                                if (is_string($state) && ! is_numeric($state)) {
                                                    return (string) $state;
                                                }

                                                return MenuItemMoney::minorToDtInputString((int) $state);
                                            })
                                            ->dehydrateStateUsing(fn ($state) => MenuItemMoney::parseDtInputToMinor($state))
                                            ->placeholder('0 / 0.5 / 12.5')
                                            ->helperText('0.5 DT 刻み'),
                                    ]),
                            ])
                            ->default([])
                            ->addActionLabel('スタイルを追加')
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                        Repeater::make('options_payload.toppings')
                            ->label('トッピング一覧（チェック相当）')
                            ->schema([
                                Grid::make(['default' => 1, 'sm' => 3])
                                    ->schema([
                                        TextInput::make('id')
                                            ->label('ID (slug)')
                                            ->maxLength(64)
                                            ->helperText('未入力なら表示名から自動。英小文字・数字・ハイフン。')
                                            ->rules([new MenuItemOptionIdFormat]),
                                        TextInput::make('name')
                                            ->label('表示名')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(debounce: 500)
                                            ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                                                $id = trim((string) ($get('id') ?? ''));
                                                if ($id !== '') {
                                                    return;
                                                }
                                                if (! is_string($state) || trim($state) === '') {
                                                    return;
                                                }
                                                $slug = Str::slug($state);
                                                if ($slug !== '') {
                                                    $set('id', $slug);
                                                }
                                            }),
                                        TextInput::make('price_delta_minor')
                                            ->label('追加価格 (DT)')
                                            ->required()
                                            ->default(0)
                                            ->formatStateUsing(function ($state) {
                                                if ($state === null || $state === '') {
                                                    return '0';
                                                }
                                                if (is_string($state) && ! is_numeric($state)) {
                                                    return (string) $state;
                                                }

                                                return MenuItemMoney::minorToDtInputString((int) $state);
                                            })
                                            ->dehydrateStateUsing(fn ($state) => MenuItemMoney::parseDtInputToMinor($state))
                                            ->placeholder('0 / 0.5')
                                            ->helperText('0.5 DT 刻み'),
                                    ]),
                            ])
                            ->default([])
                            ->addActionLabel('トッピングを追加')
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                    ])
                    ->extraAttributes(['class' => self::MARIO_BRICK]),
            ]);
    }

    /**
     * @param  array<string, true>  $used
     */
    private static function makeUniqueOptionId(string $base, array &$used): string
    {
        $candidate = $base;
        if (! isset($used[$candidate])) {
            $used[$candidate] = true;

            return $candidate;
        }
        $n = 2;
        for (; ;) {
            $candidate = $base.'-'.$n;
            if (! isset($used[$candidate])) {
                $used[$candidate] = true;

                return $candidate;
            }
            $n++;
        }
    }
}
