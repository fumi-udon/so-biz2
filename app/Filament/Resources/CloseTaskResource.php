<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CloseTask\Pages\CreateCloseTask;
use App\Filament\Resources\CloseTask\Pages\EditCloseTask;
use App\Filament\Resources\CloseTask\Pages\ListCloseTasks;
use App\Filament\Support\AdminOnlyResource;
use App\Models\CloseTask;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CloseTaskResource extends AdminOnlyResource
{
    protected static ?string $model = CloseTask::class;

    protected static ?string $navigationGroup = '店舗・勤怠管理';

    protected static ?string $modelLabel = 'クローズチェック項目';

    protected static ?string $pluralModelLabel = 'クローズチェック項目';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
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
