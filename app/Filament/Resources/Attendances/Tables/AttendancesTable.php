<?php

namespace App\Filament\Resources\Attendances\Tables;

use App\Filament\Resources\Attendances\AttendanceResource;
use App\Filament\Resources\Attendances\Forms\AttendanceForm;
use App\Models\Attendance;
use App\Models\Staff;
use App\Support\AttendanceFormSaveData;
use App\Support\BusinessDate;
use App\Support\TipAttendanceScope;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
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

    /**
     * ランチ／ディナーのチップ状態ラベル（打刻・申請・剥奪）。
     */
    public static function formatTipMealBadge(Attendance $record, string $meal): string
    {
        $clock = $meal === 'lunch' ? $record->lunch_in_at !== null : $record->dinner_in_at !== null;
        $applied = $meal === 'lunch'
            ? (bool) ($record->is_lunch_tip_applied ?? false)
            : (bool) ($record->is_dinner_tip_applied ?? false);
        $denied = $meal === 'lunch'
            ? (bool) ($record->is_lunch_tip_denied ?? false)
            : (bool) ($record->is_dinner_tip_denied ?? false);

        if ($denied) {
            return '剥奪';
        }
        if ($clock && $applied && ! $denied) {
            $eligible = $meal === 'lunch'
                ? TipAttendanceScope::lunchEligible($record)
                : TipAttendanceScope::dinnerEligible($record);

            return $eligible ? '対象' : '—';
        }
        if ($clock && ! $applied) {
            return '未申請';
        }
        if (! $clock) {
            return '無打刻';
        }

        return '—';
    }

    public static function tipBadgeColor(Attendance $record, string $meal): string
    {
        $label = self::formatTipMealBadge($record, $meal);

        return match ($label) {
            '対象' => 'success',
            '剥奪' => 'danger',
            '未申請' => 'warning',
            '無打刻' => 'gray',
            default => 'gray',
        };
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
                            ->default(BusinessDate::current()->startOfMonth()),
                    ])
                    ->query(function (Builder $query, array $data): void {
                        $raw = $data['month_filter'] ?? null;
                        $d = blank($raw)
                            ? BusinessDate::current()->startOfMonth()
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
                    ->using(function (array $data, Table $table): Model {
                        $data = AttendanceFormSaveData::normalizeForCreate($data);
                        AttendanceFormSaveData::assertAtLeastOneMealClockIn($data);

                        $existing = Attendance::query()
                            ->where('staff_id', $data['staff_id'])
                            ->whereDate('date', $data['date'])
                            ->first();

                        if ($existing !== null) {
                            Notification::make()
                                ->warning()
                                ->title('既に該当日の出勤記録が存在します。編集画面に移動しました。')
                                ->send();

                            $livewire = $table->getLivewire();
                            $livewire->redirect(AttendanceResource::getUrl('edit', ['record' => $existing]));

                            throw new Halt;
                        }

                        return Attendance::query()->create($data);
                    }),
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
                TextColumn::make('tip_lunch')
                    ->label('Tip L')
                    ->badge()
                    ->getStateUsing(fn (Attendance $record): string => self::formatTipMealBadge($record, 'lunch'))
                    ->color(fn (Attendance $record): string => self::tipBadgeColor($record, 'lunch')),
                TextColumn::make('tip_dinner')
                    ->label('Tip D')
                    ->badge()
                    ->getStateUsing(fn (Attendance $record): string => self::formatTipMealBadge($record, 'dinner'))
                    ->color(fn (Attendance $record): string => self::tipBadgeColor($record, 'dinner')),
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
                        AttendanceFormSaveData::assertAtLeastOneMealClockIn($data);
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
