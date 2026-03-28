<?php

namespace App\Filament\Resources\Staff\Schemas;

use App\Models\Setting;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StaffForm
{
    /**
     * @return list<Fieldset>
     */
    protected static function fixedShiftFieldsets(): array
    {
        $days = [
            'monday' => 'Lundi',
            'tuesday' => 'Mardi',
            'wednesday' => 'Mercredi',
            'thursday' => 'Jeudi',
            'friday' => 'Vendredi',
            'saturday' => 'Samedi',
            'sunday' => 'Dimanche',
        ];

        $fieldsets = [];

        foreach ($days as $key => $label) {
            $fieldsets[] = Fieldset::make($label)
                ->schema([
                    TimePicker::make("{$key}.lunch_start")
                        ->label('Début Midi')
                        ->seconds(false),
                    TimePicker::make("{$key}.lunch_end")
                        ->label('Fin Midi')
                        ->seconds(false),
                    TimePicker::make("{$key}.dinner_start")
                        ->label('Début Soir')
                        ->seconds(false),
                    TimePicker::make("{$key}.dinner_end")
                        ->label('Fin Soir')
                        ->seconds(false),
                ])
                ->columns(2);
        }

        return $fieldsets;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('shop_id')
                    ->relationship('shop', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('pin_code')
                    ->label('PIN')
                    ->tel()
                    ->maxLength(4)
                    ->nullable()
                    ->rules(['nullable', 'digits:4'])
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null),
                Select::make('role')
                    ->options(function (): array {
                        $roles = Setting::getValue('staff_roles', ['hall', 'kitchen', 'manager', 'support']);

                        if (! is_array($roles)) {
                            return [];
                        }

                        $out = [];

                        foreach ($roles as $r) {
                            if (! is_string($r) || $r === '') {
                                continue;
                            }

                            $out[$r] = ucfirst($r);
                        }

                        return $out;
                    })
                    ->nullable(),
                TextInput::make('wage')
                    ->numeric()
                    ->step(0.01)
                    ->nullable(),
                TextInput::make('target_weekly_hours')
                    ->numeric()
                    ->integer()
                    ->nullable(),
                Toggle::make('is_active')
                    ->default(true),
                Section::make('Horaires hebdomadaires (fixed_shifts)')
                    ->description('Horaires théoriques par jour (détection de retard au pointage : tolérance 10 minutes).')
                    ->schema([
                        Group::make(self::fixedShiftFieldsets())
                            ->statePath('fixed_shifts'),
                    ])
                    ->collapsible(),
            ]);
    }
}
