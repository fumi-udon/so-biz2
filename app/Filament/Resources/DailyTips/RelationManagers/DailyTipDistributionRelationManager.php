<?php

namespace App\Filament\Resources\DailyTips\RelationManagers;

use App\Models\DailyTipDistribution;
use App\Models\Staff;
use App\Services\TipCalculationService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class DailyTipDistributionRelationManager extends RelationManager
{
    protected static string $relationship = 'distributions';

    protected static ?string $title = 'Tip Distributions';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('staff.name')
            ->columns([
                TextColumn::make('staff.name')->label('Staff Name')->searchable(),
                TextColumn::make('weight')->numeric(decimalPlaces: 3),
                TextColumn::make('amount')->numeric(decimalPlaces: 3),
                IconColumn::make('is_tardy_deprived')->label('Tardy')->boolean(),
                IconColumn::make('is_manual_added')->label('Manual')->boolean(),
                TextColumn::make('note')->limit(50)->wrap(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Staff')
                    ->form([
                        Select::make('staff_id')
                            ->label('Staff')
                            ->required()
                            ->options(fn (): array => Staff::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->rules([
                                Rule::unique('daily_tip_distributions', 'staff_id')
                                    ->where(fn ($query) => $query->where('daily_tip_id', $this->ownerRecord->id)),
                            ]),
                        TextInput::make('weight')
                            ->required()
                            ->numeric()
                            ->default(10)
                            ->step(0.001)
                            ->minValue(0),
                        TextInput::make('note')
                            ->maxLength(1000),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['is_manual_added'] = true;
                        $data['is_tardy_deprived'] = false;
                        $data['amount'] = 0;

                        return $data;
                    })
                    ->after(function (): void {
                        app(TipCalculationService::class)->recalculateAmounts($this->ownerRecord->fresh('distributions'));
                    }),
                Action::make('attach')
                    ->label('Attach Staff')
                    ->icon('heroicon-o-link')
                    ->form([
                        Select::make('staff_id')
                            ->label('Staff')
                            ->required()
                            ->options(fn (): array => Staff::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->rules([
                                Rule::unique('daily_tip_distributions', 'staff_id')
                                    ->where(fn ($query) => $query->where('daily_tip_id', $this->ownerRecord->id)),
                            ]),
                        TextInput::make('weight')
                            ->required()
                            ->numeric()
                            ->default(10)
                            ->step(0.001)
                            ->minValue(0),
                        TextInput::make('note')
                            ->maxLength(1000),
                    ])
                    ->action(function (array $data): void {
                        DailyTipDistribution::query()->create([
                            'daily_tip_id' => $this->ownerRecord->id,
                            'staff_id' => $data['staff_id'],
                            'weight' => $data['weight'],
                            'amount' => 0,
                            'is_tardy_deprived' => false,
                            'is_manual_added' => true,
                            'note' => $data['note'] ?? null,
                        ]);

                        app(TipCalculationService::class)->recalculateAmounts($this->ownerRecord->fresh('distributions'));
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->form([
                        TextInput::make('weight')
                            ->required()
                            ->numeric()
                            ->step(0.001)
                            ->minValue(0),
                        TextInput::make('note')
                            ->maxLength(1000),
                    ])
                    ->after(function (): void {
                        app(TipCalculationService::class)->recalculateAmounts($this->ownerRecord->fresh('distributions'));
                    }),
                DeleteAction::make()
                    ->after(function (): void {
                        app(TipCalculationService::class)->recalculateAmounts($this->ownerRecord->fresh('distributions'));
                    }),
            ]);
    }
}
