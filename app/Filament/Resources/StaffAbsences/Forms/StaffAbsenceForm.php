<?php

namespace App\Filament\Resources\StaffAbsences\Forms;

use App\Models\StaffAbsence;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Builder;

class StaffAbsenceForm
{
    public static function configure(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('staff_id')
                    ->label('スタッフ')
                    ->relationship(
                        name: 'staff',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('name'),
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                DatePicker::make('date')
                    ->label('日付')
                    ->native(false)
                    ->required(),
                Select::make('meal_type')
                    ->label('区分')
                    ->options([
                        StaffAbsence::MEAL_LUNCH => 'ランチ',
                        StaffAbsence::MEAL_DINNER => 'ディナー',
                        StaffAbsence::MEAL_FULL => '全日',
                    ])
                    ->required(),
                Textarea::make('note')
                    ->label('メモ')
                    ->rows(2)
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
