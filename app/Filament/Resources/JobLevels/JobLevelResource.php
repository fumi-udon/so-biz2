<?php

namespace App\Filament\Resources\JobLevels;

use App\Filament\Support\AdminOnlyResource;
use App\Filament\Resources\JobLevels\Pages\CreateJobLevel;
use App\Filament\Resources\JobLevels\Pages\EditJobLevel;
use App\Filament\Resources\JobLevels\Pages\ListJobLevels;
use App\Models\JobLevel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class JobLevelResource extends AdminOnlyResource
{
    protected static ?string $model = JobLevel::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Pourboires';

    protected static ?string $modelLabel = 'Job Level';

    protected static ?string $pluralModelLabel = 'Job Levels';

    /**
     * @return array<int, string>
     */
    protected static function defaultWeightSelectOptions(): array
    {
        $out = [];
        foreach (range(0, 100, 10) as $value) {
            $out[$value] = (string) $value;
        }

        return $out;
    }

    public static function form(Form $form): Form
    {
        $weightAllowed = array_keys(static::defaultWeightSelectOptions());

        return $form->schema([
            TextInput::make('level')
                ->required()
                ->integer()
                ->minValue(0)
                ->maxValue(10),
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            Select::make('default_weight')
                ->label('デフォルト比率 (Default Weight %)')
                ->required()
                ->native(false)
                ->options(static::defaultWeightSelectOptions())
                ->rules([
                    'required',
                    'integer',
                    'min:0',
                    'max:100',
                    Rule::in($weightAllowed),
                ])
                ->formatStateUsing(function (mixed $state): int {
                    if ($state === null || $state === '') {
                        return 0;
                    }

                    $snapped = (int) round((float) $state / 10) * 10;

                    return max(0, min(100, $snapped));
                })
                ->dehydrateStateUsing(fn (mixed $state): float => (float) (int) $state),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('level')->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('default_weight')
                    ->label('デフォルト比率 (%)')
                    ->formatStateUsing(fn (mixed $state): string => (string) (int) round((float) $state))
                    ->sortable(),
            ])
            ->defaultSort('level');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobLevels::route('/'),
            'create' => CreateJobLevel::route('/create'),
            'edit' => EditJobLevel::route('/{record}/edit'),
        ];
    }
}
