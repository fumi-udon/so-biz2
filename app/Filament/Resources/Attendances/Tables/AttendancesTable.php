<?php

namespace App\Filament\Resources\Attendances\Tables;

use App\Filament\Resources\Attendances\Forms\AttendanceForm;
use App\Models\Attendance;
use App\Models\Staff;
use App\Support\AttendanceFormSaveData;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class AttendancesTable
{
    public static function formatMealRange(Attendance $record, string $meal): string
    {
        $inKey = $meal === 'lunch' ? 'lunch_in_at' : 'dinner_in_at';
        $outKey = $meal === 'lunch' ? 'lunch_out_at' : 'dinner_out_at';
        $in = $record->{$inKey};
        $out = $record->{$outKey};

        if (! $in && ! $out) {
            return '—';
        }

        $inStr = $in instanceof Carbon ? $in->format('H:i') : '—';
        $outStr = $out instanceof Carbon ? $out->format('H:i') : '—';

        return $inStr.' – '.$outStr;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('date', 'desc')
            ->recordUrl(null)
            ->filters([
                SelectFilter::make('staff_id')
                    ->label('スタッフ')
                    ->options(fn (): array => Staff::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload(),
                Filter::make('month')
                    ->form([
                        DatePicker::make('month_filter')
                            ->label('表示月')
                            ->native(false)
                            ->displayFormat('Y年 m月')
                            ->default(now()->startOfMonth()),
                    ])
                    ->query(function (Builder $query, array $data): void {
                        $raw = $data['month_filter'] ?? null;
                        $d = blank($raw)
                            ? now()->startOfMonth()
                            : Carbon::parse($raw);
                        $query->whereYear('date', $d->year)->whereMonth('date', $d->month);
                    }),
            ], layout: FiltersLayout::Dropdown)
            ->filtersFormColumns(1)
            ->headerActions([
                CreateAction::make()
                    ->label('新規打刻')
                    ->slideOver()
                    ->form(fn (Form $form): Form => AttendanceForm::configure($form))
                    ->mutateFormDataUsing(fn (array $data): array => AttendanceFormSaveData::normalizeForCreate($data)),
            ])
            ->columns([
                TextColumn::make('date')
                    ->label('日付')
                    ->date()
                    ->sortable(),
                TextColumn::make('staff.name')
                    ->label('スタッフ')
                    ->searchable(),
                TextColumn::make('lunch')
                    ->label('ランチ')
                    ->getStateUsing(fn (Attendance $record): string => self::formatMealRange($record, 'lunch')),
                TextColumn::make('dinner')
                    ->label('ディナー')
                    ->getStateUsing(fn (Attendance $record): string => self::formatMealRange($record, 'dinner')),
                TextColumn::make('late_minutes')
                    ->label('遅刻（分）')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state === null ? '—' : (string) $state)
                    ->color(fn ($state): string => $state === null ? 'gray' : ((int) $state > 0 ? 'danger' : 'success')),
            ])
            ->actions([
                EditAction::make()
                    ->label('編集')
                    ->slideOver()
                    ->form(fn (Form $form): Form => AttendanceForm::configure($form))
                    ->using(function (array $data, Model $record, Table $table): Model {
                        /** @var Attendance $record */
                        $data = AttendanceFormSaveData::normalizeForRecord($record, $data);
                        $data['is_edited_by_admin'] = true;
                        $record->update($data);

                        return $record->refresh();
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
