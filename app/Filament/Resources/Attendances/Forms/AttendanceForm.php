<?php

namespace App\Filament\Resources\Attendances\Forms;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;

class AttendanceForm
{
    private const string SKY_BLOCK =
        'rounded-2xl border-2 border-b-4 border-sky-300 bg-white shadow-sm dark:border-sky-700 dark:bg-slate-900';

    private const string AMBER_BLOCK =
        'rounded-2xl border-2 border-b-4 border-amber-400 bg-amber-50/90 shadow-sm dark:border-amber-700 dark:bg-amber-950/50';

    public static function configure(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('hq.form_section_identity', [], 'fr'))
                    ->compact()
                    ->columns(['default' => 2, 'md' => 2])
                    ->schema([
                        Select::make('staff_id')
                            ->label(__('hq.form_staff', [], 'fr'))
                            ->relationship('staff', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        DatePicker::make('date')
                            ->label(__('hq.form_date', [], 'fr'))
                            ->required()
                            ->native(false)
                            ->locale('fr')
                            ->displayFormat('d/m/Y')
                            ->weekStartsOnMonday()
                            ->live(debounce: 500),
                    ])
                    ->extraAttributes([
                        'class' => self::SKY_BLOCK,
                    ]),

                Section::make(__('hq.form_section_planned', [], 'fr'))
                    ->description(__('hq.form_section_planned_desc', [], 'fr'))
                    ->compact()
                    ->columns(['default' => 2, 'md' => 2])
                    ->schema([
                        TimePicker::make('scheduled_in_at')
                            ->label(__('hq.form_scheduled_in_lunch', [], 'fr'))
                            ->helperText(__('hq.form_scheduled_in_lunch_help', [], 'fr'))
                            ->nullable()
                            ->native(false)
                            ->locale('fr')
                            ->displayFormat('H:i')
                            ->seconds(false),
                        TimePicker::make('scheduled_dinner_at')
                            ->label(__('hq.form_scheduled_in_dinner', [], 'fr'))
                            ->helperText(__('hq.form_scheduled_in_dinner_help', [], 'fr'))
                            ->nullable()
                            ->native(false)
                            ->locale('fr')
                            ->displayFormat('H:i')
                            ->seconds(false),
                    ])
                    ->extraAttributes([
                        'class' => self::SKY_BLOCK,
                    ]),

                Section::make(__('hq.form_section_lunch', [], 'fr'))
                    ->description(__('hq.form_section_lunch_desc', [], 'fr'))
                    ->compact()
                    ->columns(['default' => 2, 'md' => 2])
                    ->schema([
                        TimePicker::make('lunch_in_at')
                            ->label(__('hq.form_lunch_in', [], 'fr'))
                            ->nullable()
                            ->native(false)
                            ->locale('fr')
                            ->displayFormat('H:i')
                            ->seconds(false),
                        TimePicker::make('lunch_out_at')
                            ->label(__('hq.form_lunch_out', [], 'fr'))
                            ->nullable()
                            ->native(false)
                            ->locale('fr')
                            ->displayFormat('H:i')
                            ->seconds(false),
                    ])
                    ->extraAttributes([
                        'class' => self::SKY_BLOCK,
                    ]),

                Section::make(__('hq.form_section_dinner', [], 'fr'))
                    ->description(__('hq.form_section_dinner_desc', [], 'fr'))
                    ->compact()
                    ->columns(['default' => 2, 'md' => 2])
                    ->schema([
                        TimePicker::make('dinner_in_at')
                            ->label(__('hq.form_dinner_in', [], 'fr'))
                            ->nullable()
                            ->native(false)
                            ->locale('fr')
                            ->displayFormat('H:i')
                            ->seconds(false),
                        TimePicker::make('dinner_out_at')
                            ->label(__('hq.form_dinner_out', [], 'fr'))
                            ->nullable()
                            ->native(false)
                            ->locale('fr')
                            ->displayFormat('H:i')
                            ->seconds(false),
                    ])
                    ->extraAttributes([
                        'class' => self::SKY_BLOCK,
                    ]),

                Section::make(__('hq.form_section_late', [], 'fr'))
                    ->compact()
                    ->schema([
                        TextInput::make('late_minutes')
                            ->label(__('hq.form_late_total', [], 'fr'))
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText(__('hq.form_late_total_help', [], 'fr')),
                    ])
                    ->extraAttributes([
                        'class' => self::SKY_BLOCK,
                    ]),

                Section::make(__('hq.form_section_notes', [], 'fr'))
                    ->compact()
                    ->columns(['default' => 2, 'md' => 2])
                    ->schema([
                        Textarea::make('in_note')
                            ->label(__('hq.form_note_in', [], 'fr'))
                            ->nullable()
                            ->rows(2),
                        Textarea::make('out_note')
                            ->label(__('hq.form_note_out', [], 'fr'))
                            ->nullable()
                            ->rows(2),
                        Textarea::make('admin_note')
                            ->label(__('hq.form_note_admin', [], 'fr'))
                            ->nullable()
                            ->rows(2)
                            ->columnSpan(['default' => 2, 'md' => 2]),
                    ])
                    ->extraAttributes([
                        'class' => self::SKY_BLOCK,
                    ]),

                Section::make(__('hq.section_tip_title', [], 'fr'))
                    // ->description(__('hq.section_tip_desc', [], 'fr'))
                    ->compact()
                    ->columns(['default' => 2, 'md' => 4])
                    ->schema([
                        Toggle::make('is_lunch_tip_applied')
                            ->label(__('hq.toggle_lunch_apply', [], 'fr'))
                            // ->helperText(__('hq.toggle_lunch_apply_help', [], 'fr'))
                            ->default(false),
                        Toggle::make('is_lunch_tip_denied')
                            ->label(__('hq.toggle_lunch_deny', [], 'fr'))
                            // ->helperText(__('hq.toggle_lunch_deny_help', [], 'fr'))
                            ->default(false),
                        Toggle::make('is_dinner_tip_applied')
                            ->label(__('hq.toggle_dinner_apply', [], 'fr'))
                            // ->helperText(__('hq.toggle_dinner_apply_help', [], 'fr'))
                            ->default(false),
                        Toggle::make('is_dinner_tip_denied')
                            ->label(__('hq.toggle_dinner_deny', [], 'fr'))
                            // ->helperText(__('hq.toggle_dinner_deny_help', [], 'fr'))
                            ->default(false),
                    ])
                    ->extraAttributes([
                        'class' => self::AMBER_BLOCK,
                    ]),
            ]);
    }
}
