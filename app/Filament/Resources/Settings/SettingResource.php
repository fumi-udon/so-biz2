<?php

namespace App\Filament\Resources\Settings;

use App\Filament\Resources\Settings\Pages\ManageSettings;
use App\Filament\Support\AdminOnlyResource;
use App\Models\Setting;
use App\Support\InventorySettingOptions;
use App\Support\SettingFormValue;
use App\Support\StoreHolidaySetting;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
use JsonException;

class SettingResource extends AdminOnlyResource
{
    protected static ?string $model = Setting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'システム設定';

    protected static ?string $modelLabel = '設定';

    protected static ?string $pluralModelLabel = '設定';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('key')
                    ->label('キー')
                    ->required()
                    ->maxLength(255)
                    ->unique(Setting::class, 'key', ignoreRecord: true)
                    ->live(onBlur: true),
                Textarea::make('value')
                    ->label(function (Get $get): string {
                        $key = $get('key');

                        if ($key === StoreHolidaySetting::KEY) {
                            return '休業日リスト';
                        }

                        return InventorySettingOptions::isListKey($key)
                            ? '値（カンマ区切りの候補）'
                            : '値 (JSON)';
                    })
                    ->rows(5)
                    ->nullable()
                    ->helperText(function (Get $get): string {
                        $key = $get('key');

                        if ($key === StoreHolidaySetting::KEY) {
                            return '1行1日付、またはカンマ区切り。例: 2026-04-05 または 2026/4/5（保存時に Y-m-d に正規化・検証されます）';
                        }

                        return InventorySettingOptions::isListKey($key)
                            ? '候補をカンマで区切って入力します。例: close, open, lunch, prep（前後の空白は無視されます）'
                            : '数値・配列・オブジェクトは JSON 形式で入力してください。例: 10 または ["hall","kitchen"]';
                    })
                    ->formatStateUsing(function (mixed $state, Get $get): string {
                        if ($get('key') === StoreHolidaySetting::KEY) {
                            return StoreHolidaySetting::formatForTextarea($state);
                        }

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
                        if ($get('key') === StoreHolidaySetting::KEY) {
                            return StoreHolidaySetting::parseAndValidate(is_string($state) ? $state : null);
                        }

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
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
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
