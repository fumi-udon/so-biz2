<?php

namespace App\Filament\Resources\Attendances\Tables;

use App\Models\Attendance;
use App\Models\Staff;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AttendancesTable
{
    protected static function timeInputColumn(string $attribute, string $label): TextInputColumn
    {
        return TextInputColumn::make($attribute)
            ->label($label)
            ->type('time')
            ->updateStateUsing(function (?string $state, Attendance $record) use ($attribute): ?string {
                if (blank($state)) {
                    $record->{$attribute} = null;
                    $record->is_edited_by_admin = true;
                    $record->save();

                    return null;
                }

                $date = $record->date instanceof Carbon
                    ? $record->date->copy()->startOfDay()
                    : Carbon::parse($record->date)->startOfDay();

                $record->{$attribute} = $date->setTimeFromTimeString($state);
                $record->is_edited_by_admin = true;
                $record->save();

                return $state;
            });
    }

    protected static function clockOutInputColumn(string $attribute, string $label): TextInputColumn
    {
        return self::timeInputColumn($attribute, $label)
            ->extraCellAttributes(function (Attendance $record) use ($attribute): array {
                $incomplete = match ($attribute) {
                    'lunch_out_at' => $record->lunch_in_at && ! $record->lunch_out_at,
                    'dinner_out_at' => $record->dinner_in_at && ! $record->dinner_out_at,
                    default => false,
                };

                return $incomplete
                    ? ['class' => '!bg-danger-50 dark:!bg-danger-950/40 ring-1 ring-danger-500/40']
                    : [];
            });
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->paginated(false)
            ->defaultSort('date', 'asc')
            ->filters([
                SelectFilter::make('staff_id')
                    ->label('👤 スタッフ選択')
                    ->options(fn (): array => Staff::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload(),
                Filter::make('month')
                    ->schema([
                        DatePicker::make('month_filter')
                            ->label('📅 表示月')
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
            ], layout: FiltersLayout::AboveContent)
            ->headerActions([
                CreateAction::make()
                    ->label('＋ 新規打刻追加')
                    ->slideOver(),
            ])
            ->columns([
                TextColumn::make('date')
                    ->label('日付')
                    ->date('m/d (D)')
                    ->sortable(),
                TextColumn::make('staff.name')
                    ->label('スタッフ')
                    ->searchable(),
                TextColumn::make('day_salary')
                    ->label('日給(参考)')
                    ->alignEnd()
                    ->formatStateUsing(function (Attendance $record): string {
                        $minutes = $record->calculateTotalMinutes();
                        $wage = $record->staff?->hourly_wage;
                        if ($minutes === null || $wage === null || (int) $wage === 0) {
                            return '—';
                        }

                        $yen = (int) round(($minutes / 60) * (int) $wage);

                        return number_format($yen).' 円';
                    }),
                self::timeInputColumn('lunch_in_at', 'L-In'),
                self::clockOutInputColumn('lunch_out_at', 'L-Out'),
                self::timeInputColumn('dinner_in_at', 'D-In'),
                self::clockOutInputColumn('dinner_out_at', 'D-Out'),
                TextColumn::make('id')
                    ->label('備考')
                    ->sortable(false)
                    ->formatStateUsing(function ($state, Attendance $record): string {
                        $parts = [];
                        if ($record->hasMissingClockOut()) {
                            $parts[] = '未退勤';
                        }
                        if ($record->approved_by_manager_id && $record->approvedByManager) {
                            $parts[] = '承認: '.$record->approvedByManager->name;
                        }

                        return $parts !== [] ? implode(' · ', $parts) : '—';
                    })
                    ->color(fn (Attendance $record): ?string => $record->hasMissingClockOut() ? 'danger' : null),
                TextInputColumn::make('admin_note')
                    ->label('メモ'),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
