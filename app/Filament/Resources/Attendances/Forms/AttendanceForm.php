<?php

namespace App\Filament\Resources\Attendances\Forms;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;

class AttendanceForm
{
    public static function configure(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('staff_id')
                    ->relationship('staff', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                DatePicker::make('date')
                    ->required()
                    ->native(false),
                DateTimePicker::make('lunch_in_at')
                    ->nullable()
                    ->seconds(false),
                DateTimePicker::make('lunch_out_at')
                    ->nullable()
                    ->seconds(false),
                DateTimePicker::make('dinner_in_at')
                    ->nullable()
                    ->seconds(false),
                DateTimePicker::make('dinner_out_at')
                    ->nullable()
                    ->seconds(false),
                TextInput::make('late_minutes')
                    ->numeric()
                    ->default(0),
                Textarea::make('in_note')
                    ->nullable()
                    ->rows(2),
                Textarea::make('out_note')
                    ->nullable()
                    ->rows(2),
                Textarea::make('admin_note')
                    ->nullable()
                    ->rows(2),
            ]);
    }
}
