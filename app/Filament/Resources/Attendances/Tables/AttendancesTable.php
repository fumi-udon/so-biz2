<?php

namespace App\Filament\Resources\Attendances\Tables;

use App\Models\Attendance;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendancesTable
{
    /**
     * @return array<string, string>
     */
    public static function monthYearOptions(): array
    {
        $options = [];
        $d = now()->startOfMonth();

        for ($i = 0; $i < 24; $i++) {
            $key = $d->format('Y-m');
            $options[$key] = $d->translatedFormat('Y年n月');
            $d = $d->copy()->subMonth();
        }

        return $options;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('date', 'desc')
            ->paginationPageOptions([10, 25, 50, 100])
            ->columns([
                TextColumn::make('date')
                    ->label('日付')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('staff.name')
                    ->label('スタッフ')
                    ->searchable(),
                TextColumn::make('lunch_in_at')
                    ->label('ランチ出')
                    ->dateTime('H:i')
                    ->placeholder('—'),
                TextColumn::make('lunch_out_at')
                    ->label('ランチ退')
                    ->dateTime('H:i')
                    ->placeholder('—'),
                TextColumn::make('dinner_in_at')
                    ->label('ディナー出')
                    ->dateTime('H:i')
                    ->placeholder('—'),
                TextColumn::make('dinner_out_at')
                    ->label('ディナー退')
                    ->dateTime('H:i')
                    ->placeholder('—'),
                TextColumn::make('work_duration')
                    ->label('当日労働')
                    ->formatStateUsing(function (?string $state, Attendance $record): string {
                        return $record->formatWorkDuration() ?? '—';
                    })
                    ->placeholder('—'),
                TextColumn::make('work_hours_decimal')
                    ->label('時間(小数)')
                    ->formatStateUsing(function (?string $state, Attendance $record): string {
                        $h = $record->workHoursDecimal();

                        return $h !== null ? number_format($h, 2, '.', '').' h' : '—';
                    })
                    ->alignEnd(),
                TextColumn::make('late_minutes')
                    ->label('遅刻(分)')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),
                IconColumn::make('late_flag')
                    ->label('遅刻')
                    ->boolean()
                    ->getStateUsing(fn (Attendance $record): bool => $record->hasLateOccurrence()),
            ])
            ->filters([
                SelectFilter::make('staff_id')
                    ->relationship('staff', 'name')
                    ->label('スタッフを選択')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('month_ym')
                    ->label('表示月')
                    ->options(fn (): array => self::monthYearOptions())
                    ->default(now()->format('Y-m'))
                    ->query(function (Builder $query, array $data): void {
                        $ym = $data['value'] ?? null;

                        if (blank($ym) || ! is_string($ym) || ! preg_match('/^\d{4}-\d{2}$/', $ym)) {
                            return;
                        }

                        [$y, $m] = explode('-', $ym);
                        $query->whereYear('date', (int) $y)->whereMonth('date', (int) $m);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
