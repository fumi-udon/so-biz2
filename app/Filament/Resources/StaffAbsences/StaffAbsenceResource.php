<?php

namespace App\Filament\Resources\StaffAbsences;

use App\Filament\Resources\StaffAbsences\Forms\StaffAbsenceForm;
use App\Filament\Resources\StaffAbsences\Pages\CreateStaffAbsence;
use App\Filament\Resources\StaffAbsences\Pages\EditStaffAbsence;
use App\Filament\Resources\StaffAbsences\Pages\ListStaffAbsences;
use App\Filament\Resources\StaffAbsences\Tables\StaffAbsencesTable;
use App\Filament\Support\AdminOnlyResource;
use App\Models\StaffAbsence;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StaffAbsenceResource extends AdminOnlyResource
{
    protected static ?string $model = StaffAbsence::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    public static function getNavigationGroup(): ?string
    {
        return __('hq.nav_group_store', [], 'fr');
    }

    protected static ?string $modelLabel = '確定欠勤';

    protected static ?string $pluralModelLabel = '確定欠勤';

    protected static ?int $navigationSort = 25;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'staff' => fn ($query) => $query->withTrashed(),
        ]);
    }

    public static function form(Form $form): Form
    {
        return StaffAbsenceForm::configure($form);
    }

    public static function table(Table $table): Table
    {
        return StaffAbsencesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaffAbsences::route('/'),
            'create' => CreateStaffAbsence::route('/create'),
            'edit' => EditStaffAbsence::route('/{record}/edit'),
        ];
    }
}
