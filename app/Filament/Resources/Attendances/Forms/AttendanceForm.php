<?php

namespace App\Filament\Resources\Attendances\Forms;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                DateTimePicker::make('scheduled_in_at')
                    ->label('予定出勤（ランチ）')
                    ->helperText('スナップショット。空のまま保存すると当日の fixed_shifts から埋まります。')
                    ->nullable()
                    ->seconds(false),
                DateTimePicker::make('scheduled_dinner_at')
                    ->label('予定出勤（ディナー）')
                    ->helperText('スナップショット。空のまま保存すると当日の fixed_shifts から埋まります。')
                    ->nullable()
                    ->seconds(false),
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
                    ->label('遅刻合計（分）')
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('打刻・予定時刻から自動計算されます。'),
                Textarea::make('in_note')
                    ->nullable()
                    ->rows(2),
                Textarea::make('out_note')
                    ->nullable()
                    ->rows(2),
                Textarea::make('admin_note')
                    ->nullable()
                    ->rows(2),
                Section::make('チップ管理 (Tip Management)')
                    ->description('タイムカード申請に加え、代理申請・剥奪はここで設定します。')
                    ->compact()
                    ->schema([
                        Toggle::make('is_lunch_tip_applied')
                            ->label('Lunch tip — 申請（代理）')
                            ->helperText('打刻があり、チップ配分対象とする場合は ON'),
                        Toggle::make('is_lunch_tip_denied')
                            ->label('Lunch tip — 剥奪')
                            ->helperText('ON でチップ配分から除外'),
                        Toggle::make('is_dinner_tip_applied')
                            ->label('Dinner tip — 申請（代理）')
                            ->helperText('打刻があり、チップ配分対象とする場合は ON'),
                        Toggle::make('is_dinner_tip_denied')
                            ->label('Dinner tip — 剥奪')
                            ->helperText('ON でチップ配分から除外'),
                    ])
                    ->columns(2)
                    ->extraAttributes([
                        'class' => 'rounded-lg border border-amber-200/80 bg-amber-50/40 dark:border-amber-800/50 dark:bg-amber-950/20',
                    ]),
            ]);
    }
}
