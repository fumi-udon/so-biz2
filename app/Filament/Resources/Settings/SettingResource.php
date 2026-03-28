<?php

namespace App\Filament\Resources\Settings;

use App\Filament\Resources\Settings\Pages\ManageSettings;
use App\Models\Setting;
use App\Support\InventorySettingOptions;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
use JsonException;
use UnitEnum;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'システム設定';

    protected static ?string $modelLabel = '設定';

    protected static ?string $pluralModelLabel = '設定';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->label('キー')
                    ->required()
                    ->maxLength(255)
                    ->unique(Setting::class, 'key', ignoreRecord: true)
                    ->live(onBlur: true),
                TagsInput::make('value')
                    ->label('値（候補）')
                    ->placeholder('候補を入力して Enter')
                    ->helperText('タイミング・カテゴリ・単位の各マスタはここで一覧を編集します。')
                    ->visible(fn (Get $get): bool => InventorySettingOptions::isListKey($get('key')))
                    ->dehydrated(fn (Get $get): bool => InventorySettingOptions::isListKey($get('key')))
                    ->formatStateUsing(function (mixed $state): array {
                        if ($state === null || $state === '') {
                            return [];
                        }

                        if (is_string($state)) {
                            try {
                                $decoded = json_decode($state, true, 512, JSON_THROW_ON_ERROR);
                                $state = $decoded;
                            } catch (JsonException) {
                                return [];
                            }
                        }

                        if (is_array($state)) {
                            return array_values(array_filter(
                                array_map(
                                    static fn (mixed $item): string => is_string($item) ? trim($item) : '',
                                    $state,
                                ),
                                static fn (string $s): bool => $s !== '',
                            ));
                        }

                        return [];
                    }),
                Textarea::make('value')
                    ->label('値 (JSON)')
                    ->rows(5)
                    ->nullable()
                    ->helperText('数値・配列・オブジェクトは JSON 形式で入力してください。例: 10 または ["hall","kitchen"]')
                    ->visible(fn (Get $get): bool => ! InventorySettingOptions::isListKey($get('key')))
                    ->dehydrated(fn (Get $get): bool => ! InventorySettingOptions::isListKey($get('key')))
                    ->formatStateUsing(function (mixed $state): string {
                        if ($state === null || $state === '') {
                            return '';
                        }

                        if (is_string($state)) {
                            return $state;
                        }

                        return json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    })
                    ->dehydrateStateUsing(function (?string $state): mixed {
                        if ($state === null || trim($state) === '') {
                            return null;
                        }

                        try {
                            return json_decode($state, true, 512, JSON_THROW_ON_ERROR);
                        } catch (JsonException) {
                            throw ValidationException::withMessages([
                                'value' => '無効な JSON です。',
                            ]);
                        }
                    }),
                TextInput::make('description')
                    ->label('説明')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label('キー')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('value')
                    ->label('値')
                    ->formatStateUsing(function (mixed $state): string {
                        if ($state === null) {
                            return '';
                        }

                        return is_string($state) ? $state : json_encode($state, JSON_UNESCAPED_UNICODE);
                    })
                    ->limit(80),
                TextColumn::make('description')
                    ->label('説明')
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSettings::route('/'),
        ];
    }
}
