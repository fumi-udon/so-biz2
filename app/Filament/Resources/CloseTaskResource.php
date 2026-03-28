<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CloseTask\Pages\CreateCloseTask;
use App\Filament\Resources\CloseTask\Pages\EditCloseTask;
use App\Filament\Resources\CloseTask\Pages\ListCloseTasks;
use App\Models\CloseTask;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CloseTaskResource extends Resource
{
    protected static ?string $model = CloseTask::class;

    protected static string|UnitEnum|null $navigationGroup = '店舗・勤怠管理';

    protected static ?string $modelLabel = 'クローズチェック項目';

    protected static ?string $pluralModelLabel = 'クローズチェック項目';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                Textarea::make('description'),
                FileUpload::make('image_path')
                    ->image()
                    ->directory('close_tasks')
                    ->disk('public')
                    ->label('画像'),
                Toggle::make('is_active')
                    ->default(true)
                    ->label('有効'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->disk('public')
                    ->label('画像'),
                TextColumn::make('title')
                    ->label('タイトル'),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('有効'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListCloseTasks::route('/'),
            'create' => CreateCloseTask::route('/create'),
            'edit' => EditCloseTask::route('/{record}/edit'),
        ];
    }
}
