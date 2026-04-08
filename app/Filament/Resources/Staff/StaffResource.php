<?php

namespace App\Filament\Resources\Staff;

use App\Filament\Resources\Staff\Forms\StaffForm;
use App\Filament\Resources\Staff\Pages\CreateStaff;
use App\Filament\Resources\Staff\Pages\EditStaff;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Filament\Resources\Staff\Tables\StaffTable;
use App\Filament\Support\AdminOnlyResource;
use App\Models\Staff;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StaffResource extends AdminOnlyResource
{
    protected static ?string $model = Staff::class;

    protected static ?string $navigationGroup = '店舗・勤怠管理';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('shop');
    }

    public static function form(Form $form): Form
    {
        return StaffForm::configure($form);
    }

    public static function table(Table $table): Table
    {
        return StaffTable::configure($table);
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
            'index' => ListStaff::route('/'),
            'create' => CreateStaff::route('/create'),
            'edit' => EditStaff::route('/{record}/edit'),
        ];
    }
}
