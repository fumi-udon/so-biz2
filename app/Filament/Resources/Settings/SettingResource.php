<?php

namespace App\Filament\Resources\Settings;

use App\Filament\Resources\Settings\Pages\ManageSettings;
use App\Models\Setting;
use App\Support\InventorySettingOptions;
use App\Support\SettingFormValue;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
                Textarea::make('value')
                    ->label(fn (Get $get): string => InventorySettingOptions::isListKey($get('key'))
                        ? '値（カンマ区切りの候補）'
                        : '値 (JSON)')
                    ->rows(5)
                    ->nullable()
                    ->helperText(fn (Get $get): string => InventorySettingOptions::isListKey($get('key'))
                        ? '候補をカンマで区切って入力します。例: close, open, lunch, prep（前後の空白は無視されます）'
                        : '数値・配列・オブジェクトは JSON 形式で入力してください。例: 10 または ["hall","kitchen"]')
                    ->formatStateUsing(function (mixed $state, Get $get): string {
                        if (InventorySettingOptions::isListKey($get('key'))) {
                            return SettingFormValue::arrayToCommaLine($state);
                        }

                        if ($state === null || $state === '') {
                            return '';
                        }

                        if (is_string($state)) {
                            return $state;
                        }

                        return json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    })
                    ->dehydrateStateUsing(function (mixed $state, Get $get): mixed {
                        if (InventorySettingOptions::isListKey($get('key'))) {
                            return SettingFormValue::commaLineToArray(is_string($state) ? $state : null);
                        }

                        if (! is_string($state) || trim($state) === '') {
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
