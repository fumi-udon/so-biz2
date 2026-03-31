<?php

namespace App\Filament\Resources\Attendances;

use App\Filament\Resources\Attendances\Forms\AttendanceForm;
use App\Filament\Resources\Attendances\Pages\CreateAttendance;
use App\Filament\Resources\Attendances\Pages\EditAttendance;
use App\Filament\Resources\Attendances\Pages\ListAttendances;
use App\Filament\Resources\Attendances\Tables\AttendancesTable;
use App\Models\Attendance;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static ?string $navigationGroup = '店舗・勤怠管理';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'staff' => fn ($q) => $q->withTrashed()->with(['jobLevel']),
            'approvedByManager' => fn ($q) => $q->withTrashed(),
        ]);
    }

    public static function form(Form $form): Form
    {
        return AttendanceForm::configure($form);
    }

    public static function table(Table $table): Table
    {
        return AttendancesTable::configure($table);
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
            'index' => ListAttendances::route('/'),
            'create' => CreateAttendance::route('/create'),
            'edit' => EditAttendance::route('/{record}/edit'),
        ];
    }
}
